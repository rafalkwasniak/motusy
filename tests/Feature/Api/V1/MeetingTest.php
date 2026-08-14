<?php

namespace Tests\Feature\Api\V1;

use App\Models\Device;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\BleIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    private function rider(string $nickname): User
    {
        $user = User::factory()->create();
        UserProfile::factory()->for($user)->create(['nickname' => $nickname]);

        return $user;
    }

    private function tokenOf(User $user): string
    {
        return app(BleIdentityService::class)->current($user)->token;
    }

    private function detection(User $seen, array $overrides = []): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'ble_token' => $this->tokenOf($seen),
            'latitude' => 50.4201,
            'longitude' => 18.9273,
            'detected_at' => now()->toIso8601String(),
            ...$overrides,
        ];
    }

    private function report(User $reporter, array $detections)
    {
        return $this->actingAs($reporter, 'sanctum')
            ->postJson('/api/v1/meetings', ['detections' => $detections]);
    }

    /**
     * The rule the whole redesign turns on: one phone is enough. Waiting for both would
     * drop every iOS-Android pair, because a backgrounded iPhone is invisible to
     * Android and only one direction ever detects anything.
     */
    public function test_one_report_records_the_meeting_and_returns_the_card(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek)])
            ->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'created')
            ->assertJsonPath('data.results.0.meeting.user.nickname', 'Marek');

        $this->assertSame(1, Meeting::count());
    }

    public function test_the_side_that_never_reported_still_sees_the_meeting(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek)]);

        $this->actingAs($marek, 'sanctum')
            ->getJson('/api/v1/meetings')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.nickname', 'Rafal');
    }

    public function test_both_sides_reporting_the_same_encounter_produce_one_meeting(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek)])
            ->assertJsonPath('data.results.0.status', 'created');

        $this->report($marek, [$this->detection($rafal)])
            ->assertJsonPath('data.results.0.status', 'cooldown')
            ->assertJsonPath('data.results.0.meeting.user.nickname', 'Rafal');

        $this->assertSame(1, Meeting::count());
        $this->assertSame(2, MeetingReport::count());
    }

    /**
     * The pair is stored unordered, so the same encounter has to be found whichever
     * rider reports it first.
     */
    public function test_the_pair_is_normalised_so_order_of_reporting_does_not_matter(): void
    {
        $first = $this->rider('Pierwszy');
        $second = $this->rider('Drugi');

        $this->report($second, [$this->detection($first)]);
        $this->report($first, [$this->detection($second)]);

        $meeting = Meeting::sole();

        $this->assertSame(min($first->id, $second->id), $meeting->user_a_id);
        $this->assertSame(max($first->id, $second->id), $meeting->user_b_id);
    }

    public function test_each_report_keeps_its_own_position_and_time(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek, ['latitude' => 50.1, 'longitude' => 18.1])]);
        $this->report($marek, [$this->detection($rafal, ['latitude' => 50.2, 'longitude' => 18.2])]);

        $this->assertEqualsWithDelta(50.1, MeetingReport::where('reporter_id', $rafal->id)->value('latitude'), 0.0001);
        $this->assertEqualsWithDelta(50.2, MeetingReport::where('reporter_id', $marek->id)->value('latitude'), 0.0001);
    }

    /**
     * Rafał's commute: passing the same rider on the way to work and again two hours
     * later is one encounter, not two.
     */
    public function test_passing_the_same_rider_again_within_the_cooldown_is_one_meeting(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek)]);

        $this->travel(2)->hours();

        $this->report($rafal, [$this->detection($marek)])
            ->assertJsonPath('data.results.0.status', 'cooldown');

        $this->assertSame(1, Meeting::count());
    }

    public function test_meeting_again_after_the_cooldown_is_a_new_meeting(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek)]);

        $this->travel(config('motusy.meetings.cooldown_hours') + 1)->hours();

        $this->report($rafal, [$this->detection($marek)])
            ->assertJsonPath('data.results.0.status', 'created');

        $this->assertSame(2, Meeting::count());
    }

    /**
     * Reports held offline arrive in a burst. Measuring the cooldown from arrival would
     * let all of them through as separate encounters.
     */
    public function test_cooldown_is_measured_against_the_detection_time_not_the_upload_time(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $detectedAt = now()->subHours(3);

        $this->report($rafal, [
            $this->detection($marek, ['detected_at' => $detectedAt->toIso8601String()]),
            $this->detection($marek, ['detected_at' => $detectedAt->copy()->addMinutes(20)->toIso8601String()]),
        ]);

        $this->assertSame(1, Meeting::count());
    }

    public function test_a_retry_of_the_same_event_returns_the_original_outcome(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $detection = $this->detection($marek);

        $this->report($rafal, [$detection])->assertJsonPath('data.results.0.status', 'created');

        $this->report($rafal, [$detection])
            ->assertJsonPath('data.results.0.status', 'duplicate')
            ->assertJsonPath('data.results.0.meeting.user.nickname', 'Marek');

        $this->assertSame(1, Meeting::count());
        $this->assertSame(1, MeetingReport::count());
    }

    /**
     * The two sides generate different event ids for the same encounter, so idempotency
     * is per reporter — one rider's id must never block the other's report.
     */
    public function test_the_same_event_id_from_two_riders_is_not_a_duplicate(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $shared = 'evt_001';

        $this->report($rafal, [$this->detection($marek, ['event_id' => $shared])]);
        $this->report($marek, [$this->detection($rafal, ['event_id' => $shared])])
            ->assertJsonPath('data.results.0.status', 'cooldown');

        $this->assertSame(2, MeetingReport::count());
    }

    public function test_detections_are_processed_in_one_batch(): void
    {
        $rafal = $this->rider('Rafal');
        $riders = collect(['A', 'B', 'C'])->map(fn (string $n) => $this->rider($n));

        $this->report($rafal, $riders->map(fn (User $r) => $this->detection($r))->all())
            ->assertStatus(200)
            ->assertJsonCount(3, 'data.results')
            ->assertJsonPath('data.results.2.status', 'created');

        $this->assertSame(3, Meeting::count());
    }

    public function test_unknown_token_is_reported_without_failing_the_batch(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [
            $this->detection($marek, ['ble_token' => str_repeat('a', 32)]),
            $this->detection($marek),
        ])
            ->assertJsonPath('data.results.0.status', 'unknown_token')
            ->assertJsonPath('data.results.0.meeting', null)
            ->assertJsonPath('data.results.1.status', 'created');
    }

    /**
     * A token that aged out is an honest report that waited too long; one that never
     * existed is noise. Same answer for the app, different story for us.
     */
    public function test_expired_token_is_told_apart_from_an_unknown_one(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $old = $this->tokenOf($marek);
        app(BleIdentityService::class)->rotate($marek);

        $this->travel(config('motusy.ble.resolvable_after_rotation_hours') + 1)->hours();

        $this->report($rafal, [$this->detection($marek, ['ble_token' => $old])])
            ->assertJsonPath('data.results.0.status', 'expired_token');
    }

    public function test_retired_token_still_works_inside_the_grace_period(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $old = $this->tokenOf($marek);
        app(BleIdentityService::class)->rotate($marek);

        $this->report($rafal, [$this->detection($marek, ['ble_token' => $old])])
            ->assertJsonPath('data.results.0.status', 'created');
    }

    /**
     * "Reset my identity" has to bite at once. Losing reports still in flight is the
     * point, not a side effect.
     */
    public function test_manual_rotation_stops_the_old_token_from_resolving(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $old = $this->tokenOf($marek);

        $this->actingAs($marek, 'sanctum')->postJson('/api/v1/ble/identity/rotate')->assertStatus(200);

        $this->report($rafal, [$this->detection($marek, ['ble_token' => $old])])
            ->assertJsonPath('data.results.0.status', 'expired_token');
    }

    public function test_detection_older_than_the_limit_is_dropped(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $tooOld = now()->subHours(config('motusy.meetings.max_report_age_hours') + 1);

        $this->report($rafal, [$this->detection($marek, ['detected_at' => $tooOld->toIso8601String()])])
            ->assertJsonPath('data.results.0.status', 'too_old');

        $this->assertSame(0, Meeting::count());
    }

    /**
     * Phone clocks drift, and a couple of minutes of it must not cost a meeting.
     */
    public function test_a_slightly_fast_clock_is_tolerated(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $slightlyAhead = now()->addMinutes(config('motusy.meetings.clock_tolerance_minutes') - 1);

        $this->report($rafal, [$this->detection($marek, ['detected_at' => $slightlyAhead->toIso8601String()])])
            ->assertJsonPath('data.results.0.status', 'created');
    }

    public function test_detection_far_in_the_future_is_rejected(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek, ['detected_at' => now()->addDay()->toIso8601String()])])
            ->assertJsonPath('data.results.0.status', 'invalid_time');

        $this->assertSame(0, Meeting::count());
    }

    public function test_incognito_on_either_side_stops_the_meeting(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $marek->update(['incognito' => true]);

        $this->report($rafal, [$this->detection($marek)])
            ->assertJsonPath('data.results.0.status', 'incognito');

        $marek->update(['incognito' => false]);
        $rafal->update(['incognito' => true]);

        $this->report($rafal, [$this->detection($marek)])
            ->assertJsonPath('data.results.0.status', 'incognito');

        $this->assertSame(0, Meeting::count());
    }

    public function test_own_token_does_not_create_a_meeting_with_yourself(): void
    {
        $rafal = $this->rider('Rafal');

        $this->report($rafal, [$this->detection($rafal)])
            ->assertJsonPath('data.results.0.status', 'self');

        $this->assertSame(0, Meeting::count());
    }

    public function test_reporting_platform_is_recorded_from_the_signed_in_device(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $token = $rafal->createToken('iPhone');
        $rafal->devices()->create([
            'device_id' => 'abc',
            'platform' => 'ios',
            'personal_access_token_id' => $token->accessToken->id,
        ]);

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/meetings', ['detections' => [$this->detection($marek)]])
            ->assertStatus(200);

        $this->assertSame('ios', MeetingReport::where('reporter_id', $rafal->id)->value('platform'));
    }

    public function test_signal_strength_is_stored_when_sent(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek, ['rssi' => -67])])->assertStatus(200);

        $this->assertSame(-67, MeetingReport::where('reporter_id', $rafal->id)->value('rssi'));
    }

    public function test_hidden_fields_stay_hidden_in_the_meeting_card(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $marek->profile->update([
            'first_name' => 'Marek',
            'first_name_visible' => false,
            'phone' => '600100200',
            'phone_visible' => true,
        ]);

        $this->report($rafal, [$this->detection($marek)])
            ->assertJsonPath('data.results.0.meeting.user.first_name', null)
            ->assertJsonPath('data.results.0.meeting.user.phone', '600100200');
    }

    public function test_meeting_detail_is_readable_by_both_participants_only(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');
        $obcy = $this->rider('Obcy');

        $id = $this->report($rafal, [$this->detection($marek)])->json('data.results.0.meeting.id');

        $this->actingAs($rafal, 'sanctum')->getJson("/api/v1/meetings/{$id}")
            ->assertStatus(200)
            ->assertJsonPath('data.user.nickname', 'Marek');

        $this->actingAs($marek, 'sanctum')->getJson("/api/v1/meetings/{$id}")
            ->assertStatus(200)
            ->assertJsonPath('data.user.nickname', 'Rafal');

        $this->actingAs($obcy, 'sanctum')->getJson("/api/v1/meetings/{$id}")
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_history_is_paginated_newest_first(): void
    {
        $rafal = $this->rider('Rafal');

        foreach (['A', 'B', 'C'] as $index => $nickname) {
            $this->report($rafal, [$this->detection(
                $this->rider($nickname),
                ['detected_at' => now()->subHours($index + 1)->toIso8601String()],
            )]);
        }

        $this->actingAs($rafal, 'sanctum')
            ->getJson('/api/v1/meetings?per_page=2')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.user.nickname', 'A')
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('pagination.last_page', 2);
    }

    public function test_empty_history_returns_an_empty_array(): void
    {
        $this->actingAs($this->rider('Rafal'), 'sanctum')
            ->getJson('/api/v1/meetings')
            ->assertStatus(200)
            ->assertExactJsonStructure(['success', 'message', 'data', 'pagination'])
            ->assertJsonPath('data', []);
    }

    public function test_batch_larger_than_the_limit_is_rejected(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $tooMany = array_fill(0, config('motusy.meetings.max_batch_size') + 1, $this->detection($marek));

        $this->report($rafal, $tooMany)
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_reporting_is_rate_limited_per_user(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');
        $inny = $this->rider('Inny');

        $attempts = config('motusy.meetings.throttle.attempts');

        for ($i = 0; $i < $attempts; $i++) {
            $this->report($rafal, [$this->detection($marek)])->assertStatus(200);
        }

        $this->report($rafal, [$this->detection($marek)])
            ->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_REQUESTS')
            ->assertHeader('Retry-After');

        // Keyed by account: one rider hitting the limit must not lock out everybody
        // behind the same carrier NAT.
        $this->report($inny, [$this->detection($marek)])->assertStatus(200);
    }

    public function test_meeting_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/meetings', ['detections' => []])->assertStatus(401);
        $this->getJson('/api/v1/meetings')->assertStatus(401);
        $this->getJson('/api/v1/meetings/1')->assertStatus(401);
    }
}
