<?php

namespace App\Models;

use App\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $attributes = ['is_active' => true];

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $user->roleAssignments()->create(['role' => ($user->role ?? UserRole::Student)->value]);
        });
        static::updated(function (User $user): void {
            if ($user->wasChanged('role')) {
                $user->roleAssignments()->delete();
                $user->roleAssignments()->create(['role' => $user->role->value]);
                $user->unsetRelation('roleAssignments');
            }
        });
    }

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'role' => UserRole::class, 'is_active' => 'boolean'];
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    public function campuses(): BelongsToMany
    {
        return $this->belongsToMany(Campus::class);
    }

    public function teachingClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_teachers');
    }

    public function studentAccountLinks(): HasMany
    {
        return $this->hasMany(StudentAccountLink::class)->whereNull('revoked_at');
    }

    public function guardianLinks(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }

    /** @return list<string> */
    public function roles(): array
    {
        $this->loadMissing('roleAssignments');
        $assigned = $this->roleAssignments->pluck('role')->all();

        return array_values(array_filter(array_map(fn (UserRole $role) => $role->value, UserRole::cases()), fn (string $role) => in_array($role, $assigned, true)));
    }

    public function hasAnyRole(string ...$roles): bool
    {
        return count(array_intersect($this->roles(), $roles)) > 0;
    }

    public function isGlobalAdmin(): bool
    {
        return $this->hasAnyRole('admin', 'centre_admin');
    }

    /** @return list<string> */
    public function permissions(): array
    {
        $permissions = [];
        foreach ($this->roles() as $role) {
            $permissions = array_merge($permissions, UserRole::from($role)->permissions());
        }

        return array_values(array_unique($permissions));
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
