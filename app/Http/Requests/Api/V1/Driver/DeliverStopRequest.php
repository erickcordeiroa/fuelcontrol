<?php

namespace App\Http\Requests\Api\V1\Driver;

use App\Models\RouteStop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class DeliverStopRequest extends FormRequest
{
    use ValidatesPosition;

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
            'client_uuid' => ['required', 'uuid'],
            'completed_at' => ['nullable', 'date', 'before_or_equal:now'],
            ...$this->positionRules(),
        ];
    }

    public function stop(): RouteStop
    {
        return $this->route('stop');
    }

    /**
     * Horário da baixa no celular; vale o do aparelho para baixas guardadas sem sinal.
     */
    public function completedAt(): ?Carbon
    {
        return $this->filled('completed_at') ? Carbon::parse($this->input('completed_at')) : null;
    }
}
