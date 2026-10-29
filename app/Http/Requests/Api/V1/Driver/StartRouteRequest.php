<?php

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StartRouteRequest extends FormRequest
{
    use ValidatesPosition;

    public function authorize(): bool
    {
        return $this->user()->can('drive', $this->route('deliveryRoute'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->positionRules();
    }
}
