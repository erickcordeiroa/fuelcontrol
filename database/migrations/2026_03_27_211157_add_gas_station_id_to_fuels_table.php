<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fuels', function (Blueprint $table) {
            $table->foreignId('gas_station_id')
                ->nullable()
                ->after('trip_id')
                ->constrained('gas_stations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gas_station_id');
        });
    }
};
