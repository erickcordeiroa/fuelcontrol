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
            self::Pending => '#94a3b8',
            self::Resolved => '#10b981',
            self::Approximate => '#F97316',
            self::Failed => '#dc2626',
            self::Manual => '#002B5C',
            self::Confirmed => '#166534',
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
