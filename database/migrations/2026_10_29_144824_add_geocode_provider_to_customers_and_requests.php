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
        Schema::table('customers', function (Blueprint $table) {
            /** Provedor da coordenada em cache (google, geoapify, address_book). Só o Google tem prazo de 30 dias. */
            $table->string('geocode_provider', 20)->nullable()->after('location_type');
        });

        Schema::table('geocoding_requests', function (Blueprint $table) {
            $table->string('provider', 20)->default('google')->after('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('geocode_provider');
        });

        Schema::table('geocoding_requests', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
