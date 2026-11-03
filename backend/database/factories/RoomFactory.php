<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Room> */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return ['campus_id' => Campus::factory(), 'building_id' => null, 'code' => strtoupper(fake()->unique()->bothify('R-####')), 'name' => 'Learning room', 'capacity' => 30, 'facilities' => ['Whiteboard'], 'status' => 'active'];
    }
}
