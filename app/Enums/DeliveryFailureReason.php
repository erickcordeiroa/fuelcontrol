<?php

namespace App\Enums;

/**
 * Motivos de "Não entregue" escolhidos pelo motorista no app (spec 004).
 */
enum DeliveryFailureReason: string
{
    case CustomerAbsent = 'customer_absent';

    case Closed = 'closed';

    case Refused = 'refused';

    case AddressNotFound = 'address_not_found';

    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CustomerAbsent => __('Cliente ausente'),
            self::Closed => __('Estabelecimento fechado'),
            self::Refused => __('Recusou o pedido'),
            self::AddressNotFound => __('Endereço não encontrado'),
            self::Other => __('Outro motivo'),
        };
    }
}
