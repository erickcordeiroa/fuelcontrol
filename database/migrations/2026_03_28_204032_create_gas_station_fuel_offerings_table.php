<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preços por tipo de combustível por posto; migra o preço único legado para "gasolina comum".
     */
    public function up(): void
    {
        Schema::create('gas_station_fuel_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('gas_station_id')->constrained()->cascadeOnDelete();
            $table->string('fuel_type', 32);
            $table->unsignedBigInteger('price_per_liter')->default(0);
            $table->timestamps();

            $table->unique(['gas_station_id', 'fuel_type']);
        });

        $now = now();
        foreach (DB::table('gas_stations')->cursor() as $row) {
            DB::table('gas_station_fuel_offerings')->insert([
                'user_id' => $row->user_id,
                'gas_station_id' => $row->id,
                'fuel_type' => 'gasolina_comum',
                'price_per_liter' => (int) ($row->price_per_liter ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropColumn('price_per_liter');
        });
    }

    public function down(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->unsignedBigInteger('price_per_liter')->default(0);
        });

        foreach (DB::table('gas_stations')->cursor() as $row) {
            $offering = DB::table('gas_station_fuel_offerings')
                ->where('gas_station_id', $row->id)
                ->where('fuel_type', 'gasolina_comum')
                ->first();
            $price = $offering !== null ? (int) $offering->price_per_liter : 0;
            DB::table('gas_stations')->where('id', $row->id)->update(['price_per_liter' => $price]);
        }

        Schema::dropIfExists('gas_station_fuel_offerings');
    }
};
