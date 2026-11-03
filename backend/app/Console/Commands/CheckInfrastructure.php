<?php

namespace App\Console\Commands;

use App\Jobs\InfrastructureProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CheckInfrastructure extends Command
{
    protected $signature = 'ems:check-infrastructure {--queue : Also dispatch a short-lived worker probe}';

    protected $description = 'Check Redis, private Garage read/write and optionally the Redis queue worker without changing business records';

    public function handle(): int
    {
        $success = true;
        $key = 'infra-check:'.Str::uuid();
        try {
            Redis::setex($key, 60, 'ok');
            if (Redis::get($key) !== 'ok') {
                throw new \RuntimeException;
            }
            Cache::store('redis')->put($key, 'cached', 60);
            if (Cache::store('redis')->get($key) !== 'cached') {
                throw new \RuntimeException;
            }
            $this->info('Redis cache: PASS');
        } catch (\Throwable) {
            $this->error('Redis cache: FAIL');
            $success = false;
        } finally {
            try {
                Redis::del($key);
                Cache::store('redis')->forget($key);
            } catch (\Throwable) {
            }
        }
        $path = 'infrastructure-checks/'.Str::uuid();
        try {
            $disk = Storage::disk('garage');
            $content = random_bytes(64);
            if (! $disk->put($path, $content) || $disk->get($path) !== $content) {
                throw new \RuntimeException;
            }
            $url = rtrim(config('filesystems.disks.garage.endpoint'), '/').'/'.rawurlencode(config('filesystems.disks.garage.bucket')).'/'.$path;
            if (Http::timeout(5)->get($url)->status() !== 403 || ! $disk->delete($path)) {
                throw new \RuntimeException;
            }
            $this->info('Garage private storage: PASS');
        } catch (\Throwable) {
            $this->error('Garage private storage: FAIL');
            $success = false;
        } finally {
            try {
                Storage::disk('garage')->delete($path);
            } catch (\Throwable) {
            }
        }
        if ($this->option('queue')) {
            try {
                InfrastructureProbe::dispatch($key)->onConnection('redis');
                $deadline = microtime(true) + 10;
                while (Cache::store('redis')->get($key) !== 'processed' && microtime(true) < $deadline) {
                    usleep(200000);
                }
                if (Cache::store('redis')->get($key) !== 'processed') {
                    throw new \RuntimeException;
                }
                $this->info('Redis queue worker: PASS');
            } catch (\Throwable) {
                $this->error('Redis queue worker: FAIL');
                $success = false;
            } finally {
                try {
                    Cache::store('redis')->forget($key);
                } catch (\Throwable) {
                }
            }
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
