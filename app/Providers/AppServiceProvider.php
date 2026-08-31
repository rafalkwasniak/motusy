<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
}
