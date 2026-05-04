<?php

namespace App\View\Composers;

use App\Services\OilChangeAlertService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OilChangeAlertComposer
{
    public function __construct(
        private OilChangeAlertService $oilChangeAlertService,
    ) {}

    public function compose(View $view): void
    {
        $alerts = Auth::check()
            ? $this->oilChangeAlertService->getAlerts()
            : collect();

        $view->with('oilChangeAlerts', $alerts);
    }
}
