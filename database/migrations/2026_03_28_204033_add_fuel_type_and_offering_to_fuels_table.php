<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuels', function (Blueprint $table) {
            $table->string('fuel_type', 32)->default('gasolina_comum');
            $table->foreignId('gas_station_fuel_offering_id')
                ->nullable()
                ->constrained('gas_station_fuel_offerings')
                ->nullOnDelete();
        });

        DB::table('fuels')->whereNull('fuel_type')->update(['fuel_type' => 'gasolina_comum']);
    }

    public function down(): void
    {
        Schema::table('fuels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gas_station_fuel_offering_id');
            $table->dropColumn('fuel_type');
        });
    }
};
