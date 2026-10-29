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
        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->unsignedInteger('distance_from_previous_m')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamps();

            $table->index(['delivery_route_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_stops');
    }
};
