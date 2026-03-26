<?php

namespace Database\Seeders;

use App\Enums\ExpenseType;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\Fuel;
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

        $vehicles = collect([
            ['plate' => 'BRA-2E19', 'model' => 'Volvo FH 540', 'capacity' => 28000, 'fuel_type' => 'Diesel S10'],
            ['plate' => 'XYZ-9A01', 'model' => 'Scania R450', 'capacity' => 24000, 'fuel_type' => 'Diesel'],
            ['plate' => 'QWE-4C88', 'model' => 'Mercedes Actros', 'capacity' => 32000, 'fuel_type' => 'Diesel S10'],
        ])->map(fn (array $row) => Vehicle::query()->create($row));

        $driverLinked = Driver::query()->create([
            'name' => 'Ricardo Oliveira',
            'license_number' => '88210001234',
            'phone' => '+5511999990001',
            'user_id' => $driverUser->id,
        ]);

        $driverExtra = Driver::query()->create([
            'name' => 'Ana Souza',
            'license_number' => '88210005555',
            'phone' => '+5511988880002',
            'user_id' => null,
        ]);

        foreach (range(1, 12) as $i) {
            $kmStart = 50_000 + ($i * 100);
            $kmEnd = $kmStart + random_int(120, 620);

            $trip = Trip::query()->create([
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
            $price = 5 + (random_int(50, 150) / 100);

            Fuel::query()->create([
                'trip_id' => $trip->id,
                'liters' => $liters,
                'price_per_liter' => $price,
                'station' => collect(['Ipiranga RodoSul', 'Shell BR', 'BR Distribuidora'])->random(),
            ]);

            Expense::query()->create([
                'trip_id' => $trip->id,
                'type' => ExpenseType::Toll,
                'amount' => random_int(20, 180),
            ]);
            Expense::query()->create([
                'trip_id' => $trip->id,
                'type' => ExpenseType::Assistant,
                'amount' => random_int(0, 120),
            ]);
            Expense::query()->create([
                'trip_id' => $trip->id,
                'type' => ExpenseType::Food,
                'amount' => random_int(30, 200),
            ]);
        }

        $this->command->info('Admin: '.$admin->email.' / password');
        $this->command->info('Motorista: '.$driverUser->email.' / password');
    }
}
