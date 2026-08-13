<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateMotorcycleRequest;
use App\Http\Responses\ApiResponse;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;

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
}
