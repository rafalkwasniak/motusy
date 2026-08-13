<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Responses\ApiResponse;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    /**
     * Create or update the signed-in user's profile.
     *
     * Acts on the authenticated account only — there is no way to address somebody
     * else's profile through this endpoint.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $this->profiles->saveProfile($user, $request->validated());

        return ApiResponse::success(__('api.profile_saved'), $user->account());
    }
}
