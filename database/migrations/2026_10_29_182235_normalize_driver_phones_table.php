<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O celular do motorista vira login do app (spec 004): só dígitos e único por empresa.
 * Celulares repetidos na mesma empresa ficam em branco no segundo cadastro para o administrador corrigir.
 */
return new class extends Migration
{
    public function up(): void
    {
        $seen = [];

        DB::table('drivers')->whereNotNull('phone')->orderBy('id')->each(function (object $driver) use (&$seen): void {
            $digits = preg_replace('/\D/', '', (string) $driver->phone);
            $key = $driver->user_id.'|'.$digits;
            $phone = $digits === '' || isset($seen[$key]) ? null : $digits;

            if ($phone !== null) {
                $seen[$key] = true;
            }

            DB::table('drivers')->where('id', $driver->id)->update(['phone' => $phone]);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->unique(['user_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'phone']);
        });
    }
};
