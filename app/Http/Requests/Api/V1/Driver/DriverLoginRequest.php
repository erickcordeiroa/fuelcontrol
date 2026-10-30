<?php

namespace App\Http\Requests\Api\V1\Driver;

use App\Models\Driver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DriverLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32', function (string $attribute, mixed $value, \Closure $fail): void {
                $length = strlen((string) Driver::normalizePhone((string) $value));

                if ($length < 10 || $length > 11) {
                    $fail(__('Digite o celular com DDD, por exemplo (13) 99722-1580.'));
                }
            }],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function phoneDigits(): string
    {
        return (string) Driver::normalizePhone($this->string('phone')->toString());
    }
}
