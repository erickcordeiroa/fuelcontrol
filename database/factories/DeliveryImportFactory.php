<?php

namespace Database\Factories;

use App\Enums\DeliveryImportStatus;
use App\Models\DeliveryImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryImport>
 */
class DeliveryImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'file_name' => 'pedidos-'.fake()->date('Y-m-d').'.xlsx',
            'scheduled_for' => now()->toDateString(),
            'status' => DeliveryImportStatus::Completed,
            'total_rows' => 0,
            'imported_rows' => 0,
            'failed_rows' => 0,
            'errors' => [],
        ];
    }
}
