<?php

namespace App\Http\Controllers\Api;

use App\FoundationAccess;
use App\FoundationCatalog;
use App\FoundationRules;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveFoundationRequest;
use App\Http\Resources\FoundationResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class FoundationController extends Controller
{
    public function index(Request $request, string $resource): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,all'],
            'campus_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'in:name,-name,newest'],
        ]);
        $config = FoundationCatalog::get($resource);
        $query = FoundationAccess::query($request->user(), $resource)->with($config['relations']);
        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['campus_id']) && in_array('campus_id', $config['fields'], true)) {
            $query->where('campus_id', $filters['campus_id']);
        }
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q'])).'%';
            $query->where(function ($query) use ($term, $config): void {
                $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]);
                if (in_array('code', $config['fields'], true)) {
                    $query->orWhereRaw("LOWER(code) LIKE ? ESCAPE '!'", [$term]);
                }
            });
        }
        match ($filters['sort'] ?? 'name') {
            '-name' => $query->orderByDesc('name'),
            'newest' => $query->orderByDesc('id'),
            default => $query->orderBy('name'),
        };

        return FoundationResource::collection($query->orderBy('id')->paginate($filters['per_page'] ?? 15)->withQueryString())
            ->additional(['access' => ['create' => FoundationAccess::canWrite($request->user(), $resource, true), 'edit' => FoundationAccess::canWrite($request->user(), $resource)]]);
    }

    public function show(Request $request, string $resource, int $id): FoundationResource
    {
        $record = FoundationAccess::query($request->user(), $resource)->with(FoundationCatalog::get($resource)['relations'])->findOrFail($id);

        return new FoundationResource($record);
    }

    public function store(SaveFoundationRequest $request, string $resource): JsonResponse
    {
        return $this->save($request, $resource, null);
    }

    public function update(SaveFoundationRequest $request, string $resource, int $id): JsonResponse
    {
        return $this->save($request, $resource, $id);
    }

    private function save(SaveFoundationRequest $request, string $resource, ?int $id): JsonResponse
    {
        try {
            $record = DB::transaction(function () use ($request, $resource, $id) {
                DB::table('access_locks')->where('name', 'access-control')->lockForUpdate()->first();
                $actor = User::with('roleAssignments')->findOrFail($request->user()->id);
                abort_unless($actor->is_active && FoundationAccess::canWrite($actor, $resource, $id === null), 403);
                $query = FoundationAccess::query($actor, $resource);
                $record = $id ? $query->lockForUpdate()->findOrFail($id) : null;
                $data = FoundationRules::validate($actor, $resource, $request->all(), $record);
                $teachers = $data['teacher_ids'] ?? null;
                unset($data['teacher_ids']);
                $record ??= new (FoundationCatalog::get($resource)['model']);
                $record->fill($data);
                $changed = array_keys($record->getDirty());
                if ($teachers !== null && $record->exists && $record->teachers()->pluck('users.id')->sort()->values()->all() !== collect($teachers)->map(fn ($id) => (int) $id)->sort()->values()->all()) {
                    $changed[] = 'teacher_ids';
                } elseif ($teachers !== null && ! $record->exists) {
                    $changed[] = 'teacher_ids';
                }
                $record->save();
                if ($teachers !== null) {
                    $record->teachers()->sync($teachers);
                }
                if ($changed) {
                    AuditLog::create([
                        'actor_user_id' => $request->user()->id,
                        'campus_id' => $resource === 'campuses' ? $record->id : $record->getAttribute('campus_id'),
                        'subject_type' => $resource, 'subject_id' => $record->id,
                        'action' => $id ? 'updated' : 'created', 'changed_fields' => $changed,
                    ]);
                }

                return $record->load(FoundationCatalog::get($resource)['relations']);
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'A record with this code already exists.', 'errors' => ['code' => ['This code is already in use.']]], 422);
        }

        return (new FoundationResource($record))->response()->setStatusCode($id ? 200 : 201);
    }

    public function options(Request $request, string $resource): JsonResponse
    {
        FoundationAccess::query($request->user(), $resource);
        $options = [];
        foreach (FoundationCatalog::get($resource)['fields'] as $field) {
            if ($reference = FoundationCatalog::reference($field)) {
                $query = FoundationAccess::query($request->user(), $reference);
                $count = (clone $query)->count();
                abort_if($count > 2000, 422, 'This catalogue exceeds the current selector limit. Contact the administrator.');
                $options[$field] = $query->orderBy('name')->get()->map(fn ($record) => [
                    'value' => $record->id, 'label' => $record->name.' ('.$record->code.')',
                    'campus_id' => $record->getAttribute('campus_id'),
                    'academic_year_id' => $record->getAttribute('academic_year_id'),
                    'programme_id' => $record->getAttribute('programme_id'),
                ]);
            }
        }
        if ($resource === 'classes' && FoundationAccess::canWrite($request->user(), $resource)) {
            $visibleCampusIds = $request->user()->isGlobalAdmin() ? null : $request->user()->campuses()->pluck('campuses.id')->all();
            $teachers = User::where('is_active', true)->whereHas('roleAssignments', fn ($query) => $query->where('role', 'teacher'))
                ->whereHas('campuses', fn ($query) => $request->user()->isGlobalAdmin() ? $query : $query->whereIn('campuses.id', $request->user()->campuses()->select('campuses.id')))
                ->with('campuses:id')->orderBy('name');
            abort_if((clone $teachers)->count() > 2000, 422, 'Too many teacher options.');
            $options['teacher_ids'] = $teachers->get()->map(fn (User $teacher) => [
                'value' => $teacher->id, 'label' => $teacher->name,
                'campus_ids' => $teacher->campuses->pluck('id')->filter(fn (int $id) => $visibleCampusIds === null || in_array($id, $visibleCampusIds, true))->values(),
            ]);
        }

        return response()->json(['data' => $options]);
    }
}
