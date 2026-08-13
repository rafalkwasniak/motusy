<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MeetingService
{
    public function __construct(private readonly BleIdentityService $identities) {}

    /**
     * @return array<int, array<string, mixed>> one result per detection, in order
     */
    public function record(User $user, array $detections): array
    {
        return array_map(fn (array $detection) => $this->recordOne($user, $detection), $detections);
    }

    private function recordOne(User $user, array $detection): array
    {
        $eventId = $detection['event_id'];

        // A retry after a lost response must not create a second meeting, and must
        // still hand back the card so the app can show the notification it missed.
        $existing = $user->meetings()->where('event_id', $eventId)->first();

        if ($existing !== null) {
            return $this->result($eventId, false, 'duplicate', $existing, $user);
        }

        $detectedAt = CarbonImmutable::parse($detection['detected_at']);

        if ($detectedAt->isFuture()) {
            return $this->result($eventId, false, 'invalid_time');
        }

        if ($detectedAt->lt(now()->subHours(config('motusy.meetings.max_report_age_hours')))) {
            return $this->result($eventId, false, 'too_old');
        }

        $metUser = $this->identities->resolve($detection['ble_token']);

        if ($metUser === null) {
            return $this->result($eventId, false, 'unknown_token');
        }

        if ($metUser->id === $user->id) {
            return $this->result($eventId, false, 'self');
        }

        // Incognito works both ways: such a user is neither detected nor detects.
        if ($user->incognito || $metUser->incognito) {
            return $this->result($eventId, false, 'incognito');
        }

        if ($this->withinCooldown($user, $metUser, $detectedAt)) {
            return $this->result($eventId, false, 'cooldown');
        }

        $meeting = $this->create($user, $metUser, $detection, $detectedAt);

        return $this->result($eventId, true, null, $meeting, $user);
    }

    /**
     * Sweep away reports the other side never matched. They are invisible anyway, so
     * this only keeps the table from filling up with rows that can no longer pair:
     * once a detection is older than the confirmation window, nothing can confirm it.
     */
    public function pruneUnconfirmed(): int
    {
        return Meeting::query()
            ->where('confirmed', false)
            ->where('detected_at', '<', now()->subMinutes(config('motusy.meetings.confirmation_window_minutes')))
            ->delete();
    }

    /**
     * Compared against detected_at rather than the current time. Reports held offline
     * arrive in a burst, and measuring from arrival would let every one of them
     * through as if it were a separate encounter.
     *
     * The window reaches both ways because late reports can arrive out of order.
     */
    private function withinCooldown(User $user, User $metUser, CarbonImmutable $detectedAt): bool
    {
        $cooldown = config('motusy.meetings.cooldown_hours');

        return $user->meetings()
            ->where('met_user_id', $metUser->id)
            ->whereBetween('detected_at', [
                $detectedAt->subHours($cooldown),
                $detectedAt->addHours($cooldown),
            ])
            ->exists();
    }

    private function create(User $user, User $metUser, array $detection, CarbonImmutable $detectedAt): Meeting
    {
        return DB::transaction(function () use ($user, $metUser, $detection, $detectedAt) {
            $meeting = $user->meetings()->create([
                'met_user_id' => $metUser->id,
                'latitude' => $detection['latitude'],
                'longitude' => $detection['longitude'],
                'detected_at' => $detectedAt,
                'event_id' => $detection['event_id'],
            ]);

            $this->confirmAgainstMirror($meeting, $metUser, $detectedAt);

            return $meeting->load('metUser.profile', 'metUser.motorcycle');
        });
    }

    /**
     * A meeting counts only once both phones have reported it. Until then the row
     * exists solely so the other side has something to pair with — it is hidden from
     * the history and never returned to the client.
     */
    private function confirmAgainstMirror(Meeting $meeting, User $metUser, CarbonImmutable $detectedAt): void
    {
        $window = config('motusy.meetings.confirmation_window_minutes');

        $mirror = $metUser->meetings()
            ->where('met_user_id', $meeting->user_id)
            ->whereBetween('detected_at', [
                $detectedAt->subMinutes($window),
                $detectedAt->addMinutes($window),
            ])
            ->first();

        if ($mirror === null) {
            return;
        }

        $mirror->update(['confirmed' => true]);
        $meeting->update(['confirmed' => true]);
    }

    /**
     * The card is handed back only for a confirmed meeting. That is what makes the
     * notification honest — the app never announces somebody the history will not
     * show. It also means a fabricated report reveals nothing: without the other
     * phone independently reporting the same encounter, the token stays anonymous.
     */
    private function result(
        string $eventId,
        bool $created,
        ?string $reason = null,
        ?Meeting $meeting = null,
        ?User $viewer = null,
    ): array {
        $confirmed = (bool) $meeting?->confirmed;

        return [
            'event_id' => $eventId,
            'created' => $created,
            'confirmed' => $confirmed,
            'reason' => $reason,
            'meeting' => $confirmed && $viewer !== null ? $meeting->card($viewer) : null,
        ];
    }
}
