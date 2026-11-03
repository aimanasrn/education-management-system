<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Campus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Building> */
class BuildingFactory extends Factory
{
    public function definition(): array
    {
        return ['campus_id' => Campus::factory(), 'code' => strtoupper(fake()->unique()->bothify('B-####')), 'name' => 'Learning building', 'status' => 'active'];
    }
}
