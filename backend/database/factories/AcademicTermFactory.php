<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AcademicTerm> */
class AcademicTermFactory extends Factory
{
    public function definition(): array
    {
        return ['academic_year_id' => AcademicYear::factory(), 'code' => fake()->unique()->bothify('TERM-####'), 'name' => 'First term', 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'status' => 'active'];
    }
}
