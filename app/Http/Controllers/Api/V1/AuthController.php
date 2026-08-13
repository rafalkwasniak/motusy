<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Responses\ApiResponse;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /**
     * Register a new account.
     *
     * The account is usable immediately. The profile is not created here — the app
     * sends it separately, and `profile_complete` tells it whether to open onboarding.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name')->toString() ?: null,
        );

        return ApiResponse::success(__('api.registered'), $result, status: 201);
    }

    /**
     * Exchange credentials for an access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name')->toString() ?: null,
        );

        return ApiResponse::success(__('api.logged_in'), $result);
    }

    /**
     * Revoke the token used for this request. Other devices stay signed in.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return ApiResponse::success(__('api.logged_out'));
    }

    /**
     * The signed-in user's own account, including profile and motorcycle.
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(__('api.fetched'), $request->user()->account());
    }
}
