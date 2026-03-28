<?php

namespace App\Enums;

enum FuelType: string
{
    case GasolinaComum = 'gasolina_comum';

    case GasolinaAditivada = 'gasolina_aditivada';

    case GasolinaPremium = 'gasolina_premium';

    case Etanol = 'etanol';

    case DieselS10 = 'diesel_s10';

    case DieselS500 = 'diesel_s500';

    case Gnv = 'gnv';

    case Outro = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::GasolinaComum => __('Gasolina comum'),
            self::GasolinaAditivada => __('Gasolina aditivada'),
            self::GasolinaPremium => __('Gasolina premium'),
            self::Etanol => __('Etanol'),
            self::DieselS10 => __('Diesel S10'),
            self::DieselS500 => __('Diesel S500'),
            self::Gnv => __('GNV'),
            self::Outro => __('Outro'),
        };
    }

    /**
     * @return list<self>
     */
    public static function orderedCases(): array
    {
        return [
            self::GasolinaComum,
            self::GasolinaAditivada,
            self::GasolinaPremium,
            self::Etanol,
            self::DieselS10,
            self::DieselS500,
            self::Gnv,
            self::Outro,
        ];
    }
}
