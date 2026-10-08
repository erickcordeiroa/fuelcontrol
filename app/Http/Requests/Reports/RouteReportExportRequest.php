<?php

namespace App\Http\Requests\Reports;

use App\Models\Trip;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RouteReportExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Trip::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'vehicle_ids' => ['nullable', 'array'],
            'vehicle_ids.*' => ['integer', 'min:1'],
            'driver_id' => ['nullable', 'integer', 'min:1'],
            'format' => ['nullable', 'in:pdf,csv'],
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
            'vehicle_ids' => __('Veículos'),
            'driver_id' => __('Motorista'),
        ];
    }

    /**
     * Vehicle filter is admin-only; drivers always see their own trips.
     *
     * @return list<int>
     */
    public function vehicleIds(): array
    {
        if (! $this->user()->isAdmin()) {
            return [];
        }

        return array_values(array_map('intval', (array) $this->validated('vehicle_ids', [])));
    }

    /**
     * Driver used to scope the report: admin's optional filter, or the logged driver's own record.
     */
    public function scopedDriverId(): ?int
    {
        $user = $this->user();

        if ($user->isAdmin()) {
            $driverId = $this->validated('driver_id');

            return $driverId !== null ? (int) $driverId : null;
        }

        return $user->driver?->id;
    }

    public function hasNoDriverScope(): bool
    {
        return ! $this->user()->isAdmin() && $this->user()->driver === null;
    }
}
