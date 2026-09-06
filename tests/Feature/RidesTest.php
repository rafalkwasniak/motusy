<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Ride;
use App\Models\RideTrack;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RidesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('rides.index'))->assertRedirect(route('login'));
    }

    public function test_rides_page_lists_rides_newest_first(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create();

        foreach ([1, 2, 3] as $seq) {
            Ride::factory()->for($user)->create([
                'device_id' => 'a1b2c3d4e5f6',
                'seq' => $seq,
            ]);
        }

        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->assertSeeInOrder(['#3', '#2', '#1']);
    }

    /**
     * Historia pokazuje wyłącznie przejazdy zalogowanego konta.
     *
     * Zawężenie robi relacja `User::rides()`, więc dziś dzieje się samo —
     * i właśnie dlatego warto je przypilnować testem. Przepisanie zapytania
     * na `Ride::query()` przy jakimś przyszłym filtrze zgubiłoby je bez
     * jednego błędu, a przejazdy obcych ludzi weszłyby do cudzej historii.
     */
    public function test_the_history_shows_only_the_rides_of_the_signed_in_account(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create();

        Ride::factory()->for($user)->create(['device_id' => 'a1b2c3d4e5f6', 'seq' => 11]);

        $obcy = User::factory()->create();
        Device::factory()->for($obcy)->withDeviceId('ffeeddccbbaa')->create();

        Ride::factory()->for($obcy)->create(['device_id' => 'ffeeddccbbaa', 'seq' => 22]);

        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->assertSee('#11')
            ->assertDontSee('#22');
    }

    public function test_missing_speed_is_shown_as_dashes_not_zero(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create();

        Ride::factory()->for($user)->create([
            'device_id' => 'a1b2c3d4e5f6',
            'seq' => 1,
            'speed_kmh' => null,
        ]);

        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->assertSee('———')
            ->assertDontSee('0 km/h');
    }

    public function test_speed_is_shown_when_the_device_measured_it(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create();

        Ride::factory()->for($user)->create([
            'device_id' => 'a1b2c3d4e5f6',
            'seq' => 1,
            'speed_kmh' => 187.0,
        ]);

        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->assertSee('187 km/h');
    }

    public function test_a_ride_can_be_deleted_but_only_softly(): void
    {
        $user = User::factory()->create();
        $ride = Ride::factory()->for($user)->create(['seq' => 1]);

        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->call('confirmDelete', $ride->id)
            ->call('deleteRide');

        // Miękko, bo twarde kasowanie sprawiłoby, że przejazd wróciłby
        // przy następnej wysyłce z urządzenia.
        $this->assertSoftDeleted($ride);
    }

    /**
     * Ikona śladu przy numerze przejazdu jest **znacznikiem**, nie odnośnikiem:
     * mówi, że ślad jest, a pobieranie GPX-a stoi na karcie przejazdu. Wiersz
     * w całości prowadzi do tej karty, więc drugi cel w nim tylko mnożyłby
     * możliwe kliknięcia.
     */
    public function test_a_ride_with_a_track_is_marked_but_not_downloadable_from_the_list(): void
    {
        $user = User::factory()->create();
        $ze = Ride::factory()->for($user)->create(['seq' => 2]);

        RideTrack::factory()->for($user)->create([
            'device_id' => $ze->device_id,
            'seq' => $ze->seq,
            'ride_id' => $ze->id,
        ]);

        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->assertSee(__('Ten przejazd ma ślad trasy'))
            ->assertDontSee(route('rides.track.gpx', $ze), escape: false);
    }

    /**
     * Przejazd bez śladu — a takich jest większość — nie pokazuje znacznika.
     */
    public function test_a_ride_without_a_track_is_not_marked(): void
    {
        $user = User::factory()->create();
        Ride::factory()->for($user)->create(['seq' => 1]);

        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->assertDontSee(__('Ten przejazd ma ślad trasy'));
    }

    /**
     * Ślad idzie do kosza razem z przejazdem — zostawiony pokazywałby trasę
     * jazdy, której właściciel już nie chce w historii.
     */
    public function test_deleting_a_ride_deletes_its_track(): void
    {
        $user = User::factory()->create();
        $ride = Ride::factory()->for($user)->create(['seq' => 1]);

        $track = RideTrack::factory()->for($user)->create([
            'device_id' => $ride->device_id,
            'seq' => $ride->seq,
            'ride_id' => $ride->id,
        ]);

        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->call('confirmDelete', $ride->id)
            ->call('deleteRide');

        $this->assertSoftDeleted($track);
    }

    public function test_a_ride_belonging_to_someone_else_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $ride = Ride::factory()->for(User::factory())->create(['seq' => 1]);

        try {
            Livewire::actingAs($user)
                ->test('pages::rides.index')
                ->call('confirmDelete', $ride->id)
                ->call('deleteRide');

            $this->fail('Cudzy przejazd dał się usunąć.');
        } catch (ModelNotFoundException) {
            // Oczekiwane: zapytanie idzie przez relację właściciela.
        }

        $this->assertNotSoftDeleted($ride);
    }

    public function test_rides_can_be_filtered_by_device(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('aaaaaaaaaaaa')->create();
        Device::factory()->for($user)->withDeviceId('bbbbbbbbbbbb')->create();

        Ride::factory()->for($user)->create(['device_id' => 'aaaaaaaaaaaa', 'seq' => 11]);
        Ride::factory()->for($user)->create(['device_id' => 'bbbbbbbbbbbb', 'seq' => 22]);

        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->set('device', 'aaaaaaaaaaaa')
            ->assertSee('#11')
            ->assertDontSee('#22');
    }

    public function test_the_history_is_paginated_by_ten(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create();

        foreach (range(101, 112) as $seq) {
            Ride::factory()->for($user)->create([
                'device_id' => 'a1b2c3d4e5f6',
                'seq' => $seq,
            ]);
        }

        // Pierwsza strona to dziesięć najwyższych numerów: 112 w dół do 103.
        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->assertSee('#112')
            ->assertSee('#103')
            ->assertDontSee('#102')
            ->assertDontSee('#101')
            // Podsumowanie po polsku. Sprawdzane tędy, a nie na samym
            // paginatorze, bo Livewire renderuje własny widok paginacji
            // (`livewire::tailwind`), a nie ten z Laravela.
            ->assertSee('Wyniki od')
            ->assertDontSee('Showing')
            ->assertDontSee('results');
    }

    public function test_each_ride_shows_the_device_that_recorded_it(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create(['name' => 'Ducati']);

        Ride::factory()->for($user)->create(['device_id' => 'a1b2c3d4e5f6', 'seq' => 1]);

        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->assertSee('Ducati');
    }

    public function test_a_ride_from_an_unknown_device_falls_back_to_its_identifier(): void
    {
        $user = User::factory()->create();

        // Przejazd bez wpisu w `devices` — nie powinien wywrócić widoku.
        Ride::factory()->for($user)->create(['device_id' => 'ffeeddccbbaa', 'seq' => 1]);

        Livewire::actingAs($user)
            ->test('pages::rides.index')
            ->assertSee('ffeeddccbbaa');
    }
}
