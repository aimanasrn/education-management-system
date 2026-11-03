<?php

namespace Database\Factories;

use App\Models\CampusBooking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CampusBooking> */
class CampusBookingFactory extends Factory
{
    public function definition(): array
    {
        return ['room_id' => Room::factory(), 'campus_id' => fn (array $a) => Room::find($a['room_id'])->campus_id, 'created_by' => User::factory(), 'kind' => 'booking', 'title' => 'Facility reservation', 'purpose' => null, 'attendees' => 1, 'priority' => 'normal', 'starts_at' => now()->addDay()->setTime(2, 0), 'ends_at' => now()->addDay()->setTime(3, 0), 'status' => 'requested'];
    }
}
