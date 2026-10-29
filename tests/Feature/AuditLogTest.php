<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Ana Gestora']);
    }

    public function test_update_records_old_and_new_values_with_the_actor(): void
    {
        $this->actingAs($this->admin);
        $vehicle = Vehicle::factory()->create(['user_id' => $this->admin->id, 'plate' => 'BRA-2E19']);

        $vehicle->update(['plate' => 'BRA-2E20']);

        $log = AuditLog::query()->latest('id')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('updated', $log->metadata['event']);
        $this->assertSame('Vehicle', $log->metadata['model']);
        $this->assertSame($vehicle->id, $log->metadata['model_id']);
        $this->assertSame(['plate' => ['old' => 'BRA-2E19', 'new' => 'BRA-2E20']], $log->metadata['changes']);
        $this->assertSame('Ana Gestora', $log->metadata['actor']['name']);
        $this->assertSame('admin', $log->metadata['actor']['role']);
    }

    public function test_create_and_delete_are_recorded(): void
    {
        $this->actingAs($this->admin);
        $vehicle = Vehicle::factory()->create(['user_id' => $this->admin->id, 'plate' => 'XYZ-9A01']);
        $vehicle->delete();

        $events = AuditLog::query()->orderBy('id')->get()->pluck('metadata.event')->all();
        $this->assertSame(['created', 'deleted'], $events);
        $deleted = AuditLog::query()->latest('id')->firstOrFail();
        $this->assertSame('XYZ-9A01', $deleted->metadata['changes']['plate']['old']);
    }

    public function test_saving_without_changes_records_nothing(): void
    {
        $vehicle = Vehicle::factory()->create(['user_id' => $this->admin->id]);
        $before = AuditLog::query()->count();

        $vehicle->save();
        $vehicle->touch();

        $this->assertSame($before, AuditLog::query()->count());
    }

    public function test_system_changes_have_no_actor(): void
    {
        $vehicle = Vehicle::factory()->create(['user_id' => $this->admin->id]);

        $this->assertNull(AuditLog::query()->withoutGlobalScopes()->latest('id')->firstOrFail()->metadata['actor']);
        $this->assertSame($vehicle->id, AuditLog::query()->withoutGlobalScopes()->latest('id')->firstOrFail()->metadata['model_id']);
    }

    public function test_log_cannot_be_changed_or_deleted(): void
    {
        Vehicle::factory()->create(['user_id' => $this->admin->id]);
        $log = AuditLog::query()->withoutGlobalScopes()->firstOrFail();

        $log->update(['metadata' => ['event' => 'tampered']]);
        $log->delete();

        $this->assertSame('created', $log->fresh()->metadata['event']);
    }

    public function test_each_company_sees_only_its_own_log(): void
    {
        $otherAdmin = User::factory()->admin()->create();
        Vehicle::factory()->create(['user_id' => $this->admin->id]);
        Vehicle::factory()->create(['user_id' => $otherAdmin->id]);

        $this->actingAs($this->admin);

        $this->assertSame([$this->admin->id], AuditLog::query()->pluck('user_id')->unique()->values()->all());
    }
}
