<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEnrolment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentEnrolment> */
class StudentEnrolmentFactory extends Factory
{
    public function definition(): array
    {
        return ['student_id' => Student::factory(), 'school_class_id' => SchoolClass::factory(), 'starts_on' => '2026-01-05', 'ends_on' => '2026-06-30', 'status' => 'active', 'created_by' => User::factory()->admin()];
    }
}
