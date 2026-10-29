<?php

namespace App\Models;

use Database\Factories\RoadLegFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Trecho pelas ruas entre dois pontos, guardado para não pagar o mesmo traçado de novo. Sem empresa: é só geometria.
 */
class RoadLeg extends Model
{
    /** @use HasFactory<RoadLegFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'leg_key',
        'distance_m',
        'duration_s',
        'path_polyline',
        'navigation_steps',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'distance_m' => 'integer',
            'duration_s' => 'integer',
            'navigation_steps' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Mesma chave para pontos a menos de ~1 m de distância (5 casas decimais).
     *
     * @param  array{lat: float, lng: float}  $from
     * @param  array{lat: float, lng: float}  $to
     */
    public static function keyFor(array $from, array $to): string
    {
        return hash('sha256', implode(',', array_map(fn (float $value): string => number_format($value, 5, '.', ''), [$from['lat'], $from['lng'], $to['lat'], $to['lng']])));
    }
}
