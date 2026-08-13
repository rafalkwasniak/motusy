<?php

namespace Tests\Feature\Api\V1;

use App\Models\Meeting;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\BleIdentityService;
use App\Services\MeetingService;
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

    public function test_single_sided_report_is_stored_but_reveals_nothing(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $response = $this->report($rafal, [$this->detection($marek)]);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.created', true)
            ->assertJsonPath('data.results.0.confirmed', false)
            ->assertJsonPath('data.results.0.meeting', null);

        $this->assertSame(1, Meeting::count());
    }

    public function test_single_sided_report_stays_out_of_the_history(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek)]);

        $this->actingAs($rafal, 'sanctum')
            ->getJson('/api/v1/meetings')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_second_report_confirms_the_meeting_for_both_sides(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek)]);

        // The phone that closes the pair learns who it was straight away.
        $this->report($marek, [$this->detection($rafal)])
            ->assertJsonPath('data.results.0.confirmed', true)
            ->assertJsonPath('data.results.0.meeting.user.nickname', 'Rafal');

        foreach ([[$rafal, 'Marek'], [$marek, 'Rafal']] as [$viewer, $expected]) {
            $this->actingAs($viewer, 'sanctum')
                ->getJson('/api/v1/meetings')
                ->assertJsonPath('data.0.user.nickname', $expected)
                ->assertJsonCount(1, 'data');
        }
    }

    public function test_each_side_keeps_its_own_position_and_time(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek, ['latitude' => 50.1, 'longitude' => 18.1])]);
        $this->report($marek, [$this->detection($rafal, ['latitude' => 50.2, 'longitude' => 18.2])]);

        $this->actingAs($rafal, 'sanctum')->getJson('/api/v1/meetings')
            ->assertJsonPath('data.0.latitude', 50.1);

        $this->actingAs($marek, 'sanctum')->getJson('/api/v1/meetings')
            ->assertJsonPath('data.0.latitude', 50.2);
    }

    public function test_cooldown_blocks_a_second_meeting_with_the_same_person(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek)]);

        $this->report($rafal, [$this->detection($marek)])
            ->assertJsonPath('data.results.0.created', false)
            ->assertJsonPath('data.results.0.reason', 'cooldown');

        $this->assertSame(1, Meeting::where('user_id', $rafal->id)->count());
    }

    public function test_meeting_again_after_the_cooldown_is_recorded(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek)]);

        $this->travel(config('motusy.meetings.cooldown_hours') + 1)->hours();

        $this->report($rafal, [$this->detection($marek)])
            ->assertJsonPath('data.results.0.created', true);

        $this->assertSame(2, Meeting::where('user_id', $rafal->id)->count());
    }

    /**
     * Reports held offline arrive in a burst. Measuring the cooldown from arrival
     * would let every one of them through as a separate encounter.
     */
    public function test_cooldown_is_measured_against_the_detection_time_not_the_upload_time(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $base = now()->subHours(3);

        $this->report($rafal, [
            $this->detection($marek, ['detected_at' => $base->toIso8601String()]),
            $this->detection($marek, ['detected_at' => $base->addMinutes(5)->toIso8601String()]),
            $this->detection($marek, ['detected_at' => $base->addMinutes(10)->toIso8601String()]),
        ])
            ->assertJsonPath('data.results.0.created', true)
            ->assertJsonPath('data.results.1.reason', 'cooldown')
            ->assertJsonPath('data.results.2.reason', 'cooldown');

        $this->assertSame(1, Meeting::where('user_id', $rafal->id)->count());
    }

    public function test_a_retry_of_the_same_event_does_not_create_a_second_meeting(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $detection = $this->detection($marek);

        $this->report($rafal, [$detection])->assertJsonPath('data.results.0.created', true);

        $this->report($rafal, [$detection])
            ->assertJsonPath('data.results.0.created', false)
            ->assertJsonPath('data.results.0.reason', 'duplicate');

        $this->assertSame(1, Meeting::where('user_id', $rafal->id)->count());
    }

    /**
     * The retry must still hand back the card, otherwise a lost response means a
     * meeting the user is never told about.
     */
    public function test_a_retry_returns_the_card_once_the_meeting_is_confirmed(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $detection = $this->detection($marek);

        $this->report($rafal, [$detection]);
        $this->report($marek, [$this->detection($rafal)]);

        $this->report($rafal, [$detection])
            ->assertJsonPath('data.results.0.reason', 'duplicate')
            ->assertJsonPath('data.results.0.confirmed', true)
            ->assertJsonPath('data.results.0.meeting.user.nickname', 'Marek');
    }

    public function test_detections_are_processed_in_one_batch(): void
    {
        $rafal = $this->rider('Rafal');
        $riders = collect(['Marek', 'Ania', 'Tomek'])->map(fn ($n) => $this->rider($n));

        $this->report($rafal, $riders->map(fn (User $r) => $this->detection($r))->all())
            ->assertStatus(200)
            ->assertJsonCount(3, 'data.results');

        $this->assertSame(3, Meeting::where('user_id', $rafal->id)->count());
    }

    public function test_unknown_token_is_reported_without_failing_the_batch(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [
            $this->detection($marek, ['ble_token' => str_repeat('a', 32)]),
            $this->detection($marek),
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.results.0.reason', 'unknown_token')
            ->assertJsonPath('data.results.1.created', true);
    }

    public function test_detection_older_than_the_limit_is_dropped(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $old = now()->subHours(config('motusy.meetings.max_report_age_hours') + 1);

        $this->report($rafal, [$this->detection($marek, ['detected_at' => $old->toIso8601String()])])
            ->assertJsonPath('data.results.0.reason', 'too_old');

        $this->assertSame(0, Meeting::count());
    }

    public function test_detection_from_the_future_is_rejected(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [
            $this->detection($marek, ['detected_at' => now()->addHours(2)->toIso8601String()]),
        ])->assertJsonPath('data.results.0.reason', 'invalid_time');
    }

    public function test_incognito_on_either_side_stops_the_meeting(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $marek->update(['incognito' => true]);
        $this->report($rafal, [$this->detection($marek)])
            ->assertJsonPath('data.results.0.reason', 'incognito');

        $marek->update(['incognito' => false]);
        $rafal->update(['incognito' => true]);
        $this->report($rafal->fresh(), [$this->detection($marek)])
            ->assertJsonPath('data.results.0.reason', 'incognito');

        $this->assertSame(0, Meeting::count());
    }

    public function test_own_token_does_not_create_a_meeting_with_yourself(): void
    {
        $rafal = $this->rider('Rafal');

        $this->report($rafal, [$this->detection($rafal)])
            ->assertJsonPath('data.results.0.reason', 'self');
    }

    public function test_hidden_fields_stay_hidden_in_the_meeting_card(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');
        $marek->profile->update(['phone' => '600100200', 'phone_visible' => false]);

        $this->report($rafal, [$this->detection($marek)]);
        $this->report($marek, [$this->detection($rafal)]);

        $this->actingAs($rafal, 'sanctum')->getJson('/api/v1/meetings')
            ->assertJsonPath('data.0.user.nickname', 'Marek')
            ->assertJsonPath('data.0.user.phone', null);
    }

    public function test_meeting_detail_is_readable_only_by_its_owner(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek)]);
        $this->report($marek, [$this->detection($rafal)]);

        $rafalsMeeting = Meeting::where('user_id', $rafal->id)->first();

        $this->actingAs($rafal, 'sanctum')
            ->getJson("/api/v1/meetings/{$rafalsMeeting->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.user.nickname', 'Marek');

        $this->actingAs($this->rider('Obcy'), 'sanctum')
            ->getJson("/api/v1/meetings/{$rafalsMeeting->id}")
            ->assertStatus(404);
    }

    public function test_unconfirmed_meeting_detail_is_not_readable(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');

        $this->report($rafal, [$this->detection($marek)]);

        $meeting = Meeting::where('user_id', $rafal->id)->first();

        $this->actingAs($rafal, 'sanctum')
            ->getJson("/api/v1/meetings/{$meeting->id}")
            ->assertStatus(404);
    }

    public function test_history_is_paginated_newest_first(): void
    {
        $rafal = $this->rider('Rafal');

        foreach (range(1, 3) as $i) {
            $other = $this->rider("Rider{$i}");
            $at = now()->subHours($i)->toIso8601String();
            $this->report($rafal, [$this->detection($other, ['detected_at' => $at])]);
            $this->report($other, [$this->detection($rafal, ['detected_at' => $at])]);
        }

        $response = $this->actingAs($rafal, 'sanctum')
            ->getJson('/api/v1/meetings?per_page=2')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('pagination.per_page', 2);

        $this->assertSame('Rider1', $response->json('data.0.user.nickname'));
    }

    public function test_empty_history_returns_an_empty_array(): void
    {
        $this->actingAs($this->rider('Rafal'), 'sanctum')
            ->getJson('/api/v1/meetings')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_batch_larger_than_the_limit_is_rejected(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');
        $tooMany = config('motusy.meetings.max_batch_size') + 1;

        $this->report($rafal, array_fill(0, $tooMany, $this->detection($marek)))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_pruning_removes_reports_the_other_side_never_matched(): void
    {
        $rafal = $this->rider('Rafal');
        $marek = $this->rider('Marek');
        $ania = $this->rider('Ania');

        $this->report($rafal, [$this->detection($marek)]);

        $this->report($rafal, [$this->detection($ania)]);
        $this->report($ania, [$this->detection($rafal)]);

        $this->travel(config('motusy.meetings.confirmation_window_minutes') + 1)->minutes();

        $this->assertSame(1, app(MeetingService::class)->pruneUnconfirmed());
        $this->assertSame(2, Meeting::count());
    }

    public function test_meeting_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/meetings', ['detections' => []])->assertStatus(401);
        $this->getJson('/api/v1/meetings')->assertStatus(401);
    }
}
