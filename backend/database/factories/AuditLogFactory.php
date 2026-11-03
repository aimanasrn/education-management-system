<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return ['actor_user_id' => User::factory(), 'campus_id' => null, 'subject_type' => 'campuses', 'subject_id' => 1, 'action' => 'created', 'changed_fields' => ['name', 'code']];
    }
}
