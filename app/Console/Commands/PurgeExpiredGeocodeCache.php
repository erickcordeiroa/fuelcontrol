<?php

namespace App\Console\Commands;

use App\Enums\GeocodeStatus;
use App\Models\Customer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('geocoding:purge-expired')]
#[Description('Apaga lat/lng do Google com mais de 30 dias (termos do Google Maps Platform). Mantém place_id e coordenadas próprias.')]
class PurgeExpiredGeocodeCache extends Command
{
    public function handle(): int
    {
        $expiredBefore = now()->subDays(Customer::GOOGLE_CACHE_DAYS);
        $purged = 0;

        Customer::query()
            ->withoutGlobalScopes()
            ->whereNotNull('geocoded_at')
            ->where('geocoded_at', '<', $expiredBefore)
            ->chunkById(500, function ($customers) use (&$purged): void {
                foreach ($customers as $customer) {
                    $hasOwnCoordinates = $customer->confirmed_latitude !== null && $customer->confirmed_longitude !== null;

                    $customer->forceFill([
                        'latitude' => null,
                        'longitude' => null,
                        'location_type' => null,
                        'geocoded_at' => null,
                        'geocode_status' => $hasOwnCoordinates ? $customer->geocode_status : GeocodeStatus::Pending,
                    ])->save();

                    $purged++;
                }
            });

        $this->components->info(__(':count cliente(s) com cache do Google expirado foram limpos.', ['count' => $purged]));

        return self::SUCCESS;
    }
}
