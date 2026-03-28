<?php

use App\Livewire\Dashboard\FleetDashboard;
use App\Livewire\Drivers\DriverIndex;
use App\Livewire\GasStations\GasStationIndex;
use App\Livewire\Logbook\TripLogForm;
use App\Livewire\Reports\RouteReports;
use App\Livewire\Vehicles\VehicleIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', FleetDashboard::class)->name('dashboard');
    Route::get('/diario', TripLogForm::class)->name('logbook');
    Route::get('/relatorios', RouteReports::class)->name('reports');

    Route::middleware('admin')->group(function () {
        Route::get('/veiculos', VehicleIndex::class)->name('vehicles.index');

        Route::get('/motoristas', DriverIndex::class)->name('drivers.index');

        Route::get('/postos', GasStationIndex::class)->name('gas-stations.index');
    });

    Route::redirect('/ativos/veiculos', '/veiculos')->name('assets.vehicles');
    Route::redirect('/ativos/motoristas', '/motoristas')->name('assets.drivers');

    Route::view('profile', 'profile')->name('profile');
});

require __DIR__.'/auth.php';
