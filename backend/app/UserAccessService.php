<?php

namespace App;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAccessService
{
    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?int $id): User
    {
        try {
            return DB::transaction(function () use ($actor, $data, $id): User {
                DB::table('access_locks')->where('name', 'access-control')->lockForUpdate()->first();
                $actor = User::with('roleAssignments')->findOrFail($actor->id);
                abort_unless($actor->is_active && $actor->hasPermission('users.manage'), 403);
                $user = $id ? User::with('roleAssignments', 'campuses')->lockForUpdate()->findOrFail($id) : new User;
                if (! $actor->hasAnyRole('admin')) {
                    abort_if(($id && $user->hasAnyRole('admin', 'centre_admin')) || array_intersect($data['roles'], ['admin', 'centre_admin']), 403, 'Only a system administrator can manage administrator access.');
                }
                if ($id === $actor->id && (! $data['is_active'] || ! in_array('admin', $data['roles'], true))) {
                    throw ValidationException::withMessages(['roles' => ['Ask another system administrator to change your own administrator access.']]);
                }
                if ($id && $user->hasAnyRole('admin') && (! $data['is_active'] || ! in_array('admin', $data['roles'], true))) {
                    $otherAdmin = User::where('is_active', true)->whereKeyNot($user->id)
                        ->whereHas('roleAssignments', fn ($query) => $query->where('role', 'admin'))->exists();
                    if (! $otherAdmin) {
                        throw ValidationException::withMessages(['roles' => ['At least one active system administrator is required.']]);
                    }
                }
                if ($id && $user->teachingClasses()->exists()) {
                    $assignedCampuses = $user->teachingClasses()->distinct()->pluck('campus_id')->all();
                    if (! in_array('teacher', $data['roles'], true) || array_diff($assignedCampuses, $data['campus_ids'])) {
                        throw ValidationException::withMessages(['campus_ids' => ['Reassign this user’s classes before removing their teacher role or campus assignment.']]);
                    }
                }
                $rolesChanged = ! $id || collect($user->roles())->sort()->values()->all() !== collect($data['roles'])->sort()->values()->all();
                $campusesChanged = ! $id || $user->campuses->pluck('id')->sort()->values()->all() !== collect($data['campus_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all();
                $user->fill(collect($data)->only(['name', 'email', 'password'])->all());
                $user->is_active = $data['is_active'];
                $user->role = in_array('admin', $data['roles'], true) ? UserRole::Admin : (in_array('teacher', $data['roles'], true) ? UserRole::Teacher : UserRole::Student);
                $changed = array_values(array_diff(array_keys($user->getDirty()), ['password', 'role']));
                if ($rolesChanged) {
                    $changed[] = 'roles';
                }
                if ($campusesChanged) {
                    $changed[] = 'campus_ids';
                }
                $user->saveQuietly();
                $user->roleAssignments()->delete();
                $user->roleAssignments()->createMany(array_map(fn (string $role) => ['role' => $role], $data['roles']));
                $user->campuses()->sync($data['campus_ids']);
                if (! $user->is_active || $rolesChanged || $campusesChanged) {
                    $user->tokens()->delete();
                }
                if ($changed) {
                    AuditLog::create(['actor_user_id' => $actor->id, 'campus_id' => null, 'subject_type' => 'users', 'subject_id' => $user->id, 'action' => $id ? 'access_updated' : 'created', 'changed_fields' => $changed]);
                }

                return $user->fresh(['roleAssignments', 'campuses']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => ['This email address is already in use.']]);
        }
    }
}
