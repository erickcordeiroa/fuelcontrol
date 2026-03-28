<?php

namespace Database\Seeders;

use App\Enums\ExpenseType;
use App\Enums\FuelType;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\Fuel;
use App\Models\GasStation;
use App\Models\GasStationFuelOffering;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->create([
            'company_name' => 'FuelTrack Demo',
            'name' => 'Administrador',
            'email' => 'admin@fueltrack.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $driverUser = User::query()->create([
            'name' => 'Ricardo Oliveira',
            'email' => 'motorista@fueltrack.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Driver,
            'email_verified_at' => now(),
        ]);

        $tenantId = $admin->id;

        $vehicles = collect([
            ['plate' => 'BRA-2E19', 'model' => 'Volvo FH 540', 'capacity' => 28000, 'fuel_type' => 'Diesel S10'],
            ['plate' => 'XYZ-9A01', 'model' => 'Scania R450', 'capacity' => 24000, 'fuel_type' => 'Diesel'],
            ['plate' => 'QWE-4C88', 'model' => 'Mercedes Actros', 'capacity' => 32000, 'fuel_type' => 'Diesel S10'],
        ])->map(fn (array $row) => Vehicle::query()->create([...$row, 'user_id' => $tenantId]));

        $gasStationConfigs = [
            [
                'name' => 'Ipiranga RodoSul',
                'phone' => '(11) 3000-1000',
                'address' => 'Rod. Anhanguera, km 42 — Campinas/SP',
                'offerings' => [
                    [FuelType::DieselS10, 6.299],
                    [FuelType::GasolinaComum, 6.089],
                ],
            ],
            [
                'name' => 'Shell BR',
                'phone' => '(11) 4000-2000',
                'address' => 'Av. Brasil, 1500 — São Paulo/SP',
                'offerings' => [
                    [FuelType::DieselS10, 6.459],
                    [FuelType::GasolinaComum, 6.259],
                    [FuelType::Etanol, 4.199],
                ],
            ],
            [
                'name' => 'BR Distribuidora',
                'phone' => null,
                'address' => 'BR-116, posto 12',
                'offerings' => [
                    [FuelType::DieselS10, 6.189],
                ],
            ],
        ];

        $gasStations = collect($gasStationConfigs)->map(function (array $config) use ($tenantId) {
            $offerings = $config['offerings'];
            unset($config['offerings']);

            $station = GasStation::query()->create([
                ...$config,
                'user_id' => $tenantId,
            ]);

            foreach ($offerings as [$fuelType, $price]) {
                GasStationFuelOffering::query()->create([
                    'gas_station_id' => $station->id,
                    'fuel_type' => $fuelType,
                    'price_per_liter' => $price,
                ]);
            }

            return $station->load('fuelOfferings');
        });

        $driverLinked = Driver::query()->create([
            'name' => 'Ricardo Oliveira',
            'license_number' => '88210001234',
            'phone' => '+5511999990001',
            'user_id' => $tenantId,
            'linked_user_id' => $driverUser->id,
        ]);

        $driverExtra = Driver::query()->create([
            'name' => 'Ana Souza',
            'license_number' => '88210005555',
            'phone' => '+5511988880002',
            'user_id' => $tenantId,
            'linked_user_id' => null,
        ]);

        foreach (range(1, 12) as $i) {
            $kmStart = 50_000 + ($i * 100);
            $kmEnd = $kmStart + random_int(120, 620);

            $trip = Trip::query()->create([
                'user_id' => $tenantId,
                'date' => now()->subDays($i * 3)->toDateString(),
                'vehicle_id' => $vehicles->random()->id,
                'driver_id' => collect([$driverLinked->id, $driverExtra->id])->random(),
                'km_start' => $kmStart,
                'km_end' => $kmEnd,
                'km_total' => $kmEnd - $kmStart,
                'revenue' => 0,
                'status' => TripStatus::Completed,
            ]);

            $liters = random_int(80, 220) + (random_int(0, 99) / 100);

            $station = $gasStations->random();
            $offering = $station->fuelOfferings->random();

            Fuel::query()->create([
                'user_id' => $tenantId,
                'trip_id' => $trip->id,
                'gas_station_id' => $station->id,
                'gas_station_fuel_offering_id' => $offering->id,
                'fuel_type' => $offering->fuel_type,
                'liters' => $liters,
                'price_per_liter' => (float) $offering->price_per_liter,
                'station' => $station->name,
            ]);

            Expense::query()->create([
                'user_id' => $tenantId,
                'trip_id' => $trip->id,
                'type' => ExpenseType::Toll,
                'amount' => random_int(20, 180),
            ]);
            Expense::query()->create([
                'user_id' => $tenantId,
                'trip_id' => $trip->id,
                'type' => ExpenseType::Assistant,
                'amount' => random_int(0, 120),
            ]);
            Expense::query()->create([
                'user_id' => $tenantId,
                'trip_id' => $trip->id,
                'type' => ExpenseType::Food,
                'amount' => random_int(30, 200),
            ]);
        }

        $this->command->info('Admin: '.$admin->email.' / password');
        $this->command->info('Motorista: '.$driverUser->email.' / password');
    }
}
