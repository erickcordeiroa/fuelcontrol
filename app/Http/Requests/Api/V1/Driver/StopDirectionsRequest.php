<?php

namespace App\Http\Requests\Api\V1\Driver;

use App\Models\RouteStop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StopDirectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $route = $this->stop()->deliveryRoute;

        return $route !== null && $this->user()->can('drive', $route);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function stop(): RouteStop
    {
        return $this->route('stop');
    }

    /**
     * @return array{lat: float, lng: float}
     */
    public function from(): array
    {
        return ['lat' => (float) $this->input('latitude'), 'lng' => (float) $this->input('longitude')];
    }
}
