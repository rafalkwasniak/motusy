<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Guards the fix for a real incident: running the suite posted to the production
 * Discord channel, because tests throw on purpose and those exceptions get reported.
 */
class NoOutboundCallsInTestsTest extends TestCase
{
    public function test_alert_channel_is_disarmed_during_tests(): void
    {
        $this->assertEmpty(config('services.discord.webhook'));
    }

    public function test_an_unfaked_outbound_call_fails_loudly(): void
    {
        $this->expectException(\Illuminate\Http\Client\StrayRequestException::class);

        Http::get('https://example.com');
    }

    /**
     * The endpoint that first leaked: it throws so the 500 envelope can be checked.
     */
    public function test_a_deliberate_server_error_sends_nothing_outward(): void
    {
        Http::fake();

        \Illuminate\Support\Facades\Route::middleware('api')->get('/api/v1/testing/explode', function () {
            throw new \RuntimeException('celowy wyjatek testowy');
        });

        $this->getJson('/api/v1/testing/explode')->assertStatus(500);

        Http::assertNothingSent();
    }
}
