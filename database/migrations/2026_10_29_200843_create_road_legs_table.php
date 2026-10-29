<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trechos pelas ruas já traçados (ponto A → ponto B). Rotas da semana seguinte reaproveitam os trechos
 * que se repetem e só pedem ao serviço de rotas os que mudaram.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('road_legs', function (Blueprint $table) {
            $table->id();
            $table->string('leg_key', 64)->unique();
            $table->unsignedInteger('distance_m');
            $table->unsignedInteger('duration_s')->nullable();
            $table->text('path_polyline')->nullable();
            $table->json('navigation_steps')->nullable();
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('road_legs');
    }
};
