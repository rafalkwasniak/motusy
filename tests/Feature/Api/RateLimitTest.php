<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const LIMIT = 60;

    public function test_a_device_sending_normally_is_never_throttled(): void
    {
        $user = User::factory()->create();

        // Pudełko wysyła po zakończonej jeździe, więc kilkanaście żądań
        // na minutę to i tak dużo więcej, niż potrzebuje.
        foreach (range(1, 20) as $ignored) {
            $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer '.$user->api_token])
                ->assertOk();
        }
    }

    public function test_too_many_requests_are_answered_with_429(): void
    {
        $user = User::factory()->create();

        foreach (range(1, self::LIMIT) as $ignored) {
            $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer '.$user->api_token])
                ->assertOk();
        }

        $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer '.$user->api_token])
            ->assertStatus(429);
    }

    /**
     * Ogranicznik stoi przed sprawdzeniem tokena, więc obejmuje też
     * nieudane próby. Bez tego przemiatanie tokenów byłoby nielimitowane.
     */
    public function test_failed_attempts_count_towards_the_limit(): void
    {
        $user = User::factory()->create();

        foreach (range(1, self::LIMIT) as $ignored) {
            $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer XFRS-34ST-YTS8'])
                ->assertUnauthorized();
        }

        // Poprawny token też dostaje 429 — limit jest po adresie, nie po koncie.
        $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer '.$user->api_token])
            ->assertStatus(429);
    }

    public function test_the_rides_endpoint_shares_the_same_limit(): void
    {
        $user = User::factory()->create();

        foreach (range(1, self::LIMIT) as $ignored) {
            $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer '.$user->api_token]);
        }

        $this->postJson('/api/v1/rides', [
            'device_id' => 'a1b2c3d4e5f6',
            'fw' => '1.0.0',
            'calibrated' => true,
            'rides' => [],
        ], ['Authorization' => 'Bearer '.$user->api_token])->assertStatus(429);
    }

    /**
     * Urządzenie nie ma parsera JSON i szuka w odpowiedzi dosłownie ciągu
     * `accepted_through`; przy 429 nie może go zobaczyć, bo uznałoby
     * odrzucone żądanie za potwierdzenie.
     */
    public function test_the_throttled_response_never_mentions_accepted_through(): void
    {
        $user = User::factory()->create();

        foreach (range(1, self::LIMIT) as $ignored) {
            $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer '.$user->api_token]);
        }

        $odpowiedz = $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer '.$user->api_token]);

        $odpowiedz->assertStatus(429)->assertDontSee('accepted_through');

        // Kontrakt §3: po 429 urządzenie ponawia z opóźnieniem, więc musi
        // wiedzieć, ile czekać.
        $odpowiedz->assertHeader('Retry-After');
    }
}
