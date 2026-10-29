<?php

namespace Tests\Feature;

use App\Livewire\Deliveries\DeliveryPlanner;
use App\Livewire\Drivers\DriverIndex;
use App\Livewire\Subscription\SubscriptionSettings;
use App\Livewire\Vehicles\VehicleIndex;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Billing\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_new_company_starts_on_the_initial_plan(): void
    {
        $plan = app(PlanLimits::class)->planFor($this->admin->id);

        $this->assertSame('inicial', $plan['key']);
        $this->assertSame(2, app(PlanLimits::class)->limit($this->admin->id, 'vehicles'));
    }

    public function test_vehicle_limit_blocks_the_third_vehicle(): void
    {
        Vehicle::factory()->count(2)->create(['user_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(VehicleIndex::class)
            ->call('openCreateModal')
            ->assertSet('showModal', false)
            ->assertDispatched('plan-limit-reached', fn (string $event, array $params): bool => str_contains($params['message'], '2 veículos'));
    }

    public function test_driver_limit_blocks_new_drivers(): void
    {
        Driver::factory()->count(2)->create(['user_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(DriverIndex::class)
            ->call('openCreateModal')
            ->assertSet('showModal', false)
            ->assertDispatched('plan-limit-reached');
    }

    public function test_monthly_route_limit_blocks_route_creation(): void
    {
        config(['plans.plans.inicial.limits.routes_per_month' => 1]);
        DeliveryRoute::factory()->create(['user_id' => $this->admin->id]);
        $delivery = Delivery::factory()->forTenant($this->admin->id)->create([
            'customer_id' => Customer::factory()->confirmed()->create(['user_id' => $this->admin->id])->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->set('selected', [$delivery->id])
            ->set('routeName', 'Setor Sul')
            ->call('createRoute')
            ->assertDispatched('plan-limit-reached');

        $this->assertSame(1, DeliveryRoute::query()->withoutGlobalScopes()->count());
    }

    public function test_changing_plan_raises_limits_and_is_audited(): void
    {
        Vehicle::factory()->count(2)->create(['user_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(SubscriptionSettings::class)
            ->assertSee('Até 2 veículos')
            ->call('changePlan', 'profissional')
            ->assertSee('Plano alterado para');

        $this->assertSame('profissional', Subscription::query()->withoutGlobalScopes()->sole()->plan);
        $this->assertTrue(app(PlanLimits::class)->canAdd($this->admin->id, 'vehicles'));
        $this->assertSame('Subscription', AuditLog::query()->withoutGlobalScopes()->latest('id')->first()->metadata['model']);
    }

    public function test_downgrade_keeps_data_and_warns(): void
    {
        Subscription::factory()->forTenant($this->admin->id)->plan('profissional')->create();
        Vehicle::factory()->count(5)->create(['user_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(SubscriptionSettings::class)
            ->assertSee('Você tem mais veículos do que este plano permite')
            ->call('changePlan', 'inicial');

        $this->assertSame(5, Vehicle::query()->withoutGlobalScopes()->count());
        $this->assertFalse(app(PlanLimits::class)->canAdd($this->admin->id, 'vehicles'));
    }

    public function test_plan_on_request_cannot_be_self_selected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SubscriptionSettings::class)
            ->call('changePlan', 'empresa')
            ->assertSee('Este plano é contratado com a nossa equipe.');

        $this->assertNull(Subscription::query()->withoutGlobalScopes()->first());
    }

    public function test_custom_limits_override_the_plan(): void
    {
        Subscription::factory()->forTenant($this->admin->id)->plan('empresa')->create(['custom_limits' => ['vehicles' => 40]]);

        $this->assertSame(40, app(PlanLimits::class)->limit($this->admin->id, 'vehicles'));
        $this->assertNull(app(PlanLimits::class)->limit($this->admin->id, 'drivers'));
    }

    public function test_report_export_requires_a_plan_with_export(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.export', ['start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->toDateString(), 'format' => 'csv']))
            ->assertForbidden();
    }

    public function test_layout_warns_at_80_percent_of_a_limit(): void
    {
        config(['plans.plans.inicial.limits.vehicles' => 5]);
        Vehicle::factory()->count(4)->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get(route('reports'))
            ->assertOk()
            ->assertSee('Seu plano está perto do limite');
    }

    public function test_subscription_page_is_admin_only_and_in_the_menu(): void
    {
        $this->actingAs(User::factory()->driverRole()->create())->get(route('subscription.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('subscription.index'))->assertOk()->assertSee('Uso do plano');
    }
}
