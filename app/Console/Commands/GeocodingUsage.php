<?php

namespace App\Console\Commands;

use App\Models\GeocodingRequest;
use App\Models\User;
use App\Services\Geocoding\GeocodingCostEstimator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('geocoding:usage {--month= : Mês no formato AAAA-MM (padrão: mês atual)}')]
#[Description('Mostra as consultas ao Google Geocoding do mês, de todas as empresas, e a estimativa da fatura.')]
class GeocodingUsage extends Command
{
    public function handle(): int
    {
        $month = $this->resolveMonth();

        if ($month === null) {
            $this->components->error(__('Mês inválido. Use o formato AAAA-MM.'));

            return self::FAILURE;
        }

        $estimator = GeocodingCostEstimator::fromConfig();

        $byTenant = GeocodingRequest::query()
            ->withoutGlobalScopes()
            ->addressLookups()
            ->inMonth($month)
            ->selectRaw('user_id, sum(case when billable then 1 else 0 end) as billable_count, sum(case when billable then 0 else 1 end) as other_count')
            ->groupBy('user_id')
            ->get();

        $billable = (int) $byTenant->sum('billable_count');
        $isCurrentMonth = $month->isSameMonth(now());

        $tenantNames = User::query()->whereKey($byTenant->pluck('user_id')->filter())->pluck('company_name', 'id');

        $this->components->info(__('Google Geocoding — :month', ['month' => $month->format('m/Y')]));

        $this->table(
            [__('Empresa'), __('Cobráveis'), __('Não cobradas')],
            $byTenant->map(fn (GeocodingRequest $row): array => [
                $row->user_id === null ? __('(sem empresa)') : ($tenantNames[$row->user_id] ?? '#'.$row->user_id),
                number_format((int) $row->billable_count, 0, ',', '.'),
                number_format((int) $row->other_count, 0, ',', '.'),
            ])->all(),
        );

        $this->components->twoColumnDetail(__('Consultas cobráveis no mês'), number_format($billable, 0, ',', '.'));
        $this->components->twoColumnDetail(__('Faixa grátis restante'), number_format($estimator->freeRemaining($billable), 0, ',', '.'));
        $this->components->twoColumnDetail(__('Custo até agora'), 'US$ '.number_format($estimator->costFor($billable), 2, ',', '.'));

        if ($isCurrentMonth) {
            $projected = $estimator->projectMonth($billable, now());
            $this->components->twoColumnDetail(__('Projeção do mês'), number_format($projected, 0, ',', '.').' · US$ '.number_format($estimator->costFor($projected), 2, ',', '.'));
        }

        return self::SUCCESS;
    }

    private function resolveMonth(): ?Carbon
    {
        $option = $this->option('month');

        if ($option === null) {
            return now()->startOfMonth();
        }

        if (! is_string($option) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $option) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $option.'-01')->startOfDay();
    }
}
