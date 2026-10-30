<?php

namespace App\Livewire\Subscription;

use App\Livewire\Concerns\SendsToasts;
use App\Services\Billing\PlanLimits;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Plano da empresa, uso dos limites e troca de plano (spec 007). Pagamento entra na spec 008.
 */
#[Layout('layouts.app')]
#[Title('Assinatura')]
class SubscriptionSettings extends Component
{
    use SendsToasts;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function changePlan(string $planKey, PlanLimits $planLimits): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $plan = $planLimits->plans()->get($planKey);

        if ($plan === null || $plan['price_cents'] === null || ! ($plan['public'] ?? true)) {
            $this->toastError(__('Este plano é contratado com a nossa equipe.'));

            return;
        }

        $planLimits->changePlan($this->tenantId(), $planKey);

        $this->toast(__('Plano alterado para ":plan".', ['plan' => $plan['name']]));
    }

    public function render(PlanLimits $planLimits): View
    {
        $tenantId = $this->tenantId();
        $current = $planLimits->planFor($tenantId);

        return view('livewire.subscription.subscription-settings', [
            'current' => $current,
            'subscription' => $planLimits->subscriptionFor($tenantId),
            'usage' => $planLimits->usageSummary($tenantId),
            'plans' => $planLimits->plans()
                ->filter(fn (array $plan): bool => ($plan['public'] ?? true) || $plan['key'] === $current['key'])
                ->map(fn (array $plan): array => [
                    ...$plan,
                    'isCurrent' => $plan['key'] === $current['key'],
                    'overLimits' => $this->overLimits($planLimits, $tenantId, $plan),
                ]),
        ]);
    }

    /**
     * Limites que a empresa já ultrapassa no plano indicado (aviso ao rebaixar).
     *
     * @param  array{limits: array<string, int|null>}  $plan
     * @return list<string>
     */
    private function overLimits(PlanLimits $planLimits, int $tenantId, array $plan): array
    {
        return collect(['vehicles', 'drivers'])
            ->filter(fn (string $limit): bool => $plan['limits'][$limit] !== null && $planLimits->usage($tenantId, $limit) > $plan['limits'][$limit])
            ->map(fn (string $limit): string => PlanLimits::label($limit))
            ->values()
            ->all();
    }

    private function tenantId(): int
    {
        return auth()->user()->tenantOwnerId();
    }
}
