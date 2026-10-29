<?php

namespace App\Http\Resources\Api\V1\Driver;

use App\Services\Routing\RouteLeg;
use App\Support\Polyline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trajeto pelas ruas da posição atual até a parada, com instruções em português (GPS do app).
 *
 * @property RouteLeg $resource
 */
class DirectionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'distance_km' => round($this->resource->distanceM / 1000, 1),
            'duration_min' => $this->resource->durationS === null ? null : (int) ceil($this->resource->durationS / 60),
            'path' => Polyline::encode($this->resource->path),
            'steps' => $this->resource->steps,
        ];
    }
}
