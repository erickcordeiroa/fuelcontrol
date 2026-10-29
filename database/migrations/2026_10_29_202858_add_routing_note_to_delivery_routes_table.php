<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Por que a rota não foi otimizada ou traçada pelas ruas (limite diário de créditos ou falha do serviço),
 * para avisar na tela enquanto a rota existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->string('routing_note', 30)->nullable()->after('order_source');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->dropColumn('routing_note');
        });
    }
};
