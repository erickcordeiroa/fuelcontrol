<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';

    case Routed = 'routed';

    case InTransit = 'in_transit';

    case Delivered = 'delivered';

    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pendente'),
            self::Routed => __('Roteirizada'),
            self::InTransit => __('Em rota'),
            self::Delivered => __('Entregue'),
            self::Failed => __('Não entregue'),
        };
    }

    /**
     * Cor da marca usada no mapa e nas pills.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => '#6B6560',
            self::Routed => '#0061FE',
            self::InTransit => '#7A5A00',
            self::Delivered => '#175641',
            self::Failed => '#B42318',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Delivered, self::Failed], true);
    }
}
