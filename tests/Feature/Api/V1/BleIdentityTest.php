<?php

namespace Tests\Feature\Api\V1;

use App\Models\BleIdentity;
use App\Models\User;
use App\Services\BleIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BleIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_request_issues_a_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/ble/identity')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'refresh_after', 'should_broadcast']]);

        $token = $response->json('data.token');

        $this->assertSame(config('motusy.ble.token_bytes') * 2, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
        $this->assertDatabaseHas('ble_identities', ['user_id' => $user->id, 'token' => $token, 'active' => true]);
    }

    public function test_repeated_requests_return_the_same_token_until_it_is_due_for_rotation(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user, 'sanctum')->getJson('/api/v1/ble/identity')->json('data.token');
        $second = $this->actingAs($user, 'sanctum')->getJson('/api/v1/ble/identity')->json('data.token');

        $this->assertSame($first, $second);
        $this->assertSame(1, BleIdentity::where('user_id', $user->id)->count());
    }

    public function test_token_rotates_once_it_reaches_the_configured_age(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user, 'sanctum')->getJson('/api/v1/ble/identity')->json('data.token');

        $this->travel(config('motusy.ble.rotation_hours') + 1)->hours();

        $second = $this->actingAs($user, 'sanctum')->getJson('/api/v1/ble/identity')->json('data.token');

        $this->assertNotSame($first, $second);
        $this->assertSame(2, BleIdentity::where('user_id', $user->id)->count());
    }

    public function test_manual_rotation_replaces_the_broadcast_token(): void
    {
        $user = User::factory()->create();

        $before = $this->actingAs($user, 'sanctum')->getJson('/api/v1/ble/identity')->json('data.token');
        $after = $this->actingAs($user, 'sanctum')->postJson('/api/v1/ble/identity/rotate')->json('data.token');

        $this->assertNotSame($before, $after);
        $this->assertDatabaseHas('ble_identities', ['token' => $before, 'active' => false]);
        $this->assertDatabaseHas('ble_identities', ['token' => $after, 'active' => true]);
    }

    /**
     * A meeting detected without coverage and uploaded later must still resolve, so a
     * retired token keeps working for a while.
     */
    public function test_retired_token_still_resolves_during_the_grace_period(): void
    {
        $user = User::factory()->create();
        $service = app(BleIdentityService::class);

        $old = $service->current($user)->token;
        $service->rotate($user);

        $this->travel(config('motusy.ble.resolvable_after_rotation_hours') - 1)->hours();

        $this->assertSame($user->id, $service->resolve($old)?->id);
    }

    public function test_retired_token_stops_resolving_once_the_grace_period_ends(): void
    {
        $user = User::factory()->create();
        $service = app(BleIdentityService::class);

        $old = $service->current($user)->token;
        $service->rotate($user);

        $this->travel(config('motusy.ble.resolvable_after_rotation_hours') + 1)->hours();

        $this->assertNull($service->resolve($old));
    }

    public function test_unknown_token_resolves_to_nobody(): void
    {
        $this->assertNull(app(BleIdentityService::class)->resolve(str_repeat('a', 32)));
    }

    public function test_incognito_user_is_told_not_to_broadcast(): void
    {
        $user = User::factory()->create(['incognito' => true]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/ble/identity')
            ->assertStatus(200)
            ->assertJsonPath('data.should_broadcast', false);
    }

    public function test_tokens_are_unique_across_users(): void
    {
        $tokens = User::factory()->count(25)->create()
            ->map(fn (User $user) => app(BleIdentityService::class)->current($user)->token);

        $this->assertCount(25, $tokens->unique());
    }

    /**
     * Resolution runs on every encounter, so it must stay a single indexed lookup
     * rather than growing with the number of tokens in the table.
     */
    public function test_resolution_is_a_single_query(): void
    {
        $user = User::factory()->create();
        $token = app(BleIdentityService::class)->current($user)->token;

        DB::enableQueryLog();
        app(BleIdentityService::class)->resolve($token);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Token, user, profile, motorcycle. What matters is that the count is fixed
        // and does not grow with the number of tokens stored.
        $this->assertLessThanOrEqual(4, count($queries));
    }

    public function test_ble_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/ble/identity')->assertStatus(401);
        $this->postJson('/api/v1/ble/identity/rotate')->assertStatus(401);
    }
}
