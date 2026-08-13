<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Requests\Api\V1\Profile\UploadAvatarRequest;
use App\Http\Responses\ApiResponse;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * Replace the avatar. Sending a new file overwrites the previous one, which is
     * deleted from storage.
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile === null) {
            return ApiResponse::error('PROFILE_REQUIRED', __('api.profile_required'), null, 409);
        }

        $this->profiles->storeAvatar($user->profile, $request->file('avatar'));

        return ApiResponse::success(__('api.avatar_saved'), $user->fresh()->account());
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile === null) {
            return ApiResponse::error('PROFILE_REQUIRED', __('api.profile_required'), null, 409);
        }

        $this->profiles->removeAvatar($user->profile);

        return ApiResponse::success(__('api.avatar_removed'), $user->fresh()->account());
    }
}
