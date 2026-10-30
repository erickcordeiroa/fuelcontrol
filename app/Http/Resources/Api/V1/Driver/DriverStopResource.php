<?php

namespace App\Http\Resources\Api\V1\Driver;

use App\Models\RouteStop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Parada como o app do motorista mostra (spec 004): dados da entrega, do cliente e se aceita baixa agora.
 *
 * @mixin RouteStop
 */
class DriverStopResource extends JsonResource
{
    public function __construct(RouteStop $resource, private readonly bool $isNext = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $delivery = $this->delivery;
        $customer = $delivery->customer;
        $coordinates = $customer->coordinates();
        $destination = $coordinates !== null ? $coordinates['lat'].','.$coordinates['lng'] : $customer->fullAddress();

        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'is_next' => $this->isNext,
            'distance_from_previous_km' => $this->distance_from_previous_m === null ? null : round($this->distance_from_previous_m / 1000, 1),
            'duration_from_previous_min' => $this->duration_from_previous_s === null ? null : (int) ceil($this->duration_from_previous_s / 60),
            'status' => ['value' => $delivery->status->value, 'label' => $delivery->status->label()],
            'completed_at' => $this->arrived_at?->toIso8601String(),
            'failure_reason' => $delivery->failure_reason_code === null ? null : [
                'value' => $delivery->failure_reason_code->value,
                'label' => $delivery->failure_reason_code->label(),
            ],
            'failure_note' => $delivery->failure_note,
            'order' => [
                'number' => $delivery->order_number,
                'amount' => $delivery->amount,
                'window_start' => $delivery->window_start === null ? null : substr((string) $delivery->window_start, 0, 5),
                'window_end' => $delivery->window_end === null ? null : substr((string) $delivery->window_end, 0, 5),
            ],
            'customer' => [
                'name' => $customer->name,
                'street' => $customer->street,
                'number' => $customer->number,
                'district' => $customer->district,
                'city' => $customer->city,
                'state' => $customer->state,
                'reference' => $customer->reference,
                'phone' => $customer->phone,
                'latitude' => $coordinates['lat'] ?? null,
                'longitude' => $coordinates['lng'] ?? null,
            ],
            'navigation' => [
                'path' => $this->path_polyline,
                'steps' => $this->navigation_steps ?? [],
                'google_maps' => 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($destination),
                'waze' => $coordinates !== null
                    ? 'https://waze.com/ul?ll='.rawurlencode($destination).'&navigate=yes'
                    : 'https://waze.com/ul?q='.rawurlencode($destination).'&navigate=yes',
            ],
        ];
    }
}
