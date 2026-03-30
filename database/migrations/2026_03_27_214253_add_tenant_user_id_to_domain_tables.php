<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->dropUniquePlateIndexIfExists();

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'plate']);
        });

        Schema::table('gas_stations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('fuels', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('trip_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('trip_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->renameColumn('user_id', 'linked_user_id');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $ownerId = DB::table('users')->orderBy('id')->value('id');
        if ($ownerId !== null) {
            DB::table('vehicles')->whereNull('user_id')->update(['user_id' => $ownerId]);
            DB::table('gas_stations')->whereNull('user_id')->update(['user_id' => $ownerId]);
            DB::table('drivers')->whereNull('user_id')->update(['user_id' => $ownerId]);
            DB::table('trips')->whereNull('user_id')->update(['user_id' => $ownerId]);
            DB::table('fuels')->whereNull('user_id')->update(['user_id' => $ownerId]);
            DB::table('expenses')->whereNull('user_id')->update(['user_id' => $ownerId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('fuels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'plate']);
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique('plate');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->renameColumn('linked_user_id', 'user_id');
        });
    }

    /**
     * Remove the legacy single-column unique index on `plate` when present.
     *
     * Production databases may use a different index name than Laravel's
     * conventional `vehicles_plate_unique`, which causes `dropUnique(['plate'])` to fail.
     */
    private function dropUniquePlateIndexIfExists(): void
    {
        foreach (Schema::getIndexes('vehicles') as $index) {
            if ($index['unique'] && ! $index['primary'] && $index['columns'] === ['plate']) {
                Schema::table('vehicles', function (Blueprint $table) use ($index) {
                    $table->dropUnique($index['name']);
                });

                return;
            }
        }
    }
};
