<?php

namespace Database\Factories;

use App\Models\AdmissionApplication;
use App\Models\Campus;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AdmissionApplication> */
class AdmissionApplicationFactory extends Factory
{
    public function definition(): array
    {
        return ['reference' => 'APP-'.Str::ulid(), 'created_by' => User::factory(), 'campus_id' => Campus::factory(), 'programme_id' => Programme::factory(), 'full_name' => fake()->name(), 'email' => fake()->safeEmail(), 'phone' => null, 'requested_start' => '2026-01-05', 'status' => 'draft'];
    }
}
