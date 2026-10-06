<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReserveController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

// Públicas
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::apiResource('rooms', RoomController::class)->only(['index', 'show']);

// Exigem token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('rooms', RoomController::class)->except(['index', 'show']);

    Route::apiResource('reserves', ReserveController::class)
        ->only(['store', 'show'])
        ->parameters(['reserves' => 'reserve']);
});