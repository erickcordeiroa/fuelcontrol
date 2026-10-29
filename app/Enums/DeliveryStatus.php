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
            self::Pending => '#94a3b8',
            self::Routed => '#002B5C',
            self::InTransit => '#F97316',
            self::Delivered => '#10b981',
            self::Failed => '#dc2626',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Delivered, self::Failed], true);
    }
}
