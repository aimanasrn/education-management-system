<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentGuardian> */
class StudentGuardianFactory extends Factory
{
    public function definition(): array
    {
        return ['student_id' => Student::factory(), 'user_id' => User::factory()->afterCreating(function ($user): void {
            $user->roleAssignments()->delete();
            $user->roleAssignments()->create(['role' => 'guardian']);
        }), 'relationship' => 'Parent', 'emergency_contact' => false, 'status' => 'active'];
    }
}
