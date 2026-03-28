<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stored values were ten-thousandths of a real (4 decimal places). Convert to centavos (2 decimal places).
     */
    public function up(): void
    {
        foreach (['gas_stations', 'fuels'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $old = (int) ($row->price_per_liter ?? 0);
                    $new = (int) round($old / 100);
                    DB::table($table)->where('id', $row->id)->update(['price_per_liter' => $new]);
                }
            });
        }
    }

    /**
     * Best-effort restore of ten-thousandths storage (may not match every historical fractional value).
     */
    public function down(): void
    {
        foreach (['gas_stations', 'fuels'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $old = (int) ($row->price_per_liter ?? 0);
                    $new = (int) round($old * 100);
                    DB::table($table)->where('id', $row->id)->update(['price_per_liter' => $new]);
                }
            });
        }
    }
};
