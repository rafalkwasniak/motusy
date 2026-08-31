<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Testy nie mogą zależeć od zbudowanego frontu.
     *
     * Bez tego każdy test renderujący stronę wywala się na
     * ViteManifestNotFoundException, dopóki ktoś nie odpali `npm run build`
     * — a build na tym hostingu bywa nieosiągalny (patrz CLAUDE.md §3).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->guardAgainstRunningOnTheProductionDatabase();
    }

    /**
     * Scache'owany config ignoruje wpisy `<env>` z phpunit.xml, więc testy
     * potrafią po cichu pójść po produkcyjnej bazie — a `RefreshDatabase`
     * kasuje wtedy prawdziwe dane. Lepiej wywalić się głośno.
     */
    protected function guardAgainstRunningOnTheProductionDatabase(): void
    {
        $connection = config('database.default');

        if ($connection !== 'sqlite') {
            $this->fail(
                "Testy chcą użyć połączenia [{$connection}] zamiast sqlite. ".
                'Najpewniej bootstrap/cache/config.php nadpisał phpunit.xml — usuń go (php artisan config:clear).'
            );
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
