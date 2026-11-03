<?php

namespace App\Http\Controllers\Api;

use App\EnrolmentRules;
use App\FoundationAccess;
use App\Http\Controllers\Controller;
use App\LearningAccess;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\StudentAccountLink;
use App\Models\StudentEnrolment;
use App\Models\StudentGuardian;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LearningController extends Controller
{
    public function students(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $query = LearningAccess::students($request->user())->with(['accountLink.user', 'guardianLinks.user']);
        if (! empty($filters['q'])) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($filters['q']))).'%';
            $query->where(fn ($query) => $query->whereRaw("LOWER(full_name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(student_number) LIKE ? ESCAPE '!'", [$pattern]));
        }
        $result = $query->orderBy('full_name')->orderBy('id')->paginate(15);

        return response()->json(['data' => $result->getCollection()->map(fn ($student) => $this->studentPayload($request->user(), $student)), 'meta' => ['total' => $result->total(), 'current_page' => $result->currentPage(), 'last_page' => $result->lastPage()]]);
    }

    public function student(Request $request, int $id): JsonResponse
    {
        $student = LearningAccess::students($request->user())->with(['accountLink.user', 'guardianLinks.user'])->findOrFail($id);
        $enrolments = LearningAccess::enrolments($request->user())->where('student_id', $id)->with(['student', 'schoolClass.campus', 'schoolClass.programme', 'schoolClass.academicTerm'])->orderByDesc('starts_on')->orderByDesc('id')->paginate(15);

        return response()->json(['data' => $this->studentPayload($request->user(), $student), 'enrolments' => $enrolments->getCollection()->map(fn ($record) => $this->enrolmentPayload($request->user(), $record)), 'enrolment_total' => $enrolments->total()]);
    }

    public function enrolments(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'campus_id' => ['nullable', 'integer'], 'student_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['all', 'active', 'completed', 'withdrawn', 'transferred'])], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $query = LearningAccess::enrolments($request->user())->with(['student', 'schoolClass.campus', 'schoolClass.programme', 'schoolClass.academicTerm']);
        if (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }
        if (! empty($filters['campus_id'])) {
            $query->whereHas('schoolClass', fn ($query) => $query->where('campus_id', $filters['campus_id']));
        }
        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($filters['q']))).'%';
            $query->where(fn ($query) => $query->whereHas('student', fn ($q) => $q->whereRaw("LOWER(full_name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(student_number) LIKE ? ESCAPE '!'", [$pattern]))->orWhereHas('schoolClass', fn ($q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])));
        }
        $result = $query->orderByDesc('starts_on')->orderByDesc('id')->paginate(15);

        return response()->json(['data' => $result->getCollection()->map(fn ($record) => $this->enrolmentPayload($request->user(), $record)), 'meta' => ['total' => $result->total(), 'current_page' => $result->currentPage(), 'last_page' => $result->lastPage()]]);
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('enrolments.manage') || $user->hasPermission('links.manage'), 403);
        $students = LearningAccess::students($user)->withoutTrashed()->orderBy('full_name');
        $classes = FoundationAccess::query($user, 'classes')->where('status', 'active')->with(['campus', 'academicTerm'])->orderBy('name');
        abort_if((clone $students)->count() > 2000 || (clone $classes)->count() > 2000, 422, 'Too many records for this selector. Contact an administrator.');
        $data = ['student_id' => $students->get()->map(fn ($s) => ['value' => $s->id, 'label' => $s->full_name.' ('.$s->student_number.')']), 'school_class_id' => $classes->get()->map(fn ($c) => ['value' => $c->id, 'label' => $c->name.' — '.$c->campus->name.' ('.$c->code.')', 'starts_on' => $c->academicTerm->starts_on->toDateString(), 'ends_on' => $c->academicTerm->ends_on->toDateString()])];
        if ($user->hasPermission('links.manage')) {
            foreach (['guardian', 'student'] as $role) {
                $users = User::where('is_active', true)->whereHas('roleAssignments', fn ($q) => $q->where('role', $role))->orderBy('name');
                abort_if((clone $users)->count() > 2000, 422, 'Too many account options.');
                $data[$role.'_user_id'] = $users->get()->map(fn ($u) => ['value' => $u->id, 'label' => $u->name.' ('.$u->email.')']);
            }
        }

        return response()->json(['data' => $data]);
    }

    public function enrol(Request $request): JsonResponse
    {
        $record = DB::transaction(function () use ($request) {
            $actor = $this->actor($request, 'enrolments.manage');
            $data = EnrolmentRules::validate($actor, $request->all());
            Student::whereKey($data['student_id'])->lockForUpdate()->firstOrFail();
            $record = StudentEnrolment::create([...$data, 'created_by' => $actor->id, 'status' => 'active']);
            $this->checkCapacity($record);
            $this->audit($actor, 'enrolments', $record->id, $record->schoolClass->campus_id, 'created', array_keys($data));

            return $record;
        });

        return response()->json(['data' => $this->enrolmentPayload($request->user(), $record->load(['student', 'schoolClass.campus', 'schoolClass.programme', 'schoolClass.academicTerm']))], 201);
    }

    public function changeEnrolment(Request $request, int $id): JsonResponse
    {
        $input = $request->validate(['action' => ['required', Rule::in(['transfer', 'complete', 'withdraw'])], 'effective_on' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'max:500'], 'school_class_id' => ['required_if:action,transfer', 'nullable', 'integer'], 'ends_on' => ['required_if:action,transfer', 'nullable', 'date_format:Y-m-d']]);
        $record = DB::transaction(function () use ($request, $id, $input) {
            $actor = $this->actor($request, 'enrolments.manage');
            $record = LearningAccess::enrolments($actor)->lockForUpdate()->findOrFail($id);
            FoundationAccess::query($actor, 'classes')->findOrFail($record->school_class_id);
            abort_unless($record->status === 'active', 409, 'This enrolment has already ended or transferred.');
            $effective = $input['effective_on'];
            $start = $record->starts_on->toDateString();
            $end = $record->ends_on->toDateString();
            if ($effective < $start || $effective > $end || ($input['action'] === 'transfer' && $effective === $start)) {
                throw ValidationException::withMessages(['effective_on' => ['The date must fit the current enrolment; transfers must start after its first day.']]);
            }
            $record->status = ['transfer' => 'transferred', 'complete' => 'completed', 'withdraw' => 'withdrawn'][$input['action']];
            $record->ends_on = $input['action'] === 'transfer' ? Carbon::parse($effective)->subDay()->toDateString() : $effective;
            $record->end_reason = $input['reason'];
            $record->save();
            if ($input['action'] === 'transfer') {
                if ((int) $input['school_class_id'] === $record->school_class_id) {
                    throw ValidationException::withMessages(['school_class_id' => ['Choose a different class for a transfer.']]);
                }
                $data = EnrolmentRules::validate($actor, ['student_id' => $record->student_id, 'school_class_id' => $input['school_class_id'], 'starts_on' => $effective, 'ends_on' => $input['ends_on']]);
                $successor = StudentEnrolment::create([...$data, 'created_by' => $actor->id, 'previous_enrolment_id' => $record->id, 'status' => 'active']);
                $this->checkCapacity($successor);
                $this->audit($actor, 'enrolments', $successor->id, $successor->schoolClass->campus_id, 'created', ['student_id', 'school_class_id', 'previous_enrolment_id', 'starts_on', 'ends_on']);
            }
            $this->audit($actor, 'enrolments', $record->id, $record->schoolClass->campus_id, 'updated', ['status', 'ends_on', 'end_reason']);

            return $record;
        });

        return response()->json(['data' => $this->enrolmentPayload($request->user(), $record->load(['student', 'schoolClass.campus', 'schoolClass.programme', 'schoolClass.academicTerm']))]);
    }

    public function link(Request $request): JsonResponse
    {
        $input = $request->validate(['student_id' => ['required', 'integer'], 'user_id' => ['required', 'integer'], 'kind' => ['required', Rule::in(['student', 'guardian'])], 'relationship' => ['required_if:kind,guardian', 'nullable', 'string', 'max:60'], 'emergency_contact' => ['sometimes', 'boolean'], 'status' => ['required_if:kind,guardian', Rule::in(['active', 'inactive'])]]);
        DB::transaction(function () use ($request, $input): void {
            $actor = $this->actor($request, 'links.manage');
            $student = LearningAccess::students($actor)->lockForUpdate()->findOrFail($input['student_id']);
            abort_if($student->trashed(), 409, 'Restore the student before changing account links.');
            $user = User::with('roleAssignments')->findOrFail($input['user_id']);
            $revoking = ($input['status'] ?? 'active') === 'inactive';
            if (! $revoking && (! $user->is_active || ! $user->hasAnyRole($input['kind']))) {
                throw ValidationException::withMessages(['user_id' => ['Choose an enabled account with the matching role.']]);
            }
            if ($input['kind'] === 'student') {
                if ($revoking) {
                    $record = StudentAccountLink::where('student_id', $student->id)->where('user_id', $user->id)->whereNull('revoked_at')->firstOrFail();
                    $record->update(['revoked_at' => now()]);
                } else {
                    if (StudentAccountLink::whereNull('revoked_at')->where(fn ($query) => $query->where('student_id', $student->id)->orWhere('user_id', $user->id))->exists()) {
                        throw ValidationException::withMessages(['user_id' => ['The student or account already has an active self link. Revoke the old link before assigning another identity.']]);
                    }
                    $record = StudentAccountLink::create(['student_id' => $student->id, 'user_id' => $user->id]);
                }
            } else {
                $record = StudentGuardian::updateOrCreate(['student_id' => $student->id, 'user_id' => $user->id], ['relationship' => $input['relationship'], 'emergency_contact' => $input['emergency_contact'] ?? false, 'status' => $input['status']]);
            }
            $this->audit($actor, 'student_links', $record->id, null, 'access_updated', ['student_id', 'user_id', 'kind', 'status']);
        });

        return response()->json(['message' => 'Student account link saved.']);
    }

    private function actor(Request $request, string $permission): User
    {
        DB::table('access_locks')->where('name', 'access-control')->lockForUpdate()->first();
        $actor = User::with('roleAssignments')->findOrFail($request->user()->id);
        abort_unless($actor->is_active && $actor->hasPermission($permission), 403);

        return $actor;
    }

    private function checkCapacity(StudentEnrolment $record): void
    {
        if (EnrolmentRules::peak($record->schoolClass) > $record->schoolClass->capacity) {
            throw ValidationException::withMessages(['school_class_id' => ['The class has no remaining capacity during this period.']]);
        }
    }

    private function audit(User $actor, string $type, int $id, ?int $campus, string $action, array $fields): void
    {
        AuditLog::create(['actor_user_id' => $actor->id, 'campus_id' => $campus, 'subject_type' => $type, 'subject_id' => $id, 'action' => $action, 'changed_fields' => $fields]);
    }

    private function studentPayload(User $user, Student $student): array
    {
        $contact = LearningAccess::canContact($user, $student);

        return ['id' => $student->id, 'full_name' => $student->full_name, 'student_number' => $student->student_number, 'status' => $student->trashed() ? 'archived' : 'active', 'email' => $contact ? $student->email : null, 'phone' => $contact ? $student->phone : null, 'contacts_visible' => $contact, 'enrollment_date' => $student->enrollment_date->toDateString(), 'account' => $user->hasPermission('links.manage') && $student->accountLink ? ['user_id' => $student->accountLink->user_id, 'name' => $student->accountLink->user->name] : null, 'guardians' => $user->hasPermission('links.manage') ? $student->guardianLinks->map(fn ($g) => ['user_id' => $g->user_id, 'name' => $g->user->name, 'relationship' => $g->relationship, 'emergency_contact' => $g->emergency_contact, 'status' => $g->status]) : []];
    }

    private function enrolmentPayload(User $user, StudentEnrolment $record): array
    {
        $class = $record->schoolClass;

        return ['id' => $record->id, 'student_id' => $record->student_id, 'student' => $record->student->full_name, 'student_number' => $record->student->student_number, 'school_class_id' => $record->school_class_id, 'class' => $class->name, 'campus' => $class->campus->name, 'programme' => $class->programme->name, 'term' => $class->academicTerm->name, 'starts_on' => $record->starts_on->toDateString(), 'ends_on' => $record->ends_on->toDateString(), 'status' => $record->status, 'end_reason' => LearningAccess::canContact($user, $record->student) ? $record->end_reason : null, 'previous_enrolment_id' => $record->previous_enrolment_id];
    }
}
