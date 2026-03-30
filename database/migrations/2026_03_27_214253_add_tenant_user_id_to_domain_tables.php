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

        if (! Schema::hasColumn('vehicles', 'user_id')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        if (! Schema::hasIndex('vehicles', ['user_id', 'plate'], 'unique')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->unique(['user_id', 'plate']);
            });
        }

        $this->addForeignUserIdIfMissing('gas_stations', 'id');
        $this->addForeignUserIdIfMissing('trips', 'id');
        $this->addForeignUserIdIfMissing('fuels', 'trip_id');
        $this->addForeignUserIdIfMissing('expenses', 'trip_id');

        $this->migrateDriversForTenancy();

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
     * Add nullable tenant `user_id` when missing (safe after failed partial migrations).
     */
    private function addForeignUserIdIfMissing(string $tableName, string $afterColumn): void
    {
        if (Schema::hasColumn($tableName, 'user_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($afterColumn) {
            $table->foreignId('user_id')->nullable()->after($afterColumn)->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Rename legacy `user_id` (driver account link) to `linked_user_id`, then add tenant `user_id`.
     */
    private function migrateDriversForTenancy(): void
    {
        if (Schema::hasColumn('drivers', 'linked_user_id') && Schema::hasColumn('drivers', 'user_id')) {
            return;
        }

        if (Schema::hasColumn('drivers', 'linked_user_id') && ! Schema::hasColumn('drivers', 'user_id')) {
            $this->addForeignUserIdIfMissing('drivers', 'id');

            return;
        }

        if (Schema::hasColumn('drivers', 'user_id') && ! Schema::hasColumn('drivers', 'linked_user_id')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->renameColumn('user_id', 'linked_user_id');
            });
            $this->addForeignUserIdIfMissing('drivers', 'id');
        }
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
