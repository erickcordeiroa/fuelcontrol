<?php

namespace App\Http\Requests\Reports;

use App\Models\DeliveryRoute;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeliveryReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', DeliveryRoute::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'driver_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'start_date' => __('De'),
            'end_date' => __('Até'),
            'driver_id' => __('Motorista'),
        ];
    }

    public function driverId(): ?int
    {
        $driverId = $this->validated('driver_id');

        return $driverId !== null ? (int) $driverId : null;
    }
}
