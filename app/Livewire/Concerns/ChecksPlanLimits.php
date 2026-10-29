<?php

namespace App\Livewire\Concerns;

use App\Services\Billing\PlanLimits;

/**
 * Bloqueio por limite do plano (spec 007): avisa o usuário e diz onde mudar de plano.
 */
trait ChecksPlanLimits
{
    protected function planAllowsAdding(string $limit): bool
    {
        $tenantId = auth()->user()->tenantOwnerId();
        $planLimits = app(PlanLimits::class);

        if ($planLimits->canAdd($tenantId, $limit)) {
            return true;
        }

        $this->dispatch('plan-limit-reached', message: $planLimits->blockedMessage($tenantId, $limit));

        return false;
    }
}
