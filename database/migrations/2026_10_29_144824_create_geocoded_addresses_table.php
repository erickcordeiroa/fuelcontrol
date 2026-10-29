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
        Schema::create('geocoded_addresses', function (Blueprint $table) {
            $table->id();

            /**
             * Nulo = resultado do provedor, compartilhado entre empresas.
             * Preenchido = posição confirmada (GPS ou pino) que vale só para a empresa que confirmou.
             */
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            /** Endereço normalizado (logradouro, número, cidade e UF). Sem dados de cliente. */
            $table->string('address_key', 64);
            $table->string('street');
            $table->string('number', 20)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('city', 120);
            $table->string('state', 2);
            $table->string('zip_code', 8)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            /** ROOFTOP (número), STREET (rua) ou APPROXIMATE. */
            $table->string('location_type', 32)->nullable();

            /** provider, gps ou manual. Posições de GPS e manuais prevalecem sobre o provedor. */
            $table->string('source', 20);
            $table->string('provider', 20)->nullable();
            $table->timestamp('not_found_until')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['address_key', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geocoded_addresses');
    }
};
