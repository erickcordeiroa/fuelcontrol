<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oil_changes', function (Blueprint $table) {
            $table->unsignedInteger('km_at_change')->nullable()->after('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::table('oil_changes', function (Blueprint $table) {
            $table->dropColumn('km_at_change');
        });
    }
};
