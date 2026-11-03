<?php

namespace Database\Factories;

use App\Models\AdmissionApplication;
use App\Models\AdmissionDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdmissionDocument> */
class AdmissionDocumentFactory extends Factory
{
    public function definition(): array
    {
        return ['admission_application_id' => AdmissionApplication::factory(), 'name' => 'evidence.pdf', 'path' => 'admissions/'.fake()->uuid().'.pdf', 'bytes' => 100, 'mime' => 'application/pdf', 'uploaded_by' => User::factory()];
    }
}
