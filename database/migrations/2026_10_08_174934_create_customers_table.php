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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_code', 64);
            $table->string('name');
            $table->string('street');
            $table->string('number', 20)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('city', 120);
            $table->string('state', 2);
            $table->string('zip_code', 8);
            $table->string('reference', 500)->nullable();
            $table->string('address_hash', 64);
            $table->string('geocode_status', 20)->default('pending');
            $table->string('geocode_error')->nullable();

            /** Coordenada captada pelo GPS do motorista na baixa: dado próprio, permanente. */
            $table->decimal('confirmed_latitude', 10, 7)->nullable();
            $table->decimal('confirmed_longitude', 10, 7)->nullable();
            $table->timestamp('confirmed_at')->nullable();

            /** Place ID do Google: pode ser guardado sem prazo. */
            $table->string('google_place_id')->nullable();

            /** Cache do Google: os termos permitem guardar por até 30 dias. */
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_type', 32)->nullable();
            $table->timestamp('geocoded_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'external_code']);
            $table->index(['user_id', 'geocode_status']);
            $table->index('geocoded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
