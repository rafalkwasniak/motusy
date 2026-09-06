<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    /**
     * Każdy z sześciu kafli rekordów ma swój piktogram — komplet, bo brakująca
     * ikona w rzędzie rzuca się w oczy bardziej niż jej brak wszędzie.
     */
    public function test_every_record_tile_carries_its_icon(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create();
        Ride::factory()->for($user)->create(['device_id' => 'a1b2c3d4e5f6', 'seq' => 1]);

        $ekran = Livewire::actingAs($user)->test('pages::dashboard');

        foreach (['Przechył w lewo', 'Przechył w prawo', 'Przyspieszenie', 'Hamowanie', 'Prędkość maksymalna', 'Hałas'] as $opis) {
            $ekran->assertSee('aria-label="'.$opis.'"', escape: false);
        }
    }

    /**
     * Rekord hałasu liczy się tak samo jak rekord prędkości: MAX po całym
     * koncie, z pominięciem przejazdów sprzed mikrofonu. `null` nie może
     * wciągnąć rekordu do zera.
     */
    public function test_the_noise_record_is_the_highest_measurement_on_the_account(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create();

        Ride::factory()->for($user)->create(['device_id' => 'a1b2c3d4e5f6', 'seq' => 1]);
        Ride::factory()->for($user)->create(['device_id' => 'a1b2c3d4e5f6', 'seq' => 2, 'max_noise_db' => 96.2]);
        Ride::factory()->for($user)->create(['device_id' => 'a1b2c3d4e5f6', 'seq' => 3, 'max_noise_db' => 111.7]);

        Livewire::actingAs($user)
            ->test('pages::dashboard')
            ->assertSee('111,7 dB');
    }

    /**
     * Gdy rekord padł na pomiarze obciętym przez przetwornik, kafel mówi
     * „co najmniej tyle" — inaczej rekord konta udawałby dokładny
     * (docs/api-halas-implementacja-laravel.md §6).
     */
    public function test_a_clipped_noise_record_is_marked_as_at_least(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create();

        Ride::factory()->for($user)->create(['device_id' => 'a1b2c3d4e5f6', 'seq' => 1, 'max_noise_db' => 96.2]);
        Ride::factory()->for($user)->create([
            'device_id' => 'a1b2c3d4e5f6',
            'seq' => 2,
            'max_noise_db' => 126.4,
            'noise_clipped' => 4120,
        ]);

        Livewire::actingAs($user)
            ->test('pages::dashboard')
            ->assertSee('≥ 126,4 dB');
    }

    /**
     * Konto sprzed mikrofonu pokazuje kreskę, nie zero.
     */
    public function test_an_account_without_any_noise_measurement_shows_a_dash(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create();
        Ride::factory()->for($user)->create(['device_id' => 'a1b2c3d4e5f6', 'seq' => 1]);

        Livewire::actingAs($user)
            ->test('pages::dashboard')
            ->assertSee('———')
            ->assertDontSee('0,0 dB');
    }

    /**
     * Ostatnie przejazdy na pulpicie mają wyglądać dokładnie tak, jak pełna
     * historia — te same kolumny z tego samego komponentu, żeby dwa ekrany
     * nie rozjechały się przy pierwszej zmianie w jednym z nich.
     */
    public function test_latest_rides_use_the_same_table_as_the_full_history(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create(['name' => 'Ninja']);

        Ride::factory()->for($user)->create([
            'device_id' => 'a1b2c3d4e5f6',
            'seq' => 7,
        ]);

        Livewire::actingAs($user)
            ->test('pages::dashboard')
            ->assertSeeInOrder(['Nr', 'Urządzenie', 'Czas', 'Lewo', 'Prawo', 'Przysp.', 'Ham.', 'Maks.', 'Hałas'])
            ->assertSee('#7')
            ->assertSee('Ninja');
    }

    /**
     * Kasowanie zostaje w historii przejazdów — pulpit jest podglądem
     * i nie ma ani modalu z potwierdzeniem, ani metody, którą kosz woła.
     */
    public function test_the_dashboard_does_not_offer_deleting_rides(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create();

        Ride::factory()->for($user)->create([
            'device_id' => 'a1b2c3d4e5f6',
            'seq' => 1,
        ]);

        Livewire::actingAs($user)
            ->test('pages::dashboard')
            ->assertDontSee('confirmDelete');
    }
}
