<?php

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreDriverLocationsRequest extends FormRequest
{
    /** Posições guardadas sem sinal por até um dia de rota (uma a cada 10 minutos). */
    private const MAX_POSITIONS = 200;

    public function authorize(): bool
    {
        return $this->user()->can('drive', $this->route('deliveryRoute'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'positions' => ['required', 'array', 'min:1', 'max:'.self::MAX_POSITIONS],
            'positions.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'positions.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'positions.*.accuracy_m' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'positions.*.speed_kmh' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'positions.*.recorded_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return list<array{latitude: float, longitude: float, accuracy_m: int|null, speed_kmh: float|null, recorded_at: Carbon}>
     */
    public function positions(): array
    {
        return collect($this->validated('positions'))
            ->map(fn (array $position): array => [
                'latitude' => (float) $position['latitude'],
                'longitude' => (float) $position['longitude'],
                'accuracy_m' => isset($position['accuracy_m']) ? (int) $position['accuracy_m'] : null,
                'speed_kmh' => isset($position['speed_kmh']) ? (float) $position['speed_kmh'] : null,
                'recorded_at' => Carbon::parse($position['recorded_at']),
            ])
            ->sortBy('recorded_at')
            ->values()
            ->all();
    }
}
