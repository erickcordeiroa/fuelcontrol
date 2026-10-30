<?php

namespace Tests\Feature\Deliveries;

use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeCustomer;
use App\Models\Customer;
use App\Models\GeocodingRequest;
use App\Models\User;
use App\Services\Geocoding\AddressBook;
use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\GeocoderFactory;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GeocodingDailyLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.geocoding.daily_limit' => 3]);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_daily_limit_counts_answered_requests_of_every_company_today(): void
    {
        $factory = app(GeocoderFactory::class);

        GeocodingRequest::factory()->forTenant($this->admin->id)->create(['provider' => 'geoapify']);
        GeocodingRequest::factory()->forTenant(User::factory()->admin()->create()->id)->create(['provider' => 'geoapify']);
        GeocodingRequest::factory()->forTenant($this->admin->id)->notBillable('HTTP_500')->create(['provider' => 'geoapify']);
        GeocodingRequest::factory()->forTenant($this->admin->id)->create(['provider' => 'geoapify', 'created_at' => now()->subDay()]);

        $this->assertSame(2, $factory->usedToday());
        $this->assertTrue($factory->hasDailyCapacity());

        GeocodingRequest::factory()->forTenant($this->admin->id)->create(['provider' => 'geoapify']);

        $this->assertFalse($factory->hasDailyCapacity());
    }

    public function test_zero_turns_the_daily_limit_off(): void
    {
        config(['services.geocoding.daily_limit' => 0]);
        GeocodingRequest::factory()->forTenant($this->admin->id)->count(5)->create(['provider' => 'geoapify']);

        $this->assertTrue(app(GeocoderFactory::class)->hasDailyCapacity());
    }

    public function test_job_keeps_customer_pending_without_calling_the_provider_when_the_day_is_full(): void
    {
        GeocodingRequest::factory()->forTenant(User::factory()->admin()->create()->id)->count(3)->create(['provider' => 'geoapify']);
        $customer = Customer::factory()->create(['user_id' => $this->admin->id]);

        $geocoder = Mockery::mock(Geocoder::class);
        $geocoder->shouldNotReceive('geocode');
        $this->app->instance(GeocoderFactory::class, Mockery::mock(GeocoderFactory::class.'[forTenant]', fn (MockInterface $mock) => $mock->allows('forTenant')->andReturn($geocoder))->makePartial());

        (new GeocodeCustomer($customer))->handle(app(GeocoderFactory::class), app(AddressBook::class));

        $customer->refresh();
        $this->assertSame(GeocodeStatus::Pending, $customer->geocode_status);
        $this->assertStringContainsString('Limite diário', $customer->geocode_error);
    }

    public function test_resume_command_queues_only_customers_held_by_a_limit(): void
    {
        Queue::fake();

        $held = Customer::factory()->create(['user_id' => $this->admin->id, 'geocode_status' => GeocodeStatus::Pending, 'geocode_error' => 'Limite diário de busca de endereços atingido.']);
        Customer::factory()->create(['user_id' => $this->admin->id, 'geocode_status' => GeocodeStatus::Pending, 'geocode_error' => null]);
        Customer::factory()->located()->create(['user_id' => $this->admin->id, 'geocode_status' => GeocodeStatus::Pending, 'geocode_error' => 'Cota mensal de busca de endereços atingida.']);

        $this->artisan('geocoding:resume-pending')->assertSuccessful();

        Queue::assertPushed(GeocodeCustomer::class, 1);
        Queue::assertPushed(GeocodeCustomer::class, fn (GeocodeCustomer $job): bool => $job->customer->is($held));
    }

    public function test_resume_command_requeues_customers_waiting_too_long_for_a_lost_job(): void
    {
        Queue::fake();

        $stale = Customer::factory()->create(['user_id' => $this->admin->id, 'geocode_status' => GeocodeStatus::Pending, 'geocode_error' => null]);
        $stale->forceFill(['updated_at' => now()->subMinutes(Customer::GEOCODE_STALE_MINUTES + 1)])->saveQuietly();
        Customer::factory()->create(['user_id' => $this->admin->id, 'geocode_status' => GeocodeStatus::Pending, 'geocode_error' => null]);

        $this->artisan('geocoding:resume-pending')->assertSuccessful();

        Queue::assertPushed(GeocodeCustomer::class, 1);
        Queue::assertPushed(GeocodeCustomer::class, fn (GeocodeCustomer $job): bool => $job->customer->is($stale));
    }

    public function test_scheduler_processes_the_queue_every_minute_and_resumes_pending_searches(): void
    {
        $events = collect(app(Schedule::class)->events());

        $worker = $events->first(fn ($event): bool => str_contains($event->command, 'queue:work') && str_contains($event->command, '--stop-when-empty'));
        $this->assertNotNull($worker);
        $this->assertSame('* * * * *', $worker->expression);
        $this->assertTrue($worker->withoutOverlapping);

        $resume = $events->first(fn ($event): bool => str_contains($event->command, 'geocoding:resume-pending'));
        $this->assertSame('*/15 * * * *', $resume->expression);
    }

    public function test_job_lock_expires_so_a_lost_job_does_not_block_the_customer_forever(): void
    {
        $this->assertSame(3600, (new GeocodeCustomer(Customer::factory()->create(['user_id' => $this->admin->id])))->uniqueFor);
    }
}
