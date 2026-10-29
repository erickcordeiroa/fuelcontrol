<?php

namespace Tests\Feature\Deliveries;

use App\Enums\DeliveryImportStatus;
use App\Enums\DeliveryStatus;
use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeCustomer;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\User;
use App\Services\Deliveries\DeliveryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class DeliveryImporterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_imports_rows_and_queues_geocoding_once_per_new_customer(): void
    {
        Queue::fake();

        $path = $this->spreadsheet([
            $this->row(['numero_pedido' => 'PED-1', 'valor_pedido' => '1.284,90', 'janela_inicio' => '08:00', 'janela_fim' => '12:00']),
            $this->row(['numero_pedido' => 'PED-2', 'valor_pedido' => 64.3]),
            $this->row(['codigo_cliente' => 'C-2', 'numero_pedido' => 'PED-3', 'nome' => 'Padaria Pão Dourado', 'setor' => 'Norte']),
        ]);

        $import = app(DeliveryImporter::class)->import($path, 'pedidos.xlsx', Carbon::parse('2026-10-09'));

        $this->assertSame(DeliveryImportStatus::Completed, $import->status);
        $this->assertSame(3, $import->total_rows);
        $this->assertSame(3, $import->imported_rows);
        $this->assertSame(2, Customer::query()->count());

        $first = Delivery::query()->where('order_number', 'PED-1')->firstOrFail();
        $this->assertSame($this->admin->id, $first->user_id);
        $this->assertSame(1284.9, $first->amount);
        $this->assertSame('2026-10-09', $first->scheduled_for->toDateString());
        $this->assertSame('08:00', substr((string) $first->window_start, 0, 5));
        $this->assertSame(DeliveryStatus::Pending, $first->status);
        $this->assertSame('74270110', $first->customer->zip_code);
        $this->assertSame(6430, (int) Delivery::query()->where('order_number', 'PED-2')->firstOrFail()->getRawOriginal('amount'));

        Queue::assertPushed(GeocodeCustomer::class, 2);
    }

    public function test_reports_invalid_rows_with_line_numbers_and_imports_the_rest(): void
    {
        Queue::fake();

        $path = $this->spreadsheet([
            $this->row(['numero_pedido' => 'PED-1']),
            $this->row(['numero_pedido' => 'PED-2', 'cep' => '123']),
            $this->row(['numero_pedido' => 'PED-3', 'uf' => 'Goiás', 'valor_pedido' => 'abc']),
            $this->row(['numero_pedido' => 'PED-1']),
        ]);

        $import = app(DeliveryImporter::class)->import($path, 'pedidos.xlsx', Carbon::today());

        $this->assertSame(DeliveryImportStatus::CompletedWithErrors, $import->status);
        $this->assertSame(1, $import->imported_rows);
        $this->assertSame(3, $import->failed_rows);
        $this->assertSame([3, 4, 5], array_column($import->errors, 'row'));
        $this->assertStringContainsString('repetido', implode(' ', $import->errors[2]['messages']));
    }

    public function test_rejects_order_already_imported(): void
    {
        Queue::fake();
        Delivery::factory()->forTenant($this->admin->id)->create([
            'order_number' => 'PED-1',
            'customer_id' => Customer::factory()->create(['user_id' => $this->admin->id]),
        ]);

        $import = app(DeliveryImporter::class)->import($this->spreadsheet([$this->row(['numero_pedido' => 'PED-1'])]), 'pedidos.xlsx', Carbon::today());

        $this->assertSame(DeliveryImportStatus::Failed, $import->status);
        $this->assertStringContainsString('já foi importado', $import->errors[0]['messages'][0]);
    }

    public function test_fails_when_required_columns_are_missing(): void
    {
        $path = $this->spreadsheet([['A', 'B']], header: ['codigo_cliente', 'nome']);

        $import = app(DeliveryImporter::class)->import($path, 'pedidos.xlsx', Carbon::today());

        $this->assertSame(DeliveryImportStatus::Failed, $import->status);
        $this->assertStringContainsString('numero_pedido', $import->errors[0]['messages'][0]);
        $this->assertSame(0, Delivery::query()->count());
    }

    public function test_known_customer_with_same_address_keeps_location_and_skips_google(): void
    {
        Queue::fake();
        $address = ['street' => 'Rua das Acácias', 'number' => '410', 'district' => 'Jardim América', 'city' => 'Goiânia', 'state' => 'GO', 'zip_code' => '74270110'];
        $customer = Customer::factory()->confirmed(-16.71, -49.26)->create([
            'user_id' => $this->admin->id,
            'external_code' => 'C-1',
            ...$address,
            'address_hash' => Customer::hashAddress($address),
        ]);

        app(DeliveryImporter::class)->import($this->spreadsheet([$this->row()]), 'pedidos.xlsx', Carbon::today());

        $this->assertSame(['lat' => -16.71, 'lng' => -49.26], $customer->refresh()->coordinates());
        Queue::assertNotPushed(GeocodeCustomer::class);
    }

    public function test_changed_address_resets_location_and_queues_geocoding(): void
    {
        Queue::fake();
        $customer = Customer::factory()->confirmed()->create(['user_id' => $this->admin->id, 'external_code' => 'C-1']);

        app(DeliveryImporter::class)->import($this->spreadsheet([$this->row(['logradouro' => 'Avenida Nova'])]), 'pedidos.xlsx', Carbon::today());

        $customer->refresh();
        $this->assertNull($customer->coordinates());
        $this->assertSame(GeocodeStatus::Pending, $customer->geocode_status);
        $this->assertSame('Avenida Nova', $customer->street);
        Queue::assertPushed(GeocodeCustomer::class, 1);
    }

    public function test_coordinates_in_the_spreadsheet_are_kept_as_manual_location(): void
    {
        Queue::fake();

        app(DeliveryImporter::class)->import(
            $this->spreadsheet([$this->row(['latitude' => '-16,7123', 'longitude' => -49.2611])]),
            'pedidos.xlsx',
            Carbon::today(),
        );

        $customer = Customer::query()->firstOrFail();
        $this->assertSame(GeocodeStatus::Manual, $customer->geocode_status);
        $this->assertSame(['lat' => -16.7123, 'lng' => -49.2611], $customer->coordinates());
        Queue::assertNotPushed(GeocodeCustomer::class);
    }

    public function test_reads_csv_with_semicolon_delimiter(): void
    {
        Queue::fake();
        $path = $this->tempPath('csv');
        $header = implode(';', array_keys(DeliveryImporter::COLUMNS));
        $line = implode(';', array_map(fn (mixed $value): string => (string) $value, $this->row(['valor_pedido' => '99,90'])));
        file_put_contents($path, "\xEF\xBB\xBF".$header."\n".$line."\n");

        $import = app(DeliveryImporter::class)->import($path, 'pedidos.csv', Carbon::today());

        $this->assertSame(DeliveryImportStatus::Completed, $import->status);
        $this->assertSame(99.9, Delivery::query()->firstOrFail()->amount);
    }

    public function test_does_not_touch_customers_of_another_tenant(): void
    {
        Queue::fake();
        $otherAdmin = User::factory()->admin()->create();
        $foreign = Customer::factory()->confirmed()->create(['user_id' => $otherAdmin->id, 'external_code' => 'C-1', 'name' => 'Cliente de outra empresa']);

        app(DeliveryImporter::class)->import($this->spreadsheet([$this->row()]), 'pedidos.xlsx', Carbon::today());

        $this->assertSame('Cliente de outra empresa', $foreign->refresh()->name);
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(2, Customer::query()->withoutGlobalScopes()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return list<mixed>
     */
    private function row(array $overrides = []): array
    {
        $values = [
            'codigo_cliente' => 'C-1',
            'numero_pedido' => 'PED-1',
            'nome' => 'Mercearia São Jorge',
            'logradouro' => 'Rua das Acácias',
            'numero' => '410',
            'bairro' => 'Jardim América',
            'cidade' => 'Goiânia',
            'uf' => 'GO',
            'cep' => '74270-110',
            'ponto_referencia' => 'Portão azul',
            'valor_pedido' => '150,00',
            'setor' => 'Sul',
            'janela_inicio' => '',
            'janela_fim' => '',
            'latitude' => '',
            'longitude' => '',
            ...$overrides,
        ];

        return array_values(array_map(fn (string $column): mixed => $values[$column], array_keys(DeliveryImporter::COLUMNS)));
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @param  list<string>|null  $header
     */
    private function spreadsheet(array $rows, ?array $header = null): string
    {
        $path = $this->tempPath('xlsx');

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($header ?? array_keys(DeliveryImporter::COLUMNS)));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    private function tempPath(string $extension): string
    {
        $path = sys_get_temp_dir().'/pedidos-'.uniqid().'.'.$extension;
        $this->tempFiles[] = $path;

        return $path;
    }
}
