<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\Programme;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SchoolClass> */
class SchoolClassFactory extends Factory
{
    public function definition(): array
    {
        return ['campus_id' => Campus::factory(), 'academic_year_id' => AcademicYear::factory(), 'academic_term_id' => fn (array $attributes) => AcademicTerm::factory()->create(['academic_year_id' => $attributes['academic_year_id']])->id, 'programme_id' => Programme::factory(), 'subject_id' => null, 'room_id' => null, 'code' => fake()->unique()->bothify('CLASS-####'), 'name' => 'Learning class', 'capacity' => 20, 'status' => 'active'];
    }
}
