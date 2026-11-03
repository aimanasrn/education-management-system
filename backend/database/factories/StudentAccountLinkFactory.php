<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentAccountLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentAccountLink> */
class StudentAccountLinkFactory extends Factory
{
    public function definition(): array
    {
        return ['student_id' => Student::factory(), 'user_id' => User::factory()];
    }
}
