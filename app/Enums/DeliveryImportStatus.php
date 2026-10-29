<?php

namespace App\Enums;

enum DeliveryImportStatus: string
{
    case Completed = 'completed';

    case CompletedWithErrors = 'completed_with_errors';

    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Completed => __('Importada'),
            self::CompletedWithErrors => __('Importada com erros'),
            self::Failed => __('Falhou'),
        };
    }
}
