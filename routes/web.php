<?php

use App\Http\Controllers\Deliveries\DeliveryImportTemplateController;
use App\Http\Controllers\Reports\RouteReportExportController;
use App\Livewire\Deliveries\DeliveryPlanner;
use App\Livewire\Deliveries\RouteMonitor;
use App\Livewire\Drivers\DriverIndex;
use App\Livewire\GasStations\GasStationIndex;
use App\Livewire\Integrations\IntegrationSettings;
use App\Livewire\Logbook\TripLogForm;
use App\Livewire\OilChanges\OilChangeIndex;
use App\Livewire\Reports\RouteReports;
use App\Livewire\Subscription\SubscriptionSettings;
use App\Livewire\Vehicles\VehicleIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    /** O painel antigo foi substituído por Relatórios, que passa a ser a página inicial. */
    Route::redirect('/dashboard', '/relatorios')->name('dashboard');
    Route::get('/diario/{trip?}', TripLogForm::class)->name('logbook');
    Route::get('/relatorios', RouteReports::class)->name('reports');
    Route::get('/relatorios/exportar', RouteReportExportController::class)->name('reports.export');

    Route::middleware('admin')->group(function () {
        Route::get('/veiculos', VehicleIndex::class)->name('vehicles.index');

        Route::get('/motoristas', DriverIndex::class)->name('drivers.index');

        Route::get('/postos', GasStationIndex::class)->name('gas-stations.index');

        Route::get('/trocas-oleo', OilChangeIndex::class)->name('oil-changes.index');

        Route::get('/entregas', DeliveryPlanner::class)->name('deliveries.planner');
        Route::get('/entregas/modelo', DeliveryImportTemplateController::class)->name('deliveries.template');
        Route::get('/rotas', RouteMonitor::class)->name('deliveries.monitor');

        Route::get('/integracoes', IntegrationSettings::class)->name('integrations.index');

        Route::get('/assinatura', SubscriptionSettings::class)->name('subscription.index');
    });

    Route::redirect('/ativos/veiculos', '/veiculos')->name('assets.vehicles');
    Route::redirect('/ativos/motoristas', '/motoristas')->name('assets.drivers');

    Route::view('profile', 'profile')->name('profile');
});

require __DIR__.'/auth.php';
