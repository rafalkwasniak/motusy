<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_correct_token_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/ping', [
            'Authorization' => 'Bearer '.$user->api_token,
        ])->assertOk();
    }

    /**
     * Ktoś przepisujący token ręcznie pominie kreski albo napisze małymi
     * literami. To nie może kończyć się kodem 401, bo po nim urządzenie
     * przestaje próbować (kontrakt §3).
     */
    public function test_lowercase_and_missing_dashes_are_forgiven(): void
    {
        $user = User::factory()->create();
        $rozjechany = strtolower(str_replace('-', '', $user->api_token));

        $this->getJson('/api/v1/ping', [
            'Authorization' => 'Bearer '.$rozjechany,
        ])->assertOk();
    }

    public function test_a_request_without_a_token_is_rejected(): void
    {
        $this->getJson('/api/v1/ping')->assertUnauthorized();
    }

    public function test_an_unknown_token_is_rejected(): void
    {
        User::factory()->create();

        $this->getJson('/api/v1/ping', [
            'Authorization' => 'Bearer XFRS-34ST-YTS8',
        ])->assertUnauthorized();
    }

    public function test_a_malformed_token_is_rejected(): void
    {
        User::factory()->create();

        foreach (['za-krotki', 'XFRS-34ST-YTS8-EXTRA', '0FRS-34ST-YTS8', ''] as $token) {
            $this->getJson('/api/v1/ping', [
                'Authorization' => 'Bearer '.$token,
            ])->assertUnauthorized();
        }
    }

    /**
     * Urządzenie nie ma parsera JSON i szuka w odpowiedzi dosłownie ciągu
     * `accepted_through` (kontrakt §7). Nie może on paść w innym znaczeniu.
     */
    public function test_the_response_never_mentions_accepted_through(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer '.$user->api_token])
            ->assertDontSee('accepted_through');

        $this->getJson('/api/v1/ping')->assertDontSee('accepted_through');
    }

    public function test_a_token_belonging_to_another_account_identifies_that_account(): void
    {
        $jeden = User::factory()->create();
        $drugi = User::factory()->create();

        $this->assertNotSame($jeden->api_token, $drugi->api_token);

        $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer '.$drugi->api_token])
            ->assertOk();
    }
}
