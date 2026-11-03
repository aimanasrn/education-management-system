<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserRoleAssignment> */
class UserRoleAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'role' => 'teacher'];
    }
}
