<?php

namespace App\Services\Geocoding;

use App\Models\GeocodingRequest;
use App\Services\Billing\PlanLimits;
use Illuminate\Support\Carbon;

/**
 * Monta o geocoder da plataforma (conta única, spec 005) e controla a cota mensal de cada empresa
 * e o teto diário da conta da plataforma.
 */
class GeocoderFactory
{
    public function forTenant(?int $tenantId): Geocoder
    {
        return match ($this->provider()) {
            'google' => new GoogleGeocoder(config('services.google_maps.server_key'), $tenantId),
            default => new GeoapifyGeocoder(config('services.geocoding.geoapify_key'), $tenantId),
        };
    }

    public function provider(): string
    {
        return (string) config('services.geocoding.provider', GeoapifyGeocoder::PROVIDER);
    }

    public function isConfigured(): bool
    {
        return filled($this->provider() === 'google'
            ? config('services.google_maps.server_key')
            : config('services.geocoding.geoapify_key'));
    }

    /**
     * Cota do plano da empresa (spec 007); sem empresa, ou com plano sem cota, vale a configuração.
     */
    public function monthlyQuota(?int $tenantId = null): int
    {
        $planQuota = $tenantId === null ? null : app(PlanLimits::class)->limit($tenantId, 'geocoding_per_month');

        return $planQuota ?? (int) config('services.geocoding.monthly_quota_per_tenant');
    }

    public function usedThisMonth(?int $tenantId, ?Carbon $now = null): int
    {
        if ($tenantId === null) {
            return 0;
        }

        return GeocodingRequest::query()
            ->withoutGlobalScopes()
            ->where('user_id', $tenantId)
            ->where('billable', true)
            ->inMonth($now ?? now())
            ->count();
    }

    public function hasQuotaFor(?int $tenantId): bool
    {
        return $this->usedThisMonth($tenantId) < $this->monthlyQuota($tenantId);
    }

    /**
     * Teto diário da conta da plataforma, somando todas as empresas; 0 desliga o teto.
     */
    public function dailyLimit(): int
    {
        return (int) config('services.geocoding.daily_limit');
    }

    public function usedToday(?Carbon $now = null): int
    {
        $now ??= now();

        return GeocodingRequest::query()
            ->withoutGlobalScopes()
            ->where('billable', true)
            ->whereBetween('created_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->count();
    }

    public function hasDailyCapacity(): bool
    {
        return $this->dailyLimit() <= 0 || $this->usedToday() < $this->dailyLimit();
    }
}
