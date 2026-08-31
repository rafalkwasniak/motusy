<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccountToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_is_issued_when_an_account_is_created(): void
    {
        $this->post(route('register.store'), [
            'nickname' => 'rafal',
            'email' => 'rafal@example.com',
            'password' => 'Motocykl1',
            'password_confirmation' => 'Motocykl1',
        ]);

        $user = User::firstWhere('email', 'rafal@example.com');

        $this->assertNotNull($user->api_token);
        $this->assertSame($user->api_token, AccountToken::normalize($user->api_token));
    }

    public function test_the_token_uses_an_alphabet_without_lookalike_characters(): void
    {
        foreach (range(1, 200) as $ignored) {
            $token = AccountToken::generate();

            $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $token);

            // Zero i O, jedynka oraz I i L mylą się przy przepisywaniu.
            $this->assertDoesNotMatchRegularExpression('/[01ILO]/', $token);
        }
    }

    public function test_the_token_is_shown_in_the_panel_and_does_not_disappear(): void
    {
        $user = User::factory()->create();
        $token = $user->api_token;

        Livewire::actingAs($user)
            ->test('pages::devices.index')
            ->assertSee($token)
            // Ponowne wejście pokazuje ten sam token — nie znika po odsłonie.
            ->assertSee($token);

        $this->assertSame($token, $user->fresh()->api_token);
    }

    public function test_a_new_token_can_be_issued_and_replaces_the_old_one(): void
    {
        $user = User::factory()->create();
        $stary = $user->api_token;

        Livewire::actingAs($user)
            ->test('pages::devices.index')
            ->call('regenerateToken');

        $nowy = $user->fresh()->api_token;

        $this->assertNotSame($stary, $nowy);
        $this->assertSame($nowy, AccountToken::normalize($nowy));
    }

    /**
     * Ktoś przepisujący token ręcznie nie powinien dostać odmowy za to,
     * że pominął myślniki albo napisał małymi literami.
     */
    public function test_normalisation_forgives_case_and_missing_dashes(): void
    {
        $this->assertSame('XFRS-34ST-YTS8', AccountToken::normalize('xfrs34styts8'));
        $this->assertSame('XFRS-34ST-YTS8', AccountToken::normalize('XFRS-34ST-YTS8'));
        $this->assertSame('XFRS-34ST-YTS8', AccountToken::normalize('  xfrs 34st yts8  '));
    }

    public function test_normalisation_rejects_wrong_length_and_forbidden_characters(): void
    {
        $this->assertNull(AccountToken::normalize('0FRS-34ST-YTS8'));
        $this->assertNull(AccountToken::normalize('XFRS-34ST'));
        $this->assertNull(AccountToken::normalize('XFRS-34ST-YTS8X'));
        $this->assertNull(AccountToken::normalize(null));
    }
}
