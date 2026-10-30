<?php

namespace App\Enums;

enum GeocodeStatus: string
{
    /** Aguardando o job de geocodificação. */
    case Pending = 'pending';

    /** Google devolveu precisão de número (ROOFTOP). */
    case Resolved = 'resolved';

    /** Google devolveu só rua, bairro ou interpolação: precisa de revisão. */
    case Approximate = 'approximate';

    /** Sem resultado ou erro na API: precisa de revisão. */
    case Failed = 'failed';

    /** Posição definida pelo admin ou vinda da planilha. */
    case Manual = 'manual';

    /** GPS do motorista na baixa: coordenada permanente. */
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Aguardando'),
            self::Resolved => __('Localizado'),
            self::Approximate => __('Aproximado'),
            self::Failed => __('Não localizado'),
            self::Manual => __('Ajustado'),
            self::Confirmed => __('Confirmado em campo'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => '#6B6560',
            self::Resolved => '#0061FE',
            self::Approximate => '#7A5A00',
            self::Failed => '#B42318',
            self::Manual => '#1E1919',
            self::Confirmed => '#175641',
        };
    }

    public function needsReview(): bool
    {
        return in_array($this, [self::Approximate, self::Failed], true);
    }

    public function isPermanent(): bool
    {
        return in_array($this, [self::Manual, self::Confirmed], true);
    }
}
