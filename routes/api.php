<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReserveController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

Route::apiResource('rooms', RoomController::class);

Route::post('/reserves', [ReserveController::class, 'store']);
Route::get('/reserves/{reserve}', [ReserveController::class, 'show']);

Route::post('/reserves/{reserve}/payments', [PaymentController::class, 'store']);
Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);
