<?php

namespace Tests\Feature;

use App\Services\DiscordErrorReporter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DiscordErrorReporterTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.com/api/webhooks/test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.discord.webhook' => self::WEBHOOK]);
        Cache::flush();
        Http::fake();
    }

    public function test_sends_an_embed_carrying_the_message_type_and_location(): void
    {
        app(DiscordErrorReporter::class)->report(new RuntimeException('coś poszło nie tak'));

        Http::assertSent(function (Request $request) {
            $embed = $request->data()['embeds'][0];
            $fields = collect($embed['fields'])->pluck('value', 'name');

            return $request->url() === self::WEBHOOK
                && str_contains($embed['description'], 'coś poszło nie tak')
                && $fields['Type'] === RuntimeException::class
                && str_contains($fields['Location'], 'DiscordErrorReporterTest.php');
        });
    }

    public function test_paths_are_relative_so_the_server_layout_is_not_published(): void
    {
        app(DiscordErrorReporter::class)->report(new RuntimeException('x'));

        Http::assertSent(function (Request $request) {
            return ! str_contains(json_encode($request->data()), base_path());
        });
    }

    /**
     * A broken deploy turns every request into the same exception. Without this the
     * channel drowns and the useful alert is lost among the copies.
     */
    public function test_the_same_error_is_only_reported_once_within_the_window(): void
    {
        $reporter = app(DiscordErrorReporter::class);

        // One exception object: the fingerprint is the throw site, which is exactly
        // what repeats when a deploy is broken.
        $repeated = new RuntimeException('powtarzalny');

        $reporter->report($repeated);
        $reporter->report($repeated);
        $reporter->report($repeated);

        Http::assertSentCount(1);
    }

    public function test_the_same_error_is_reported_again_once_the_window_passes(): void
    {
        $reporter = app(DiscordErrorReporter::class);

        $repeated = new RuntimeException('powtarzalny');

        $reporter->report($repeated);

        $this->travel(config('services.discord.repeat_minutes') + 1)->minutes();

        $reporter->report($repeated);

        Http::assertSentCount(2);
    }

    public function test_different_errors_are_not_silenced_by_each_other(): void
    {
        $reporter = app(DiscordErrorReporter::class);

        $reporter->report(new RuntimeException('pierwszy'));
        $reporter->report(new \LogicException('drugi'));

        Http::assertSentCount(2);
    }

    public function test_alert_uses_its_own_colour_and_carries_the_given_fields(): void
    {
        app(DiscordErrorReporter::class)->alert('Coś wymaga uwagi', 'Opis zdarzenia', ['Kto' => 'Rafal']);

        Http::assertSent(function (Request $request) {
            $embed = $request->data()['embeds'][0];

            return $embed['color'] === 0xE67E22
                && str_contains($embed['title'], 'Coś wymaga uwagi')
                && $embed['fields'][0] === ['name' => 'Kto', 'value' => 'Rafal'];
        });
    }

    public function test_nothing_is_sent_when_no_webhook_is_configured(): void
    {
        config(['services.discord.webhook' => null]);

        app(DiscordErrorReporter::class)->report(new RuntimeException('x'));

        Http::assertNothingSent();
    }

    /**
     * A failure to deliver must not travel back into the exception handler.
     */
    public function test_a_delivery_failure_is_swallowed(): void
    {
        Http::fake(fn () => throw new RuntimeException('discord nieosiągalny'));

        app(DiscordErrorReporter::class)->report(new RuntimeException('oryginalny'));

        $this->assertTrue(true);
    }

    /**
     * Otherwise every mistyped password would ring the alert channel.
     */
    public function test_a_wrong_password_does_not_reach_discord(): void
    {
        \App\Models\User::factory()->create([
            'email' => 'rafal@example.com',
            'password' => 'tajne-haslo-123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'rafal@example.com',
            'password' => 'zle-haslo-123',
        ])->assertStatus(401);

        Http::assertNothingSent();
    }

    public function test_validation_and_missing_pages_do_not_reach_discord(): void
    {
        $this->postJson('/api/v1/auth/register', ['email' => 'nie-email'])->assertStatus(422);
        $this->getJson('/api/v1/nie-ma-takiej-trasy')->assertStatus(404);
        $this->getJson('/api/v1/auth/me')->assertStatus(401);

        Http::assertNothingSent();
    }
}
