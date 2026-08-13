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
     * Retire the current token and issue a new one. Also exposed to the user as
     * "reset my identity" for anyone who feels followed.
     */
    public function rotate(User $user): BleIdentity
    {
        return DB::transaction(function () use ($user) {
            $user->bleIdentities()
                ->where('active', true)
                ->update([
                    'active' => false,
                    'expires_at' => now()->addHours(config('motusy.ble.resolvable_after_rotation_hours')),
                ]);

            return $this->issue($user);
        });
    }

    /**
     * Resolve a token seen over BLE back into the person broadcasting it.
     *
     * One indexed lookup on a unique column: this runs on every encounter, so it has
     * to stay cheap.
     */
    public function resolve(string $token): ?User
    {
        return BleIdentity::query()
            ->resolvable()
            ->where('token', $token)
            ->with(['user.profile', 'user.motorcycle'])
            ->first()?->user;
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
