<?php

namespace App\Services\Billing;

use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\GeocodingRequest;
use App\Models\Subscription;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Plano da empresa, uso de cada limite e bloqueios (spec 007).
 *
 * @phpstan-type Plan array{key: string, name: string, description: string, price_cents: int|null, limits: array<string, int|null>, features: array<string, bool>}
 */
class PlanLimits
{
    public const LIMITS = ['vehicles', 'drivers', 'routes_per_month', 'geocoding_per_month'];

    /** A partir de quanto uso (em %) a empresa é avisada. */
    public const WARNING_PERCENT = 80;

    /**
     * @return Collection<string, Plan>
     */
    public function plans(): Collection
    {
        return collect(config('plans.plans'))->map(fn (array $plan, string $key): array => ['key' => $key, ...$plan]);
    }

    /**
     * Planos oferecidos ao público (home e troca pela tela de Assinatura).
     *
     * @return Collection<string, Plan>
     */
    public function publicPlans(): Collection
    {
        return $this->plans()->filter(fn (array $plan): bool => (bool) ($plan['public'] ?? true));
    }

    /**
     * @return Plan
     */
    public function planFor(int $tenantId): array
    {
        $key = $this->subscriptionFor($tenantId)?->plan;

        return $this->plans()->get($key ?? config('plans.default')) ?? $this->plans()->get(config('plans.default'));
    }

    public function subscriptionFor(int $tenantId): ?Subscription
    {
        return Subscription::query()->withoutGlobalScopes()->where('user_id', $tenantId)->first();
    }

    public function limit(int $tenantId, string $limit): ?int
    {
        $custom = $this->subscriptionFor($tenantId)?->custom_limits ?? [];

        return array_key_exists($limit, $custom) ? $custom[$limit] : ($this->planFor($tenantId)['limits'][$limit] ?? null);
    }

    public function usage(int $tenantId, string $limit): int
    {
        return match ($limit) {
            'vehicles' => Vehicle::query()->withoutGlobalScopes()->where('user_id', $tenantId)->count(),
            'drivers' => Driver::query()->withoutGlobalScopes()->where('user_id', $tenantId)->count(),
            'routes_per_month' => DeliveryRoute::query()->withoutGlobalScopes()->where('user_id', $tenantId)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'geocoding_per_month' => GeocodingRequest::query()->withoutGlobalScopes()->where('user_id', $tenantId)
                ->where('billable', true)->inMonth(now())->count(),
            default => throw new InvalidArgumentException("Limite desconhecido: {$limit}"),
        };
    }

    public function canAdd(int $tenantId, string $limit, int $amount = 1): bool
    {
        $max = $this->limit($tenantId, $limit);

        return $max === null || $this->usage($tenantId, $limit) + $amount <= $max;
    }

    public function allows(int $tenantId, string $feature): bool
    {
        return (bool) ($this->planFor($tenantId)['features'][$feature] ?? false);
    }

    /**
     * Mensagem mostrada quando um limite impede a ação.
     */
    public function blockedMessage(int $tenantId, string $limit): string
    {
        return __('Seu plano ":plan" permite :max :what. Mude de plano em Assinatura para continuar.', [
            'plan' => $this->planFor($tenantId)['name'],
            'max' => $this->limit($tenantId, $limit),
            'what' => self::label($limit),
        ]);
    }

    /**
     * @return list<array{limit: string, label: string, used: int, max: int|null, percent: int}>
     */
    public function usageSummary(int $tenantId): array
    {
        return collect(self::LIMITS)->map(function (string $limit) use ($tenantId): array {
            $max = $this->limit($tenantId, $limit);
            $used = $this->usage($tenantId, $limit);

            return [
                'limit' => $limit,
                'label' => self::label($limit),
                'used' => $used,
                'max' => $max,
                'percent' => $max === null || $max === 0 ? 0 : (int) min(100, round($used / $max * 100)),
            ];
        })->all();
    }

    /**
     * @return list<array{limit: string, label: string, used: int, max: int|null, percent: int}>
     */
    public function warnings(int $tenantId): array
    {
        return array_values(array_filter($this->usageSummary($tenantId), fn (array $row): bool => $row['max'] !== null && $row['percent'] >= self::WARNING_PERCENT));
    }

    /**
     * Troca o plano na hora. Até a cobrança existir (spec 008), não há etapa de pagamento.
     * Rebaixar não apaga dados: só bloqueia novos cadastros acima do limite.
     */
    public function changePlan(int $tenantId, string $planKey): Subscription
    {
        if (! $this->plans()->has($planKey)) {
            throw new InvalidArgumentException("Plano desconhecido: {$planKey}");
        }

        $subscription = $this->subscriptionFor($tenantId) ?? new Subscription(['user_id' => $tenantId]);
        $subscription->fill([
            'plan' => $planKey,
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ])->save();

        return $subscription;
    }

    public static function label(string $limit): string
    {
        return match ($limit) {
            'vehicles' => __('veículos'),
            'drivers' => __('motoristas'),
            'routes_per_month' => __('rotas por mês'),
            'geocoding_per_month' => __('buscas de endereço por mês'),
            default => $limit,
        };
    }

    public static function formatPrice(?int $cents): string
    {
        return $cents === null ? __('Sob consulta') : 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
