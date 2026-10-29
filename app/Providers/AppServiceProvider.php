<?php

namespace App\Providers;

use App\Jobs\GeocodeCustomer;
use App\View\Composers\OilChangeAlertComposer;
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

        /** Cada empresa usa a própria chave do Google, então o limite é por empresa. */
        RateLimiter::for('google-geocoding', fn (GeocodeCustomer $job): Limit => Limit::perMinute(config('services.google_maps.geocoding_per_minute'))
            ->by('tenant:'.($job->customer->user_id ?? 'none')));
    }
}
