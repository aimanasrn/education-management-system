<?php

namespace Database\Factories;

use App\Models\Campus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Campus> */
class CampusFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->bothify('CAMP-####')), 'name' => fake()->city().' Campus', 'status' => 'active', 'operating_hours' => collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])->mapWithKeys(fn ($day) => [$day => ['closed' => true, 'opens' => null, 'closes' => null]])->all()];
    }
}
