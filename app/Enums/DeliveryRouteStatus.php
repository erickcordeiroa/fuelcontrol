<?php

namespace App\Enums;

enum DeliveryRouteStatus: string
{
    case Planned = 'planned';

    case InProgress = 'in_progress';

    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Planned => __('Planejada'),
            self::InProgress => __('Em andamento'),
            self::Completed => __('Concluída'),
        };
    }
}
