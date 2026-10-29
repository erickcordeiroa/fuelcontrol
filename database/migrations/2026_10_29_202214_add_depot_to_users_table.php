<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de distribuição da empresa (no usuário dono dos dados): saída e chegada das rotas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('depot_address')->nullable()->after('company_name');
            $table->decimal('depot_latitude', 10, 7)->nullable()->after('depot_address');
            $table->decimal('depot_longitude', 10, 7)->nullable()->after('depot_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['depot_address', 'depot_latitude', 'depot_longitude']);
        });
    }
};
