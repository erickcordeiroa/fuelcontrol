<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De onde veio a ordem das paradas: Route Planner, cálculo local ou rota base reaproveitada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->string('order_source', 20)->nullable()->after('routing_provider');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->dropColumn('order_source');
        });
    }
};
