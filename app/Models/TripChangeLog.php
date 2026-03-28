<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripChangeLog extends Model
{
    public static function labelForField(string $key): string
    {
        return match ($key) {
            'date' => __('Data'),
            'vehicle_id' => __('Veículo'),
            'driver_id' => __('Motorista'),
            'km_start' => __('KM inicial'),
            'km_end' => __('KM final'),
            'revenue' => __('Receita'),
            'gas_station_id' => __('Posto (cadastro)'),
            'gas_station_fuel_offering_id' => __('Combustível no posto'),
            'fuel_type' => __('Tipo de combustível'),
            'liters' => __('Litros'),
            'price_per_liter' => __('Valor por litro (R$)'),
            'station' => __('Posto / convênio (texto)'),
            'toll' => __('Pedágio'),
            'assistant' => __('Ajudante'),
            'food' => __('Alimentação'),
            default => $key,
        };
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'user_id',
        'before',
        'after',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, array{before: mixed, after: mixed}>
     */
    public function diffSnapshots(): array
    {
        /** @var array<string, mixed> $before */
        $before = $this->before;
        /** @var array<string, mixed> $after */
        $after = $this->after;

        $keys = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
        $out = [];

        foreach ($keys as $key) {
            $b = $before[$key] ?? null;
            $a = $after[$key] ?? null;
            if ($this->valuesDiffer($b, $a)) {
                $out[$key] = ['before' => $b, 'after' => $a];
            }
        }

        return $out;
    }

    private function valuesDiffer(mixed $before, mixed $after): bool
    {
        if (is_float($before) || is_float($after) || is_int($before) || is_int($after)) {
            return round((float) $before, 4) !== round((float) $after, 4);
        }

        return $before !== $after;
    }
}
