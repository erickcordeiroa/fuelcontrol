<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A chave do Google por empresa deu lugar à conta única da plataforma (spec 005).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('google_maps_credentials');
    }

    public function down(): void
    {
        Schema::create('google_maps_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('api_key');
            $table->string('key_hint', 8);
            $table->timestamp('verified_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }
};
