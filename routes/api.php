<?php

use App\Http\Controllers\Api\V1\Driver\DriverAuthController;
use App\Http\Controllers\Api\V1\Driver\DriverLocationController;
use App\Http\Controllers\Api\V1\Driver\DriverRouteController;
use App\Http\Controllers\Api\V1\Driver\DriverStopController;
use Illuminate\Support\Facades\Route;

/**
 * API do app do motorista (spec 004).
 */
Route::prefix('v1/driver')->name('api.v1.driver.')->group(function (): void {
    Route::post('login', [DriverAuthController::class, 'login'])->middleware('throttle:driver-login')->name('login');

    Route::middleware(['auth:sanctum', 'driver'])->group(function (): void {
        Route::post('logout', [DriverAuthController::class, 'logout'])->name('logout');

        Route::get('route/today', [DriverRouteController::class, 'today'])->name('route.today');
        Route::post('routes/{deliveryRoute}/start', [DriverRouteController::class, 'start'])->name('routes.start');
        Route::post('routes/{deliveryRoute}/finish', [DriverRouteController::class, 'finish'])->name('routes.finish');
        Route::post('routes/{deliveryRoute}/locations', [DriverLocationController::class, 'store'])->name('routes.locations');

        Route::post('stops/{stop}/deliver', [DriverStopController::class, 'deliver'])->name('stops.deliver');
        Route::post('stops/{stop}/fail', [DriverStopController::class, 'fail'])->name('stops.fail');
        Route::get('stops/{stop}/directions', [DriverStopController::class, 'directions'])->middleware('throttle:driver-directions')->name('stops.directions');
    });
});
