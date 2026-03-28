<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->convertExpensesAmountToCentavos();
        $this->convertFuelsPricePerLiterToMicros();
        $this->convertGasStationsPricePerLiterToMicros();
        $this->convertTripsRevenueToCentavos();
    }

    public function down(): void
    {
        $this->revertTripsRevenueToDecimal();
        $this->revertGasStationsPricePerLiterToDecimal();
        $this->revertFuelsPricePerLiterToDecimal();
        $this->revertExpensesAmountToDecimal();
    }

    private function convertExpensesAmountToCentavos(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('amount_minor')->default(0);
        });

        foreach (DB::table('expenses')->cursor() as $row) {
            DB::table('expenses')->where('id', $row->id)->update([
                'amount_minor' => (int) round((float) $row->amount * 100),
            ]);
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->renameColumn('amount_minor', 'amount');
        });
    }

    private function convertFuelsPricePerLiterToMicros(): void
    {
        Schema::table('fuels', function (Blueprint $table) {
            $table->unsignedBigInteger('price_per_liter_micros')->default(0);
        });

        foreach (DB::table('fuels')->cursor() as $row) {
            DB::table('fuels')->where('id', $row->id)->update([
                'price_per_liter_micros' => (int) round((float) $row->price_per_liter * 10000),
            ]);
        }

        Schema::table('fuels', function (Blueprint $table) {
            $table->dropColumn('price_per_liter');
        });

        Schema::table('fuels', function (Blueprint $table) {
            $table->renameColumn('price_per_liter_micros', 'price_per_liter');
        });
    }

    private function convertGasStationsPricePerLiterToMicros(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->unsignedBigInteger('price_per_liter_micros')->default(0);
        });

        foreach (DB::table('gas_stations')->cursor() as $row) {
            DB::table('gas_stations')->where('id', $row->id)->update([
                'price_per_liter_micros' => (int) round((float) $row->price_per_liter * 10000),
            ]);
        }

        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropColumn('price_per_liter');
        });

        Schema::table('gas_stations', function (Blueprint $table) {
            $table->renameColumn('price_per_liter_micros', 'price_per_liter');
        });
    }

    private function convertTripsRevenueToCentavos(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedBigInteger('revenue_minor')->default(0);
        });

        foreach (DB::table('trips')->cursor() as $row) {
            DB::table('trips')->where('id', $row->id)->update([
                'revenue_minor' => (int) round((float) $row->revenue * 100),
            ]);
        }

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('revenue');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->renameColumn('revenue_minor', 'revenue');
        });
    }

    private function revertExpensesAmountToDecimal(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('amount_decimal', 12, 2)->default(0);
        });

        foreach (DB::table('expenses')->cursor() as $row) {
            DB::table('expenses')->where('id', $row->id)->update([
                'amount_decimal' => round(((int) $row->amount) / 100, 2),
            ]);
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->renameColumn('amount_decimal', 'amount');
        });
    }

    private function revertFuelsPricePerLiterToDecimal(): void
    {
        Schema::table('fuels', function (Blueprint $table) {
            $table->decimal('price_per_liter_decimal', 10, 4)->default(0);
        });

        foreach (DB::table('fuels')->cursor() as $row) {
            DB::table('fuels')->where('id', $row->id)->update([
                'price_per_liter_decimal' => round(((int) $row->price_per_liter) / 10000, 4),
            ]);
        }

        Schema::table('fuels', function (Blueprint $table) {
            $table->dropColumn('price_per_liter');
        });

        Schema::table('fuels', function (Blueprint $table) {
            $table->renameColumn('price_per_liter_decimal', 'price_per_liter');
        });
    }

    private function revertGasStationsPricePerLiterToDecimal(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->decimal('price_per_liter_decimal', 10, 4)->default(0);
        });

        foreach (DB::table('gas_stations')->cursor() as $row) {
            DB::table('gas_stations')->where('id', $row->id)->update([
                'price_per_liter_decimal' => round(((int) $row->price_per_liter) / 10000, 4),
            ]);
        }

        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropColumn('price_per_liter');
        });

        Schema::table('gas_stations', function (Blueprint $table) {
            $table->renameColumn('price_per_liter_decimal', 'price_per_liter');
        });
    }

    private function revertTripsRevenueToDecimal(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('revenue_decimal', 12, 2)->default(0);
        });

        foreach (DB::table('trips')->cursor() as $row) {
            DB::table('trips')->where('id', $row->id)->update([
                'revenue_decimal' => round(((int) $row->revenue) / 100, 2),
            ]);
        }

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('revenue');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->renameColumn('revenue_decimal', 'revenue');
        });
    }
};
