<?php

namespace Database\Seeders;

use App\Models\CampusClosure;
use Illuminate\Database\Seeder;

class CampusClosureSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Synthetic seeds are restricted to development.');
        }
        CampusClosure::factory()->create();
    }
}
