<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\CampusClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CampusClosure> */
class CampusClosureFactory extends Factory
{
    public function definition(): array
    {
        return ['campus_id' => Campus::factory(), 'name' => 'Development closure', 'kind' => 'closure', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-10', 'status' => 'active'];
    }
}
