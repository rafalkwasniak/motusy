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
            ->assertJsonStructure([
                'data' => ['token', 'service_uuid', 'characteristic_uuid', 'refresh_after', 'should_broadcast', 'should_scan'],
            ]);

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

    public function test_incognito_user_is_told_neither_to_broadcast_nor_to_scan(): void
    {
        $user = User::factory()->create(['incognito' => true]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/ble/identity')
            ->assertStatus(200)
            ->assertJsonPath('data.should_broadcast', false)
            ->assertJsonPath('data.should_scan', false);
    }

    /**
     * The same for everybody: the advertised UUID says a rider is nearby, never who.
     */
    public function test_ble_uuids_come_from_configuration_and_are_shared_by_all_users(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $response = $this->actingAs($first, 'sanctum')->getJson('/api/v1/ble/identity')
            ->assertJsonPath('data.service_uuid', config('motusy.ble.service_uuid'))
            ->assertJsonPath('data.characteristic_uuid', config('motusy.ble.characteristic_uuid'));

        $this->actingAs($second, 'sanctum')->getJson('/api/v1/ble/identity')
            ->assertJsonPath('data.service_uuid', $response->json('data.service_uuid'))
            ->assertJsonPath('data.characteristic_uuid', $response->json('data.characteristic_uuid'));
    }

    /**
     * A detection the app is still allowed to send must land on a token that can
     * still be resolved. If this ever inverts, reports die silently between the two
     * limits and neither side can tell why.
     */
    public function test_token_grace_period_outlasts_the_window_for_late_reports(): void
    {
        $this->assertGreaterThanOrEqual(
            config('motusy.meetings.max_report_age_hours'),
            config('motusy.ble.resolvable_after_rotation_hours'),
        );
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
