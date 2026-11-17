<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\SeatRequestController;
use App\Http\Controllers\Api\TripController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('api.login');

Route::middleware(['auth:sanctum', 'portal.user:company'])->group(function () {
    Route::get('user', [AuthController::class, 'show'])->name('api.user');
    Route::post('logout', [AuthController::class, 'logout'])->name('api.logout');

    Route::get('trips', [TripController::class, 'index'])->name('api.trips.index');

    Route::get('bookings', [BookingController::class, 'index'])->name('api.bookings.index');
    Route::post('bookings', [BookingController::class, 'store'])->name('api.bookings.store');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('api.bookings.cancel');

    Route::get('seat-requests', [SeatRequestController::class, 'index'])->name('api.seat-requests.index');
    Route::post('seat-requests', [SeatRequestController::class, 'store'])->name('api.seat-requests.store');
});
