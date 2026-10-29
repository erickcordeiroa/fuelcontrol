<?php

namespace Tests\Feature\Deliveries;

use App\Enums\DeliveryStatus;
use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeCustomer;
use App\Livewire\Deliveries\DeliveryPlanner;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\RouteTemplate;
use App\Models\User;
use App\Services\Deliveries\DeliveryImporter;
use App\Services\Routing\DeliveryRoutePlanner;
use App\Services\Routing\RoutePlan;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class DeliveryPlannerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_opens_planner_and_sees_deliveries_of_the_day(): void
    {
        $delivery = $this->delivery(['order_number' => 'PED-777']);

        $this->actingAs($this->admin)
            ->get(route('deliveries.planner'))
            ->assertOk()
            ->assertSee('PED-777')
            ->assertSee($delivery->customer->name);
    }

    public function test_driver_cannot_open_route_pages(): void
    {
        $driverUser = User::factory()->driverRole()->create();

        $this->actingAs($driverUser)->get(route('deliveries.planner'))->assertForbidden();
        $this->actingAs($driverUser)->get(route('deliveries.monitor'))->assertForbidden();
        $this->actingAs($driverUser)->get(route('deliveries.template'))->assertForbidden();
    }

    public function test_template_download_is_an_xlsx_with_the_expected_columns(): void
    {
        $response = $this->actingAs($this->admin)->get(route('deliveries.template'));

        $response->assertOk()->assertDownload('modelo-pedidos.xlsx');
    }

    public function test_imports_spreadsheet_from_the_page(): void
    {
        Queue::fake();
        $content = $this->spreadsheetContent([
            ['C-1', 'PED-1', 'Mercearia', 'Rua 1', '10', 'Centro', 'Goiânia', 'GO', '74000000', '', '100,00', 'Sul', '', '', '', ''],
        ]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->set('spreadsheet', UploadedFile::fake()->createWithContent('pedidos.xlsx', $content))
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('spreadsheet', null)
            ->assertSee('PED-1');

        $this->assertSame(1, Delivery::query()->withoutGlobalScopes()->where('user_id', $this->admin->id)->count());
        Queue::assertPushed(GeocodeCustomer::class);
    }

    public function test_rejects_files_that_are_not_spreadsheets(): void
    {
        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->set('spreadsheet', UploadedFile::fake()->create('pedidos.pdf', 10))
            ->call('import')
            ->assertHasErrors(['spreadsheet']);
    }

    public function test_select_sector_picks_only_routable_deliveries(): void
    {
        $sul = $this->delivery(['sector' => 'Sul']);
        $this->delivery(['sector' => 'Norte']);
        $this->delivery(['sector' => 'Sul'], Customer::factory()->create(['user_id' => $this->admin->id]));

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('selectSector', 'Sul')
            ->assertSet('selected', [$sul->id])
            ->assertSet('routeName', 'Setor Sul');
    }

    public function test_creates_route_and_redirects_to_monitor(): void
    {
        $driver = Driver::factory()->create(['user_id' => $this->admin->id]);
        $first = $this->delivery();
        $second = $this->delivery();

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->set('selected', [$first->id, $second->id])
            ->set('routeName', 'Setor Sul')
            ->set('driverId', $driver->id)
            ->call('createRoute')
            ->assertHasNoErrors()
            ->assertRedirect(route('deliveries.monitor', ['data' => now()->toDateString(), 'rota' => DeliveryRoute::query()->withoutGlobalScopes()->value('id')]));

        $route = DeliveryRoute::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame($driver->id, $route->driver_id);
        $this->assertSame(2, $route->stops()->count());
        $this->assertSame(DeliveryStatus::Routed, $first->refresh()->status);
    }

    public function test_adds_selected_deliveries_to_an_existing_route(): void
    {
        $route = app(DeliveryRoutePlanner::class)->create(['name' => 'Setor Sul', 'scheduled_for' => now()->toDateString(), 'user_id' => $this->admin->id], collect([$this->delivery()]));
        $late = $this->delivery();

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->set('selected', [$late->id])
            ->assertSee('Incluir 1 pedido')
            ->call('addToRoute', $route->id)
            ->assertHasNoErrors()
            ->assertSet('selected', []);

        $this->assertSame(2, $route->stops()->count());
        $this->assertSame(DeliveryStatus::Routed, $late->refresh()->status);
    }

    public function test_messages_stay_on_screen_while_the_user_keeps_working(): void
    {
        $route = app(DeliveryRoutePlanner::class)->create(['name' => 'Setor Sul', 'scheduled_for' => now()->toDateString(), 'user_id' => $this->admin->id], collect([$this->delivery()]));
        $other = $this->delivery();

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('reoptimize', $route->id)
            ->assertSee('já está otimizada')
            ->set('selected', [$other->id])
            ->set('routeName', 'Setor Norte')
            ->assertSee('já está otimizada')
            ->call('dismissMessages')
            ->assertDontSee('já está otimizada');
    }

    public function test_reoptimize_is_skipped_when_nothing_changed(): void
    {
        config(['services.routing.provider' => 'local']);
        $route = app(DeliveryRoutePlanner::class)->create(['name' => 'Setor Sul', 'scheduled_for' => now()->toDateString(), 'user_id' => $this->admin->id], collect([$this->delivery()]));
        $optimizedAt = $route->optimized_at;
        $this->travel(1)->minute();

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->assertDontSee('Reotimizar')
            ->call('reoptimize', $route->id);

        $this->assertEquals($optimizedAt, $route->refresh()->optimized_at);
    }

    public function test_shows_the_route_base_before_creating_and_can_optimize_from_scratch(): void
    {
        $customer = Customer::factory()->confirmed()->create(['user_id' => $this->admin->id]);
        RouteTemplate::factory()->create(['user_id' => $this->admin->id, 'name' => 'Setor Sul', 'name_key' => 'setor sul', 'customer_ids' => [$customer->id]]);
        $delivery = $this->delivery([], $customer);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->set('selected', [$delivery->id])
            ->set('routeName', 'Setor Sul')
            ->assertSee('Usa a rota base salva.')
            ->assertSee('sem custo de otimização')
            ->set('optimizeFromScratch', true)
            ->assertSee('A ordem será otimizada do zero')
            ->set('optimizeFromScratch', false)
            ->call('createRoute')
            ->assertHasNoErrors();

        $this->assertSame(RoutePlan::ORDER_BASE, DeliveryRoute::query()->withoutGlobalScopes()->sole()->order_source);
    }

    public function test_cannot_assign_driver_from_another_tenant(): void
    {
        $foreignDriver = Driver::factory()->create(['user_id' => User::factory()->admin()->create()->id]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->set('selected', [$this->delivery()->id])
            ->set('routeName', 'Setor Sul')
            ->set('driverId', $foreignDriver->id)
            ->call('createRoute')
            ->assertHasErrors(['driverId']);

        $this->assertSame(0, DeliveryRoute::query()->withoutGlobalScopes()->count());
    }

    public function test_moving_a_pin_saves_a_permanent_manual_location(): void
    {
        $delivery = $this->delivery([], Customer::factory()->located()->create(['user_id' => $this->admin->id, 'geocode_status' => GeocodeStatus::Approximate]));

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('moveCustomer', $delivery->customer_id, -16.6801, -49.2502);

        $customer = $delivery->customer->refresh();
        $this->assertSame(GeocodeStatus::Manual, $customer->geocode_status);
        $this->assertSame(['lat' => -16.6801, 'lng' => -49.2502], $customer->coordinates());
    }

    public function test_cannot_move_customer_of_another_tenant(): void
    {
        $foreign = Customer::factory()->confirmed()->create(['user_id' => User::factory()->admin()->create()->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('moveCustomer', $foreign->id, -16.68, -49.25);
    }

    public function test_retry_geocoding_queues_the_job(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create(['user_id' => $this->admin->id, 'geocode_status' => GeocodeStatus::Failed, 'geocode_error' => 'Erro']);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('retryGeocoding', $customer->id);

        $this->assertSame(GeocodeStatus::Pending, $customer->refresh()->geocode_status);
        Queue::assertPushed(GeocodeCustomer::class, fn (GeocodeCustomer $job): bool => $job->customer->is($customer));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function test_deliveries_table_is_paginated(): void
    {
        foreach (range(1, 20) as $index) {
            $this->delivery(['order_number' => sprintf('PED-%03d', $index), 'sector' => 'Sul']);
        }

        $component = Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->assertViewHas('deliveries', fn ($deliveries): bool => $deliveries->total() === 20 && $deliveries->count() === 15);

        $component->call('gotoPage', 2)
            ->assertViewHas('deliveries', fn ($deliveries): bool => $deliveries->count() === 5);

        $component->set('perPage', 30)
            ->assertViewHas('deliveries', fn ($deliveries): bool => $deliveries->currentPage() === 1 && $deliveries->count() === 20);
    }

    public function test_invalid_per_page_falls_back_to_default(): void
    {
        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->set('perPage', 5000)
            ->assertSet('perPage', 15);
    }

    public function test_search_matches_order_number_and_customer(): void
    {
        $byOrder = $this->delivery(['order_number' => 'PED-ABC']);
        $byCustomer = $this->delivery([], Customer::factory()->confirmed()->create(['user_id' => $this->admin->id, 'name' => 'Padaria Aurora']));
        $other = $this->delivery(['order_number' => 'PED-XYZ']);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->set('search', 'ABC')
            ->assertViewHas('deliveries', fn ($deliveries): bool => $deliveries->pluck('id')->all() === [$byOrder->id])
            ->set('search', 'aurora')
            ->assertViewHas('deliveries', fn ($deliveries): bool => $deliveries->pluck('id')->all() === [$byCustomer->id])
            ->call('clearFilters')
            ->assertViewHas('deliveries', fn ($deliveries): bool => $deliveries->pluck('id')->contains($other->id));
    }

    public function test_filters_by_sector_and_status(): void
    {
        $sul = $this->delivery(['sector' => 'Sul']);
        $withoutSector = $this->delivery(['sector' => null]);
        $delivered = $this->delivery(['sector' => 'Norte', 'status' => DeliveryStatus::Delivered]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->set('sectorFilter', 'Sul')
            ->assertViewHas('deliveries', fn ($deliveries): bool => $deliveries->pluck('id')->all() === [$sul->id])
            ->set('sectorFilter', DeliveryPlanner::WITHOUT_SECTOR)
            ->assertViewHas('deliveries', fn ($deliveries): bool => $deliveries->pluck('id')->all() === [$withoutSector->id])
            ->set('sectorFilter', '')
            ->set('statusFilter', DeliveryStatus::Delivered->value)
            ->assertViewHas('deliveries', fn ($deliveries): bool => $deliveries->pluck('id')->all() === [$delivered->id]);
    }

    public function test_sorts_by_amount_and_toggles_direction(): void
    {
        $cheap = $this->delivery(['amount' => 50]);
        $expensive = $this->delivery(['amount' => 900]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('sortBy', 'amount')
            ->assertViewHas('deliveries', fn ($deliveries): bool => $deliveries->pluck('id')->all() === [$cheap->id, $expensive->id])
            ->call('sortBy', 'amount')
            ->assertSet('sortDirection', 'desc')
            ->assertViewHas('deliveries', fn ($deliveries): bool => $deliveries->pluck('id')->all() === [$expensive->id, $cheap->id])
            ->call('sortBy', 'customers.secret')
            ->assertSet('sortField', 'amount');
    }

    public function test_toggle_page_selection_only_selects_routable_deliveries(): void
    {
        $routable = $this->delivery();
        $withoutLocation = $this->delivery([], Customer::factory()->create(['user_id' => $this->admin->id]));
        $delivered = $this->delivery(['status' => DeliveryStatus::Delivered]);

        Livewire::actingAs($this->admin)
            ->test(DeliveryPlanner::class)
            ->call('togglePageSelection', [$routable->id, $withoutLocation->id, $delivered->id])
            ->assertSet('selected', [$routable->id])
            ->call('togglePageSelection', [$routable->id, $withoutLocation->id, $delivered->id])
            ->assertSet('selected', []);
    }

    private function delivery(array $attributes = [], ?Customer $customer = null): Delivery
    {
        return Delivery::factory()->forTenant($this->admin->id)->create([
            'customer_id' => $customer ?? Customer::factory()->confirmed()->create(['user_id' => $this->admin->id]),
            ...$attributes,
        ]);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function spreadsheetContent(array $rows): string
    {
        $path = sys_get_temp_dir().'/pedidos-'.uniqid().'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_keys(DeliveryImporter::COLUMNS)));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        $content = (string) file_get_contents($path);
        unlink($path);

        return $content;
    }
}
