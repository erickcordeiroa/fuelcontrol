<?php

use App\Livewire\Assets\DriverManager;
use App\Livewire\Assets\VehicleManager;
use App\Livewire\Dashboard\FleetDashboard;
use App\Livewire\Logbook\TripLogForm;
use App\Livewire\Reports\RouteReports;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', FleetDashboard::class)->name('dashboard');
    Route::get('/diario', TripLogForm::class)->name('logbook');
    Route::get('/relatorios', RouteReports::class)->name('reports');

    Route::middleware('admin')->group(function () {
        Route::get('/ativos/veiculos', VehicleManager::class)->name('assets.vehicles');
        Route::get('/ativos/motoristas', DriverManager::class)->name('assets.drivers');
    });

    Route::view('profile', 'profile')->name('profile');
});

require __DIR__.'/auth.php';
