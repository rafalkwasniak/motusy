<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateMotorcycleRequest;
use App\Http\Requests\Api\V1\Profile\UploadMotorcyclePhotoRequest;
use App\Http\Responses\ApiResponse;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MotorcycleController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    /**
     * Create or update the signed-in user's motorcycle. One per account in the MVP.
     */
    public function update(UpdateMotorcycleRequest $request): JsonResponse
    {
        $user = $request->user();

        $this->profiles->saveMotorcycle($user, $request->validated());

        return ApiResponse::success(__('api.motorcycle_saved'), $user->account());
    }

    public function uploadPhoto(UploadMotorcyclePhotoRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->motorcycle === null) {
            return ApiResponse::error('MOTORCYCLE_REQUIRED', __('api.motorcycle_required'), null, 409);
        }

        $this->profiles->storeMotorcyclePhoto($user->motorcycle, $request->file('photo'));

        return ApiResponse::success(__('api.motorcycle_photo_saved'), $user->fresh()->account());
    }

    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->motorcycle === null) {
            return ApiResponse::error('MOTORCYCLE_REQUIRED', __('api.motorcycle_required'), null, 409);
        }

        $this->profiles->removeMotorcyclePhoto($user->motorcycle);

        return ApiResponse::success(__('api.motorcycle_photo_removed'), $user->fresh()->account());
    }
}
