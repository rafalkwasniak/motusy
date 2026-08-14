<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MeetingService
{
    public function __construct(private readonly BleIdentityService $identities) {}

    /**
     * One result per detection, in the order they were sent.
     *
     * @return list<array{event_id: string, status: string, meeting: array|null}>
     */
    public function record(User $user, array $detections, ?string $platform = null): array
    {
        return array_map(
            fn (array $detection) => $this->recordOne($user, $detection, $platform),
            $detections,
        );
    }

    private function recordOne(User $user, array $detection, ?string $platform): array
    {
        $eventId = $detection['event_id'];

        // A retry after a lost response must not record anything twice, and must still
        // hand back the card so the app can show the notification it missed.
        $previous = $user->meetingReports()->where('event_id', $eventId)->first();

        if ($previous !== null) {
            return $this->result($eventId, 'duplicate', $previous->meeting, $user);
        }

        $detectedAt = CarbonImmutable::parse($detection['detected_at']);

        // Phone clocks drift, so a small amount of future is treated as now rather than
        // thrown away.
        if ($detectedAt->gt(now()->addMinutes(config('motusy.meetings.clock_tolerance_minutes')))) {
            return $this->result($eventId, 'invalid_time');
        }

        if ($detectedAt->lt(now()->subHours(config('motusy.meetings.max_report_age_hours')))) {
            return $this->result($eventId, 'too_old');
        }

        $identity = $this->identities->find($detection['ble_token']);

        if ($identity === null) {
            return $this->result($eventId, 'unknown_token');
        }

        if (! $identity->isResolvable()) {
            return $this->result($eventId, 'expired_token');
        }

        $metUser = $identity->user;

        if ($metUser->id === $user->id) {
            return $this->result($eventId, 'self');
        }

        // Invisible mode works both ways: such a rider is neither detected nor detects.
        if ($user->incognito || $metUser->incognito) {
            return $this->result($eventId, 'incognito');
        }

        return $this->attach($user, $metUser, $detection, $detectedAt, $platform);
    }

    /**
     * Both phones report the same encounter independently and often at the same moment
     * — two queues draining as coverage returns. Checking for an existing meeting and
     * inserting one are two steps, so without a lock both sides can look, both find
     * nothing, and both insert. The pair is the narrowest thing worth locking: reports
     * about unrelated riders never wait on each other.
     */
    private function attach(
        User $user,
        User $metUser,
        array $detection,
        CarbonImmutable $detectedAt,
        ?string $platform,
    ): array {
        [$a, $b] = Meeting::pair($user->id, $metUser->id);

        $lock = Cache::lock("meeting:{$a}:{$b}", 10);

        // Waiting beats failing: the other side is mid-insert and will be done in
        // milliseconds, and giving up here would create the duplicate we are avoiding.
        return $lock->block(5, function () use ($user, $metUser, $detection, $detectedAt, $platform, $a, $b) {
            $existing = $this->withinCooldown($a, $b, $detectedAt);

            $status = $existing === null ? 'created' : 'cooldown';

            $meeting = DB::transaction(function () use ($existing, $user, $metUser, $detection, $detectedAt, $platform, $a, $b) {
                $meeting = $existing ?? Meeting::create([
                    'user_a_id' => $a,
                    'user_b_id' => $b,
                    'detected_at' => $detectedAt,
                    'latitude' => $detection['latitude'],
                    'longitude' => $detection['longitude'],
                ]);

                MeetingReport::create([
                    'meeting_id' => $meeting->id,
                    'reporter_id' => $user->id,
                    'event_id' => $detection['event_id'],
                    'detected_at' => $detectedAt,
                    'latitude' => $detection['latitude'],
                    'longitude' => $detection['longitude'],
                    'rssi' => $detection['rssi'] ?? null,
                    'platform' => $platform,
                ]);

                return $meeting;
            });

            $meeting->setRelation($meeting->user_a_id === $user->id ? 'userB' : 'userA', $metUser);

            return $this->result($detection['event_id'], $status, $meeting, $user);
        });
    }

    /**
     * Measured against detected_at rather than the current time. Reports held offline
     * arrive in a burst, and measuring from arrival would let every one of them through
     * as if it were a separate encounter.
     *
     * The window reaches both ways because late reports arrive out of order.
     */
    private function withinCooldown(int $a, int $b, CarbonImmutable $detectedAt): ?Meeting
    {
        $cooldown = config('motusy.meetings.cooldown_hours');

        return Meeting::query()
            ->where('user_a_id', $a)
            ->where('user_b_id', $b)
            ->whereBetween('detected_at', [
                $detectedAt->subHours($cooldown),
                $detectedAt->addHours($cooldown),
            ])
            ->first();
    }

    /**
     * Which phone this report came from, for checking the assumption the whole redesign
     * rests on: that Android cannot see a backgrounded iPhone. Null when the app never
     * registered a device.
     */
    public function reportingPlatform(User $user, ?int $accessTokenId): ?string
    {
        if ($accessTokenId === null) {
            return null;
        }

        return Device::query()
            ->where('user_id', $user->id)
            ->where('personal_access_token_id', $accessTokenId)
            ->value('platform');
    }

    /**
     * Every status other than a network failure means the app can drop the detection
     * from its queue. The card comes back whenever there is a meeting to point at, so
     * the notification can name the rider instead of saying "somebody".
     */
    private function result(
        string $eventId,
        string $status,
        ?Meeting $meeting = null,
        ?User $viewer = null,
    ): array {
        return [
            'event_id' => $eventId,
            'status' => $status,
            'meeting' => $meeting !== null && $viewer !== null ? $meeting->card($viewer) : null,
        ];
    }
}
