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
     * Każdy z pięciu kafli rekordów ma swój piktogram — komplet, bo brakująca
     * ikona w rzędzie rzuca się w oczy bardziej niż jej brak wszędzie.
     */
    public function test_every_record_tile_carries_its_icon(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create();
        Ride::factory()->for($user)->create(['device_id' => 'a1b2c3d4e5f6', 'seq' => 1]);

        $ekran = Livewire::actingAs($user)->test('pages::dashboard');

        foreach (['Przechył w lewo', 'Przechył w prawo', 'Przyspieszenie', 'Hamowanie', 'Prędkość maksymalna'] as $opis) {
            $ekran->assertSee('aria-label="'.$opis.'"', escape: false);
        }
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
            ->assertSeeInOrder(['Nr', 'Urządzenie', 'Czas', 'Lewo', 'Prawo', 'Przysp.', 'Ham.', 'Maks.'])
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
