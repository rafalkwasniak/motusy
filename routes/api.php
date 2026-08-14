<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BleIdentityController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\MotorcycleController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/incognito', [ProfileController::class, 'incognito']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);

    Route::post('/motorcycle', [MotorcycleController::class, 'update']);
    Route::post('/motorcycle/photo', [MotorcycleController::class, 'uploadPhoto']);
    Route::delete('/motorcycle/photo', [MotorcycleController::class, 'deletePhoto']);

    Route::get('/ble/identity', [BleIdentityController::class, 'show']);
    Route::post('/ble/identity/rotate', [BleIdentityController::class, 'rotate']);

    Route::post('/devices', [DeviceController::class, 'store']);

    Route::post('/meetings', [MeetingController::class, 'store'])->middleware('throttle:meetings');
    Route::get('/meetings', [MeetingController::class, 'index']);
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
});
