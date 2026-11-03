<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class InfrastructureProbe implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 10;

    public function __construct(public string $key) {}

    public function handle(): void
    {
        Cache::store('redis')->put($this->key, 'processed', 60);
    }
}
