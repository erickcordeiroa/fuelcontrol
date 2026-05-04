<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('oil_changes', function (Blueprint $table) {
            $table->dateTime('occurred_at')->nullable()->after('date');
        });

        foreach (DB::table('oil_changes')->orderBy('id')->cursor() as $row) {
            $created = Carbon::parse($row->created_at);
            $occurred = Carbon::parse($row->date)->setTimeFromTimeString($created->format('H:i:s'));
            DB::table('oil_changes')->where('id', $row->id)->update([
                'occurred_at' => $occurred,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oil_changes', function (Blueprint $table) {
            $table->dropColumn('occurred_at');
        });
    }
};
