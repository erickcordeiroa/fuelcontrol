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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            /** Chave do plano em config/plans.php. */
            $table->string('plan', 40);

            /** active, pending_payment, past_due ou canceled (cobrança: spec 008). */
            $table->string('status', 20)->default('active');

            /** Limites combinados para o plano sob consulta; sobrepõem os do plano. */
            $table->json('custom_limits')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
