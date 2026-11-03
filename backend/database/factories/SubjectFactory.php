<?php

namespace Database\Factories;

use App\Models\Programme;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subject> */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return ['programme_id' => Programme::factory(), 'code' => fake()->unique()->bothify('SUB-####'), 'name' => 'Language', 'status' => 'active'];
    }
}
