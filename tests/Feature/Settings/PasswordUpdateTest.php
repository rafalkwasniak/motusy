<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->set('current_password', 'password')
            ->set('password', 'NoweHaslo1')
            ->set('password_confirmation', 'NoweHaslo1')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('NoweHaslo1', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->set('current_password', 'zle-haslo')
            ->set('password', 'NoweHaslo1')
            ->set('password_confirmation', 'NoweHaslo1')
            ->call('updatePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    /**
     * Reguła ustalona przez Rafała: osiem znaków, wielka litera, cyfra.
     */
    public function test_a_too_simple_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->set('current_password', 'password')
            ->set('password', 'haslo123')
            ->set('password_confirmation', 'haslo123')
            ->call('updatePassword')
            ->assertHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}
