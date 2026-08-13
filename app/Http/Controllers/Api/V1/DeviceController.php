<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Device\RegisterDeviceRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * Register the device or refresh what we know about it.
     *
     * An upsert keyed by device_id, so the app can call it on every start without
     * checking whether it registered before. Sending it again after a new sign-in
     * re-binds the device to the current access token.
     */
    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        $user = $request->user();

        $device = $user->devices()->updateOrCreate(
            ['device_id' => $request->string('device_id')->toString()],
            [
                ...$request->safe()->except('device_id'),
                'personal_access_token_id' => $user->currentAccessToken()?->getKey(),
                'active' => true,
                'last_seen_at' => now(),
            ],
        );

        return ApiResponse::success(__('api.device_registered'), [
            'id' => $device->id,
            'device_id' => $device->device_id,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'has_push_token' => $device->push_token !== null,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ]);
    }
}
