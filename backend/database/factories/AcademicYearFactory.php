<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AcademicYear> */
class AcademicYearFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('YEAR-####'), 'name' => 'Academic year', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active'];
    }
}
