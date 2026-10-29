<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rota base: a ordem dos clientes de uma rota que se repete (ex.: "Setor Sul" toda semana). A próxima rota
 * com o mesmo nome segue essa ordem e só encaixa os clientes novos, sem pagar uma nova otimização.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('name_key', 120);
            $table->json('customer_ids');
            $table->timestamp('optimized_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_templates');
    }
};
