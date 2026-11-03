<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\LearningAccess;
use App\Models\AdmissionApplication;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Programme;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['all', 'draft', 'submitted', 'under_review', 'approved', 'rejected', 'withdrawn'])], 'campus_id' => ['nullable', 'integer'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $query = LearningAccess::applications($request->user())->with(['campus', 'programme', 'student', 'documents']);
        if (! empty($filters['campus_id'])) {
            $query->where('campus_id', $filters['campus_id']);
        }
        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($filters['q']))).'%';
            $query->where(fn ($query) => $query->whereRaw("LOWER(full_name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(reference) LIKE ? ESCAPE '!'", [$pattern]));
        }
        $result = $query->latest('id')->paginate(15);

        return response()->json(['data' => $result->getCollection()->map(fn ($record) => $this->payload($request, $record)), 'meta' => ['total' => $result->total(), 'current_page' => $result->currentPage(), 'last_page' => $result->lastPage()], 'access' => ['manage' => $request->user()->hasPermission('admissions.manage')]]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('admissions.view'), 403);
        $campuses = Campus::where('status', 'active');
        if ($request->user()->hasPermission('admissions.manage') && ! $request->user()->isGlobalAdmin()) {
            $campuses->whereIn('id', $request->user()->campuses()->select('campuses.id'));
        }
        abort_if((clone $campuses)->count() > 2000 || Programme::where('status', 'active')->count() > 2000, 422, 'Too many reference records. Contact an administrator.');

        return response()->json(['data' => ['campus_id' => $campuses->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]), 'programme_id' => Programme::where('status', 'active')->orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])]]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->payload($request, LearningAccess::applications($request->user())->with(['campus', 'programme', 'student', 'documents'])->findOrFail($id))]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->save($request, $id);
    }

    private function save(Request $request, ?int $id): JsonResponse
    {
        $record = DB::transaction(function () use ($request, $id) {
            $actor = $this->actor($request);
            abort_unless($actor->hasPermission('admissions.manage') || $actor->hasPermission('admissions.apply'), 403);
            $record = $id ? LearningAccess::applications($actor)->lockForUpdate()->findOrFail($id) : new AdmissionApplication;
            abort_if($record->exists && $record->status !== 'draft', 409, 'Only draft applications can be edited.');
            $data = $request->validate(['full_name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:254'], 'phone' => ['nullable', 'string', 'max:30'], 'campus_id' => ['required', 'integer', Rule::exists('campuses', 'id')->where('status', 'active')], 'programme_id' => ['required', 'integer', Rule::exists('programmes', 'id')->where('status', 'active')], 'requested_start' => ['required', 'date_format:Y-m-d']]);
            if ($actor->hasPermission('admissions.manage') && ! $actor->isGlobalAdmin()) {
                abort_unless($actor->campuses()->whereKey($data['campus_id'])->exists(), 403);
            }
            $record->fill($data);
            if (! $id) {
                $record->fill(['reference' => 'APP-'.Str::ulid(), 'created_by' => $actor->id, 'status' => 'draft']);
            }
            $changed = array_keys($record->getDirty());
            $record->save();
            $this->audit($actor, $record, $id ? 'updated' : 'created', $changed);

            return $record;
        });

        return response()->json(['data' => $this->payload($request, $record->load(['campus', 'programme', 'student', 'documents']))], $id ? 200 : 201);
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['submit', 'review', 'approve', 'reject', 'withdraw'])], 'note' => ['nullable', 'string', 'max:2000']]);
        $record = DB::transaction(function () use ($request, $id, $data) {
            $actor = $this->actor($request);
            $record = LearningAccess::applications($actor)->lockForUpdate()->findOrFail($id);
            $action = $data['action'];
            if (in_array($action, ['review', 'approve', 'reject'], true)) {
                abort_unless($actor->hasPermission('admissions.manage'), 403);
            }
            $allowed = ['submit' => ['draft'], 'review' => ['submitted'], 'approve' => ['under_review'], 'reject' => ['under_review'], 'withdraw' => ['draft', 'submitted']];
            abort_unless(in_array($record->status, $allowed[$action], true), 409, 'This action is unavailable for the current application status.');
            if ($action === 'submit') {
                abort_unless($record->campus->status === 'active' && $record->programme->status === 'active', 409, 'Choose an active campus and programme before submission.');
                $record->submitted_at = now();
            }
            if ($action === 'approve') {
                if ($record->documents()->whereNull('verified_at')->exists()) {
                    throw ValidationException::withMessages(['documents' => ['Verify every attached document before approval.']]);
                }
                abort_unless($record->campus->status === 'active' && $record->programme->status === 'active', 409, 'The campus and programme must still be active.');
                $student = Student::create(['student_number' => 'STU-'.Str::ulid(), 'full_name' => $record->full_name, 'email' => $record->email, 'phone' => $record->phone, 'enrollment_date' => $record->requested_start]);
                $record->student_id = $student->id;
                AuditLog::create(['actor_user_id' => $actor->id, 'campus_id' => $record->campus_id, 'subject_type' => 'students', 'subject_id' => $student->id, 'action' => 'created', 'changed_fields' => ['student_number', 'full_name', 'email', 'phone', 'enrollment_date']]);
            }
            if ($action === 'reject' && trim($data['note'] ?? '') === '') {
                throw ValidationException::withMessages(['note' => ['Give a reason for rejecting the application.']]);
            }
            $record->status = ['submit' => 'submitted', 'review' => 'under_review', 'approve' => 'approved', 'reject' => 'rejected', 'withdraw' => 'withdrawn'][$action];
            if (in_array($action, ['approve', 'reject'], true)) {
                $record->fill(['reviewed_by' => $actor->id, 'reviewed_at' => now(), 'decision_note' => $data['note'] ?? null]);
            }
            $changed = array_keys($record->getDirty());
            $record->save();
            $this->audit($actor, $record, 'updated', $changed);

            return $record;
        });

        return response()->json(['data' => $this->payload($request, $record->load(['campus', 'programme', 'student', 'documents']))]);
    }

    public function upload(Request $request, int $id): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']]);
        $path = null;
        $disk = config('filesystems.documents_disk', 'local');
        abort_unless(in_array($disk, ['local', 'garage'], true), 503, 'Document storage is not configured.');
        try {
            $document = DB::transaction(function () use ($request, $id, $disk, &$path) {
                $actor = $this->actor($request);
                $application = LearningAccess::applications($actor)->lockForUpdate()->findOrFail($id);
                abort_unless($application->status === 'draft', 409, 'Documents can only be added to drafts.');
                abort_if($application->documents()->count() >= 10, 422, 'A maximum of ten documents is allowed.');
                $file = $request->file('file');
                $path = $file->store('admissions', $disk);
                abort_unless($path, 503, 'Document storage is unavailable. Try again.');
                $document = $application->documents()->create(['name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255), 'path' => $path, 'disk' => $disk, 'bytes' => $file->getSize(), 'mime' => $file->getMimeType(), 'uploaded_by' => $actor->id]);
                $this->audit($actor, $application, 'updated', ['documents']);

                return $document;
            });
        } catch (\Throwable $error) {
            if ($path) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (\Throwable) {
                }
            }
            throw $error;
        }

        return response()->json(['data' => ['id' => $document->id, 'name' => $document->name]], 201);
    }

    public function download(Request $request, int $id, int $documentId): StreamedResponse
    {
        $application = LearningAccess::applications($request->user())->findOrFail($id);
        $document = $application->documents()->findOrFail($documentId);
        abort_unless(in_array($document->disk, ['local', 'garage'], true), 503, 'Document storage is not configured.');
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function verify(Request $request, int $id, int $documentId): JsonResponse
    {
        DB::transaction(function () use ($request, $id, $documentId): void {
            $actor = $this->actor($request);
            abort_unless($actor->hasPermission('admissions.manage'), 403);
            $application = LearningAccess::applications($actor)->lockForUpdate()->findOrFail($id);
            abort_unless($application->status === 'under_review', 409, 'Start reviewing the application first.');
            $document = $application->documents()->findOrFail($documentId);
            if (! $document->verified_at) {
                $document->update(['verified_at' => now(), 'verified_by' => $actor->id]);
                $this->audit($actor, $application, 'updated', ['document_verification']);
            }
        });

        return response()->json(['message' => 'Document verified.']);
    }

    private function actor(Request $request): User
    {
        DB::table('access_locks')->where('name', 'access-control')->lockForUpdate()->first();
        $actor = User::with('roleAssignments')->findOrFail($request->user()->id);
        abort_unless($actor->is_active, 403);

        return $actor;
    }

    private function audit(User $actor, AdmissionApplication $record, string $action, array $fields): void
    {
        if ($fields) {
            AuditLog::create(['actor_user_id' => $actor->id, 'campus_id' => $record->campus_id, 'subject_type' => 'admissions', 'subject_id' => $record->id, 'action' => $action, 'changed_fields' => $fields]);
        }
    }

    private function payload(Request $request, AdmissionApplication $record): array
    {
        $manage = $request->user()->hasPermission('admissions.manage');

        return ['id' => $record->id, 'reference' => $record->reference, 'full_name' => $record->full_name, 'email' => $record->email, 'phone' => $record->phone, 'campus_id' => $record->campus_id, 'campus' => $record->campus->name, 'programme_id' => $record->programme_id, 'programme' => $record->programme->name, 'requested_start' => $record->requested_start->toDateString(), 'status' => $record->status, 'decision_note' => $record->decision_note, 'student_id' => $record->student_id, 'student_number' => $record->student?->student_number, 'submitted_at' => $record->submitted_at?->toIso8601String(), 'reviewed_at' => $record->reviewed_at?->toIso8601String(), 'documents' => $record->documents->map(fn ($doc) => ['id' => $doc->id, 'name' => $doc->name, 'bytes' => $doc->bytes, 'verified_at' => $doc->verified_at?->toIso8601String()]), 'access' => ['edit' => $record->status === 'draft', 'submit' => $record->status === 'draft', 'withdraw' => in_array($record->status, ['draft', 'submitted'], true), 'review' => $manage && $record->status === 'submitted', 'decide' => $manage && $record->status === 'under_review']];
    }
}
