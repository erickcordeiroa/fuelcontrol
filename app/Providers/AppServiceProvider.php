<?php

namespace App\Providers;

use App\Jobs\GeocodeCustomer;
use App\View\Composers\OilChangeAlertComposer;
use App\View\Composers\PlanUsageComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', OilChangeAlertComposer::class);
        View::composer('layouts.app', PlanUsageComposer::class);

        /** Conta única da plataforma: um limite geral (o do provedor) e um por empresa, para uma não travar as outras. */
        RateLimiter::for('geocoding', fn (GeocodeCustomer $job): array => [
            Limit::perMinute(config('services.geocoding.per_minute'))->by('platform'),
            Limit::perMinute(config('services.geocoding.per_minute_per_tenant'))->by('tenant:'.($job->customer->user_id ?? 'none')),
        ]);
    }
}
