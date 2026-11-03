<?php

namespace App\Providers;

use App\JwtAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::viaRequest('ems-jwt', fn (Request $request) => app(JwtAccess::class)->authenticate($request));
        RateLimiter::for('jwt-refresh', fn (Request $request): array => [
            Limit::perMinute(20)->by('jwt-refresh-user:'.$request->user()?->id),
            Limit::perMinute(60)->by('jwt-refresh-ip:'.$request->ip()),
        ]);
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $account = is_string($email) ? Str::lower(trim($email)) : '';

            return [
                Limit::perMinute(60)->by('login-ip:'.$request->ip()),
                Limit::perMinute(5)->by('login-account:'.hash('sha256', $account)),
            ];
        });
    }
}
