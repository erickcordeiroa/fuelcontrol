<?php

namespace App\Http\Requests\Api\V1\Driver;

/**
 * Posição opcional enviada pelo app junto com a ação (início da rota e baixas).
 */
trait ValidatesPosition
{
    /**
     * @return array<string, list<string>>
     */
    protected function positionRules(): array
    {
        return [
            'position' => ['nullable', 'array'],
            'position.latitude' => ['required_with:position', 'numeric', 'between:-90,90'],
            'position.longitude' => ['required_with:position', 'numeric', 'between:-180,180'],
            'position.accuracy_m' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array{latitude: float, longitude: float, accuracy_m: int|null}|null
     */
    public function position(): ?array
    {
        if (! $this->filled('position.latitude') || ! $this->filled('position.longitude')) {
            return null;
        }

        return [
            'latitude' => (float) $this->input('position.latitude'),
            'longitude' => (float) $this->input('position.longitude'),
            'accuracy_m' => $this->filled('position.accuracy_m') ? (int) $this->input('position.accuracy_m') : null,
        ];
    }
}
