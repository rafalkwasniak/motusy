<?php

namespace App\Services;

use App\Models\BleIdentity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BleIdentityService
{
    /**
     * The token the phone should broadcast right now.
     *
     * Rotation policy lives here rather than in the app: the client just asks and
     * always gets the right answer, so changing the interval needs no app release.
     *
     * It is also lazy — the token turns over when somebody asks, not on a schedule.
     * A phone out of coverage asks for nothing, so its token stays valid instead of
     * expiring underneath it while it keeps broadcasting.
     */
    public function current(User $user): BleIdentity
    {
        $identity = $user->bleIdentities()->where('active', true)->first();

        if ($identity === null) {
            return $this->issue($user);
        }

        $rotateAfter = $identity->created_at->addHours(config('motusy.ble.rotation_hours'));

        return $rotateAfter->isPast() ? $this->rotate($user) : $identity;
    }

    /**
     * Retire the current token and issue a new one.
     *
     * $grace separates the two callers. Automatic rotation keeps the old token
     * resolvable for a while, so meetings recorded offline still find their owner.
     * A rotation the user asked for is a privacy action — "stop being recognisable" —
     * and takes effect at once. Reports already in flight are lost, which is exactly
     * what the button promises.
     */
    public function rotate(User $user, bool $grace = true): BleIdentity
    {
        return DB::transaction(function () use ($user, $grace) {
            $user->bleIdentities()
                ->where('active', true)
                ->update([
                    'active' => false,
                    'expires_at' => $grace
                        ? now()->addHours(config('motusy.ble.resolvable_after_rotation_hours'))
                        : now(),
                ]);

            return $this->issue($user);
        });
    }

    /**
     * Look a token up without judging whether it still counts.
     *
     * The caller needs the difference: a token nobody ever had is somebody feeding the
     * endpoint noise, while one that simply aged out is an honest report that waited
     * too long. Same answer for the app, different story for us.
     */
    public function find(string $token): ?BleIdentity
    {
        return BleIdentity::query()
            ->where('token', $token)
            ->with(['user.profile', 'user.motorcycle'])
            ->first();
    }

    /**
     * Resolve a token seen over BLE back into the person broadcasting it.
     *
     * One indexed lookup on a unique column: this runs on every encounter, so it has
     * to stay cheap.
     */
    public function resolve(string $token): ?User
    {
        $identity = $this->find($token);

        return $identity?->isResolvable() ? $identity->user : null;
    }

    /**
     * Retired tokens are kept only as long as a late report could still name them.
     * Nothing used to remove them, which with daily rotation left a row per user per
     * day for good.
     */
    public function pruneRetired(): int
    {
        return BleIdentity::query()
            ->where('active', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->subDays(config('motusy.ble.prune_retired_after_days')))
            ->delete();
    }

    private function issue(User $user): BleIdentity
    {
        return $user->bleIdentities()->create([
            'token' => bin2hex(random_bytes(config('motusy.ble.token_bytes'))),
            'active' => true,
            'expires_at' => null,
        ]);
    }
}
