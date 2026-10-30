<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rota pelas ruas (Geoapify Route Planner + Routing): tempo, trajeto e instruções de cada trecho para o GPS do app.
 * Os créditos de cada chamada entram no teto diário da conta da plataforma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->unsignedInteger('total_duration_s')->nullable()->after('total_distance_m');
            $table->string('routing_provider', 20)->nullable()->after('total_duration_s');
            $table->text('return_path_polyline')->nullable()->after('routing_provider');
        });

        Schema::table('route_stops', function (Blueprint $table) {
            $table->unsignedInteger('duration_from_previous_s')->nullable()->after('distance_from_previous_m');
            $table->text('path_polyline')->nullable()->after('duration_from_previous_s');
            $table->json('navigation_steps')->nullable()->after('path_polyline');
        });

        Schema::table('geocoding_requests', function (Blueprint $table) {
            $table->unsignedInteger('credits')->default(1)->after('billable');
        });
    }

    public function down(): void
    {
        Schema::table('geocoding_requests', function (Blueprint $table) {
            $table->dropColumn('credits');
        });

        Schema::table('route_stops', function (Blueprint $table) {
            $table->dropColumn(['duration_from_previous_s', 'path_polyline', 'navigation_steps']);
        });

        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->dropColumn(['total_duration_s', 'routing_provider', 'return_path_polyline']);
        });
    }
};
