<?php

namespace App\Http\Resources;

use App\Models\User;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $roles = $this->resource->roles();
        $this->resource->loadMissing('campuses:id,name');

        return [
            'id' => $this->id, 'name' => $this->name, 'email' => $this->email,
            'role' => $roles[0] ?? $this->role->value,
            'roles' => $roles,
            'role_labels' => array_map(fn (string $role) => UserRole::from($role)->label(), $roles),
            'permissions' => $this->resource->permissions(),
            'campus_ids' => $this->campuses->pluck('id')->all(),
            'campus_names' => $this->campuses->pluck('name')->all(),
            'is_active' => (bool) $this->is_active,
        ];
    }
}
