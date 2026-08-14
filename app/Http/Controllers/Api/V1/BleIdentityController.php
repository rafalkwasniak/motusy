<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\BleIdentity;
use App\Models\User;
use App\Services\BleIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BleIdentityController extends Controller
{
    public function __construct(private readonly BleIdentityService $identities) {}

    /**
     * The token this phone should broadcast over BLE.
     *
     * Rotates itself when the current token is old enough, so the app can simply ask
     * on every start and always get the right value.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success(__('api.fetched'), $this->payload($user, $this->identities->current($user)));
    }

    /**
     * Retire the current token and issue a new one, for a user who wants to stop
     * being recognisable by an identifier somebody may already have collected.
     *
     * The old token dies immediately here, unlike automatic rotation, which keeps it
     * resolvable for late reports. Detections somebody already collected stop
     * resolving, which is the whole promise of the button.
     *
     * The token belongs to the account, not to the phone, so this covers every device
     * signed in — and every one of them has to ask again before it broadcasts anything
     * anybody can still recognise.
     */
    public function rotate(Request $request): JsonResponse
    {
        $user = $request->user();

        $identity = $this->identities->rotate($user, config('motusy.ble.grace_after_manual_rotation'));

        return ApiResponse::success(__('api.ble_identity_rotated'), $this->payload($user, $identity));
    }

    /**
     * Every comment inside the returned array becomes a field description in the
     * public API contract, so the reasoning stays here instead.
     *
     * Both flags are computed on the server rather than derived from incognito on the
     * phone. Once breakdowns land, a rider with an active one will have to keep
     * broadcasting despite the mode, and that rule change must not wait for a release.
     *
     * Scanning follows the same switch: reports from an invisible rider are refused
     * anyway, so scanning would only burn battery.
     */
    private function payload(User $user, BleIdentity $identity): array
    {
        return [
            /** The rotating identifier, served from the GATT characteristic below. */
            'token' => $identity->token,

            /** The same for every user. Says a rider is nearby, never who. */
            'service_uuid' => config('motusy.ble.service_uuid'),

            /** The characteristic the token is read from after connecting. */
            'characteristic_uuid' => config('motusy.ble.characteristic_uuid'),

            /** When the token is due to be replaced. Ask again no earlier than this. */
            'refresh_after' => $identity->created_at
                ->addHours(config('motusy.ble.rotation_hours'))
                ->toIso8601String(),

            /** Whether the phone should advertise at all. False while invisible. */
            'should_broadcast' => ! $user->incognito,

            /** Whether the phone should scan at all. False while invisible. */
            'should_scan' => ! $user->incognito,
        ];
    }
}
