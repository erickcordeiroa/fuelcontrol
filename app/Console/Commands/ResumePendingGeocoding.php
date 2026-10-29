<?php

namespace App\Console\Commands;

use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeCustomer;
use App\Models\Customer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('geocoding:resume-pending')]
#[Description('Reenfileira a busca dos clientes que ficaram pendentes por teto diário ou cota mensal; roda todo dia logo após a virada.')]
class ResumePendingGeocoding extends Command
{
    public function handle(): int
    {
        $queued = 0;

        Customer::query()
            ->withoutGlobalScopes()
            ->where('geocode_status', GeocodeStatus::Pending)
            ->whereNotNull('geocode_error')
            ->chunkById(500, function ($customers) use (&$queued): void {
                foreach ($customers as $customer) {
                    if (! $customer->needsGeocoding()) {
                        continue;
                    }

                    GeocodeCustomer::dispatch($customer);
                    $queued++;
                }
            });

        $this->components->info(__(':count cliente(s) pendente(s) voltaram para a fila de busca.', ['count' => $queued]));

        return self::SUCCESS;
    }
}
