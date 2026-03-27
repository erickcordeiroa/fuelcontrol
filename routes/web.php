<?php

use App\Livewire\Dashboard\FleetDashboard;
use App\Livewire\Drivers\DriverCreate;
use App\Livewire\Drivers\DriverEdit;
use App\Livewire\Drivers\DriverIndex;
use App\Livewire\GasStations\GasStationCreate;
use App\Livewire\GasStations\GasStationEdit;
use App\Livewire\GasStations\GasStationIndex;
use App\Livewire\Logbook\TripLogForm;
use App\Livewire\Reports\RouteReports;
use App\Livewire\Vehicles\VehicleCreate;
use App\Livewire\Vehicles\VehicleEdit;
use App\Livewire\Vehicles\VehicleIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', FleetDashboard::class)->name('dashboard');
    Route::get('/diario', TripLogForm::class)->name('logbook');
    Route::get('/relatorios', RouteReports::class)->name('reports');

    Route::middleware('admin')->group(function () {
        Route::get('/veiculos', VehicleIndex::class)->name('vehicles.index');
        Route::get('/veiculos/criar', VehicleCreate::class)->name('vehicles.create');
        Route::get('/veiculos/{vehicle}/editar', VehicleEdit::class)->name('vehicles.edit');

        Route::get('/motoristas', DriverIndex::class)->name('drivers.index');
        Route::get('/motoristas/criar', DriverCreate::class)->name('drivers.create');
        Route::get('/motoristas/{driver}/editar', DriverEdit::class)->name('drivers.edit');

        Route::get('/postos', GasStationIndex::class)->name('gas-stations.index');
        Route::get('/postos/criar', GasStationCreate::class)->name('gas-stations.create');
        Route::get('/postos/{gas_station}/editar', GasStationEdit::class)->name('gas-stations.edit');
    });

    Route::redirect('/ativos/veiculos', '/veiculos')->name('assets.vehicles');
    Route::redirect('/ativos/motoristas', '/motoristas')->name('assets.drivers');

    Route::view('profile', 'profile')->name('profile');
});

require __DIR__.'/auth.php';
