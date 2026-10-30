<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baixa feita pelo motorista no app (spec 004): motivo codificado, posição e chave para ignorar reenvio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('failure_reason_code', 32)->nullable()->after('failure_reason');
            $table->string('failure_note', 140)->nullable()->after('failure_reason_code');
            $table->foreignId('completed_by_driver_id')->nullable()->after('failure_note')->constrained('drivers')->nullOnDelete();
            $table->decimal('completed_latitude', 10, 7)->nullable()->after('completed_by_driver_id');
            $table->decimal('completed_longitude', 10, 7)->nullable()->after('completed_latitude');
            $table->unsignedSmallInteger('completed_accuracy_m')->nullable()->after('completed_longitude');
            $table->uuid('client_uuid')->nullable()->unique()->after('completed_accuracy_m');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by_driver_id');
            $table->dropUnique(['client_uuid']);
            $table->dropColumn(['failure_reason_code', 'failure_note', 'completed_latitude', 'completed_longitude', 'completed_accuracy_m', 'client_uuid']);
        });
    }
};
