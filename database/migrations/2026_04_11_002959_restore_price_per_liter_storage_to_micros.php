<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['gas_stations', 'gas_station_fuel_offerings', 'fuels'] as $table) {
            $this->promoteLegacyCentValuesToMicros($table);
        }
    }

    public function down(): void
    {
        foreach (['gas_stations', 'gas_station_fuel_offerings', 'fuels'] as $table) {
            $this->restoreMicrosToCentValues($table);
        }
    }

    private function promoteLegacyCentValuesToMicros(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'price_per_liter')) {
            return;
        }

        DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table): void {
            foreach ($rows as $row) {
                $storedPrice = (int) ($row->price_per_liter ?? 0);

                if ($storedPrice <= 0 || $storedPrice >= 10_000) {
                    continue;
                }

                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['price_per_liter' => $storedPrice * 100]);
            }
        });
    }

    private function restoreMicrosToCentValues(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'price_per_liter')) {
            return;
        }

        DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table): void {
            foreach ($rows as $row) {
                $storedPrice = (int) ($row->price_per_liter ?? 0);

                if ($storedPrice < 10_000 || $storedPrice % 100 !== 0) {
                    continue;
                }

                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['price_per_liter' => (int) round($storedPrice / 100)]);
            }
        });
    }
};
