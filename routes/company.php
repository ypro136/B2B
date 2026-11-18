<?php

use App\Http\Controllers\Company\Auth\LoginController;
use App\Http\Controllers\Company\BookingController;
use App\Http\Controllers\Company\CreditController;
use App\Http\Controllers\Company\DashboardController;
use App\Http\Controllers\Company\DocumentController;
use App\Http\Controllers\Company\PaymentController;
use App\Http\Controllers\Company\SeatRequestController;
use App\Http\Controllers\Company\TripController;
use Illuminate\Support\Facades\Route;

Route::prefix('company')
    ->name('company.')
    ->middleware(['portal:company'])
    ->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'login'])->name('login.store');

        Route::middleware(['auth', 'portal.user:company'])->group(function () {
            Route::post('logout', [LoginController::class, 'logout'])->name('logout');

            Route::get('/', fn () => redirect()->route('company.dashboard'))->name('home');
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

            Route::get('trips', [TripController::class, 'index'])->name('trips.index');
            Route::get('trips/{trip}', [TripController::class, 'show'])->name('trips.show');

            Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
            Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
            Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
            Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
            // Change-a-seat disabled for the demo: booking is by number of seats only.
            // Route::post('bookings/{booking}/change-seat', [BookingController::class, 'changeSeat'])->name('bookings.change-seat');

            Route::get('seat-requests', [SeatRequestController::class, 'index'])->name('seat-requests.index');
            Route::post('seat-requests', [SeatRequestController::class, 'store'])->name('seat-requests.store');

            Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
            Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');

            Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
        });
    });
