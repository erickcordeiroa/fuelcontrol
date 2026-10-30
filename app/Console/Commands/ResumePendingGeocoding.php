<?php

namespace App\Console\Commands;

use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeCustomer;
use App\Models\Customer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('geocoding:resume-pending')]
#[Description('Reenfileira a busca dos clientes pendentes: os que pararam no teto diário ou na cota do mês e os que estão esperando há mais de algum tempo (job perdido). Roda a cada 15 minutos.')]
class ResumePendingGeocoding extends Command
{
    public function handle(): int
    {
        $queued = 0;

        Customer::query()
            ->withoutGlobalScopes()
            ->where('geocode_status', GeocodeStatus::Pending)
            ->where(fn ($query) => $query
                ->whereNotNull('geocode_error')
                ->orWhere('updated_at', '<', now()->subMinutes(Customer::GEOCODE_STALE_MINUTES)))
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
