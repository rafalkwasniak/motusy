<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\User;
use App\Support\Pomiar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_is_public(): void
    {
        $this->get('/')->assertOk();
    }

    /**
     * Dopóki nie ma żadnego przejazdu, pokazujemy przykład — ale oznaczony,
     * żeby nie wyglądał na prawdziwy odczyt.
     */
    public function test_without_any_ride_the_panel_shows_a_labelled_example(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(__('Ostatnia jazda'))
            ->assertSee(__('przykład'));
    }

    public function test_the_newest_ride_is_shown_instead_of_the_example(): void
    {
        $user = User::factory()->create();

        Ride::factory()->for($user)->create(['seq' => 1, 'lean_left_deg' => 11.0]);
        Ride::factory()->for($user)->create([
            'seq' => 2,
            'lean_left_deg' => 47.0,
            'lean_right_deg' => 44.5,
            'accel_g' => 0.91,
            'brake_g' => 0.83,
            'speed_kmh' => 164.0,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee(__('przykład'))
            ->assertSee('47°')
            ->assertSee('44,5°')
            ->assertSee('0,91 g')
            ->assertSee('164 km/h')
            // Starszy przejazd nie ma tu czego szukać.
            ->assertDontSee('11°');
    }

    /**
     * Bez GPS-a prędkość przychodzi pusta — na stronie głównej też ma być
     * kreską, a nie zerem.
     */
    public function test_a_missing_speed_is_shown_as_dashes(): void
    {
        $user = User::factory()->create();

        Ride::factory()->for($user)->create(['seq' => 1, 'speed_kmh' => null]);

        $this->get('/')
            ->assertOk()
            ->assertSee(Pomiar::BRAK)
            ->assertDontSee('0 km/h');
    }
}
