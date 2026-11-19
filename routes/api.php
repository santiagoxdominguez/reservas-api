<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ConsultantController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/consultants', [ConsultantController::class, 'index']);
    Route::get('/consultants/{id}/calendar',[ConsultantController::class, 'calendar']);
    Route::get('/consultant/reservations', [ConsultantController::class, 'myReservations']);
    Route::get('/consultants/{id}', [ConsultantController::class, 'show']);
    Route::get('/consultant/calendar', [ConsultantController::class, 'myCalendar']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
