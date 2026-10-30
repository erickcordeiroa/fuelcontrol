<?php

namespace App\Http\Requests\Api\V1\Driver;

use App\Enums\DeliveryFailureReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class FailStopRequest extends DeliverStopRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'reason' => ['required', Rule::enum(DeliveryFailureReason::class)],
            'note' => [Rule::requiredIf($this->input('reason') === DeliveryFailureReason::Other->value), 'nullable', 'string', 'min:3', 'max:140'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('Escolha o motivo da não entrega.'),
            'note.required' => __('Descreva o motivo.'),
        ];
    }

    public function reason(): DeliveryFailureReason
    {
        return DeliveryFailureReason::from($this->string('reason')->toString());
    }
}
