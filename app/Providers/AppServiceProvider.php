<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinutes(
            config('motusy.auth.throttle.decay_minutes'),
            config('motusy.auth.throttle.attempts'),
        )->by($request->ip()));

        // Keyed by account rather than address: a rally sits behind a single carrier
        // NAT, and an address limit would shut everybody there out at once.
        RateLimiter::for('meetings', fn (Request $request) => Limit::perMinutes(
            config('motusy.meetings.throttle.decay_minutes'),
            config('motusy.meetings.throttle.attempts'),
        )->by((string) $request->user()?->id));
    }
}
