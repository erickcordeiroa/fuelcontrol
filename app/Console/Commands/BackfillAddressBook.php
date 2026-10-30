<?php

namespace App\Console\Commands;

use App\Enums\GeocodeStatus;
use App\Models\Customer;
use App\Models\GeocodedAddress;
use App\Services\Geocoding\AddressBook;
use App\Services\Geocoding\GeocodeResult;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('geocoding:backfill-address-book')]
#[Description('Carrega na base de endereços as coordenadas que os clientes já têm (confirmadas ou de provedor que permite guardar). Coordenadas do Google ficam de fora.')]
class BackfillAddressBook extends Command
{
    public function handle(AddressBook $addressBook): int
    {
        $loaded = 0;

        Customer::query()
            ->withoutGlobalScopes()
            ->where(fn ($query) => $query->whereNotNull('confirmed_latitude')->orWhere(fn ($query) => $query
                ->whereNotNull('latitude')
                ->where('geocode_provider', '!=', 'google')))
            ->chunkById(500, function ($customers) use ($addressBook, &$loaded): void {
                foreach ($customers as $customer) {
                    if ($customer->confirmed_latitude !== null && $customer->confirmed_longitude !== null) {
                        $addressBook->rememberConfirmed(
                            $customer,
                            $customer->confirmed_latitude,
                            $customer->confirmed_longitude,
                            $customer->geocode_status === GeocodeStatus::Manual ? GeocodedAddress::SOURCE_MANUAL : GeocodedAddress::SOURCE_GPS,
                        );
                    } else {
                        $addressBook->rememberResult(
                            $customer,
                            new GeocodeResult($customer->latitude, $customer->longitude, null, $customer->location_type ?? 'APPROXIMATE'),
                            (string) $customer->geocode_provider,
                        );
                    }

                    $loaded++;
                }
            });

        $this->components->info(__(':count endereço(s) carregado(s) na base.', ['count' => $loaded]));

        return self::SUCCESS;
    }
}
