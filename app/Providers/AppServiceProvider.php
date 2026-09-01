<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Reguła ustalona przez Rafała: osiem znaków, wielka litera i cyfra.
        // Starter kit wymagał na produkcji dwunastu znaków ze znakiem
        // specjalnym i sprawdzeniem wycieków — za dużo jak na portal,
        // do którego ludzie logują się raz na jakiś czas.
        //
        // Reguła jest ta sama we wszystkich środowiskach. Kit rozluźniał ją
        // poza produkcją, przez co testy sprawdzały co innego niż to,
        // z czym mierzy się użytkownik.
        Password::defaults(
            fn (): Password => Password::min(8)->mixedCase()->numbers(),
        );
    }

    /**
     * Ograniczenie ruchu na API telemetrii.
     *
     * Liczone po adresie IP i **przed** sprawdzeniem tokena, żeby obejmowało
     * także nieudane próby — inaczej zgadywanie tokena byłoby nielimitowane.
     *
     * Sześćdziesiąt żądań na minutę to dużo jak na pudełko, które wysyła
     * po zakończonej jeździe, a jednocześnie zamyka drogę do przemiatania
     * dwunastoznakowych tokenów. Kod 429 jest przewidziany w kontrakcie
     * telemetrii §3: urządzenie ponawia z opóźnieniem, nie przestaje próbować.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('telemetria', fn (Request $request) => Limit::perMinute(60)
            ->by($request->ip() ?? 'nieznany'));
    }
}
