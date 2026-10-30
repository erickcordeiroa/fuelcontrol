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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            /** Empresa dona do dado. Fica nula se a empresa for removida, para o log não sumir. */
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            /** Evento, registro, mudanças (old/new), quem fez, origem e IP. */
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
