<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DevicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('devices.index'))->assertRedirect(route('login'));
    }

    public function test_a_device_without_a_name_falls_back_to_its_factory_id(): void
    {
        $user = User::factory()->create();
        Device::factory()->for($user)->withDeviceId('a1b2c3d4e5f6')->create(['name' => null]);

        Livewire::actingAs($user)
            ->test('pages::devices.index')
            ->assertSee('a1b2c3d4e5f6');
    }

    public function test_a_device_can_be_named(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->for($user)->create(['name' => null]);

        Livewire::actingAs($user)
            ->test('pages::devices.index')
            ->call('edit', $device->id)
            ->set('name', 'Ducati')
            ->call('save');

        $this->assertSame('Ducati', $device->fresh()->name);
    }

    public function test_clearing_the_name_restores_the_factory_id(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->for($user)->create(['name' => 'Ducati']);

        Livewire::actingAs($user)
            ->test('pages::devices.index')
            ->call('edit', $device->id)
            ->set('name', '')
            ->call('save');

        // Puste pole znaczy „wróć do fabrycznego identyfikatora”,
        // a nie „zapisz pusty ciąg”.
        $this->assertNull($device->fresh()->name);
        $this->assertSame($device->device_id, $device->fresh()->displayName());
    }

    public function test_someone_elses_device_cannot_be_renamed(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->for(User::factory())->create(['name' => 'Cudze']);

        try {
            Livewire::actingAs($user)
                ->test('pages::devices.index')
                ->call('edit', $device->id);

            $this->fail('Cudze urządzenie dało się otworzyć do edycji.');
        } catch (ModelNotFoundException) {
            // Oczekiwane: zapytanie idzie przez relację właściciela.
        }

        $this->assertSame('Cudze', $device->fresh()->name);
    }
}
