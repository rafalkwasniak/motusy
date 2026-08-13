<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
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
     */
    public function rotate(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success(__('api.ble_identity_rotated'), $this->payload($user, $this->identities->rotate($user)));
    }

    private function payload($user, $identity): array
    {
        return [
            'token' => $identity->token,
            'refresh_after' => $identity->created_at
                ->addHours(config('motusy.ble.rotation_hours'))
                ->toIso8601String(),

            // Incognito is enforced on the phone by not advertising at all, which also
            // saves battery. The server repeats the answer here so the app never has
            // to derive it, and refuses to record meetings anyway.
            'should_broadcast' => ! $user->incognito,
        ];
    }
}
