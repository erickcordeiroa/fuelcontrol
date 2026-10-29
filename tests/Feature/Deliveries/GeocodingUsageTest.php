<?php

namespace Tests\Feature\Deliveries;

use App\Livewire\Integrations\IntegrationSettings;
use App\Models\GeocodingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GeocodingUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_integrations_page_shows_only_this_tenants_usage_for_the_current_month(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        GeocodingRequest::factory()->forTenant($admin->id)->count(3)->create();
        GeocodingRequest::factory()->forTenant($admin->id)->notBillable()->create();
        GeocodingRequest::factory()->forTenant($admin->id)->create(['created_at' => now()->subMonth()]);
        GeocodingRequest::factory()->forTenant($otherAdmin->id)->count(5)->create();

        Livewire::actingAs($admin)
            ->test(IntegrationSettings::class)
            ->assertViewHas('currentMonth', fn (array $usage): bool => $usage['billable'] === 3
                && $usage['notBilled'] === 1
                && $usage['cost'] === 0.0
                && $usage['projected'] === 7)
            ->assertViewHas('history', fn ($history): bool => $history->first()['billable'] === 1)
            ->assertSee('Consumo do Google');
    }

    public function test_command_reports_all_tenants_and_estimated_cost(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 31));
        $admin = User::factory()->admin()->create(['company_name' => 'Distribuidora Registro']);

        GeocodingRequest::factory()->forTenant($admin->id)->count(2)->create();
        GeocodingRequest::factory()->notBillable('HTTP_500')->create();

        $this->artisan('geocoding:usage')
            ->expectsOutputToContain('Distribuidora Registro')
            ->expectsOutputToContain('(sem empresa)')
            ->expectsOutputToContain('US$ 0,00')
            ->assertSuccessful();
    }

    public function test_command_rejects_invalid_month(): void
    {
        $this->artisan('geocoding:usage', ['--month' => '2026-13'])->assertFailed();
    }

    public function test_command_accepts_a_past_month(): void
    {
        $this->artisan('geocoding:usage', ['--month' => '2026-09'])->assertSuccessful();
    }
}
