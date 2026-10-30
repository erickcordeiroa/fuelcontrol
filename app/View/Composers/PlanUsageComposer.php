<?php

namespace App\View\Composers;

use App\Services\Billing\PlanLimits;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Avisa o administrador quando algum limite do plano passa de 80% (spec 007).
 */
class PlanUsageComposer
{
    public function __construct(private PlanLimits $planLimits) {}

    public function compose(View $view): void
    {
        $user = Auth::user();

        $view->with('planWarnings', $user?->isAdmin() ? $this->planLimits->warnings($user->tenantOwnerId()) : []);
    }
}
