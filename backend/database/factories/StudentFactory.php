<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_number' => 'STU-'.fake()->unique()->numerify('######'),
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('+601########'),
            'enrollment_date' => '2026-01-05',
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => ['deleted_at' => now()]);
    }
}
