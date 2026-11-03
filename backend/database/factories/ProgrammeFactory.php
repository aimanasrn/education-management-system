<?php

namespace Database\Factories;

use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Programme> */
class ProgrammeFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('PROG-####'), 'name' => 'Foundation learning', 'status' => 'active'];
    }
}
