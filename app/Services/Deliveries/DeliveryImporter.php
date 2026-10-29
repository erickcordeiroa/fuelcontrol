<?php

namespace App\Services\Deliveries;

use App\Enums\DeliveryImportStatus;
use App\Enums\DeliveryStatus;
use App\Enums\GeocodeStatus;
use App\Jobs\GeocodeCustomer;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryImport;
use App\Support\BrazilianNumber;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Lê a planilha de pedidos (.xlsx ou .csv), cria/atualiza clientes e pedidos e
 * enfileira a geocodificação apenas para clientes sem coordenada.
 */
class DeliveryImporter
{
    /**
     * Colunas do modelo, na ordem em que aparecem na planilha.
     *
     * @var array<string, bool> coluna => obrigatória
     */
    public const COLUMNS = [
        'codigo_cliente' => true,
        'numero_pedido' => true,
        'nome' => true,
        'logradouro' => true,
        'numero' => false,
        'bairro' => false,
        'cidade' => true,
        'uf' => true,
        'cep' => true,
        'ponto_referencia' => false,
        'valor_pedido' => true,
        'setor' => false,
        'janela_inicio' => false,
        'janela_fim' => false,
        'latitude' => false,
        'longitude' => false,
    ];

    private const MAX_STORED_ERRORS = 200;

    public function import(string $path, string $originalName, CarbonInterface $scheduledFor): DeliveryImport
    {
        $import = DeliveryImport::query()->create([
            'file_name' => mb_substr($originalName, 0, 255),
            'scheduled_for' => $scheduledFor->toDateString(),
            'status' => DeliveryImportStatus::Failed,
        ]);

        try {
            $rows = $this->readRows($path, $originalName);
        } catch (Throwable $e) {
            return $this->finish($import, 0, 0, [['row' => null, 'messages' => [__('Não foi possível ler o arquivo: :message', ['message' => $e->getMessage()])]]]);
        }

        $header = array_shift($rows);
        $columns = $this->mapHeader($header ?? []);
        $missing = collect(self::COLUMNS)->filter()->keys()->diff(array_keys($columns));

        if ($missing->isNotEmpty()) {
            return $this->finish($import, 0, 0, [['row' => 1, 'messages' => [__('Colunas obrigatórias ausentes: :columns', ['columns' => $missing->implode(', ')])]]]);
        }

        $errors = [];
        $imported = 0;
        $total = 0;
        $seenOrders = [];
        $customersToGeocode = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $data = $this->extract($row, $columns);

            if ($this->isBlank($data)) {
                continue;
            }

            $total++;
            $messages = $this->validate($data, $seenOrders);

            if ($messages !== []) {
                $errors[] = ['row' => $lineNumber, 'messages' => $messages];

                continue;
            }

            $seenOrders[$data['numero_pedido']] = true;

            $customer = DB::transaction(fn (): Customer => $this->persistRow($import, $data, $scheduledFor));

            if ($customer->needsGeocoding()) {
                $customersToGeocode[$customer->id] = $customer;
            }

            $imported++;
        }

        foreach ($customersToGeocode as $customer) {
            GeocodeCustomer::dispatch($customer);
        }

        return $this->finish($import, $total, $imported, $errors);
    }

    /**
     * @return list<list<mixed>>
     */
    private function readRows(string $path, string $originalName): array
    {
        $reader = $this->readerFor($path, $originalName);
        $reader->open($path);

        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    private function readerFor(string $path, string $originalName): ReaderInterface
    {
        if (Str::lower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'csv') {
            return new XlsxReader(new XlsxOptions(SHOULD_PRESERVE_EMPTY_ROWS: true));
        }

        $handle = fopen($path, 'r');
        $firstLine = $handle !== false ? (string) fgets($handle) : '';
        if ($handle !== false) {
            fclose($handle);
        }

        return new CsvReader(new CsvOptions(
            SHOULD_PRESERVE_EMPTY_ROWS: true,
            FIELD_DELIMITER: substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',',
        ));
    }

    /**
     * @param  list<mixed>  $header
     * @return array<string, int> coluna => índice
     */
    private function mapHeader(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $label) {
            $key = Str::of((string) $label)->ascii()->lower()->trim()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

            if (array_key_exists($key, self::COLUMNS)) {
                $columns[$key] = $index;
            }
        }

        return $columns;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $columns
     * @return array<string, mixed>
     */
    private function extract(array $row, array $columns): array
    {
        $data = [];
        foreach (array_keys(self::COLUMNS) as $column) {
            $value = isset($columns[$column]) ? ($row[$columns[$column]] ?? null) : null;
            $data[$column] = is_string($value) ? trim($value) : $value;
        }

        $data['codigo_cliente'] = $this->asText($data['codigo_cliente']);
        $data['numero_pedido'] = $this->asText($data['numero_pedido']);
        $data['numero'] = $this->asText($data['numero']);
        $data['cep'] = preg_replace('/\D/', '', $this->asText($data['cep']) ?? '');
        $data['uf'] = Str::upper((string) $data['uf']);
        $data['valor_pedido'] = $this->asMoney($data['valor_pedido']);
        $data['janela_inicio'] = $this->asTime($data['janela_inicio']);
        $data['janela_fim'] = $this->asTime($data['janela_fim']);
        $data['latitude'] = $this->asCoordinate($data['latitude']);
        $data['longitude'] = $this->asCoordinate($data['longitude']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isBlank(array $data): bool
    {
        return collect($data)->every(fn (mixed $value): bool => $value === null || $value === '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, bool>  $seenOrders
     * @return list<string>
     */
    private function validate(array $data, array $seenOrders): array
    {
        $validator = Validator::make($data, [
            'codigo_cliente' => ['required', 'string', 'max:64'],
            'numero_pedido' => ['required', 'string', 'max:64'],
            'nome' => ['required', 'string', 'max:255'],
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:120'],
            'cidade' => ['required', 'string', 'max:120'],
            'uf' => ['required', 'string', 'size:2', 'alpha'],
            'cep' => ['required', 'digits:8'],
            'ponto_referencia' => ['nullable', 'string', 'max:500'],
            'valor_pedido' => ['required', 'numeric', 'min:0'],
            'setor' => ['nullable', 'string', 'max:64'],
            'janela_inicio' => ['nullable', 'date_format:H:i'],
            'janela_fim' => ['nullable', 'date_format:H:i'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ], attributes: [
            'codigo_cliente' => 'código do cliente',
            'numero_pedido' => 'número do pedido',
            'valor_pedido' => 'valor do pedido',
            'uf' => 'UF',
            'cep' => 'CEP',
        ]);

        $messages = $validator->errors()->all();

        if (filled($data['numero_pedido'])) {
            if (isset($seenOrders[$data['numero_pedido']])) {
                $messages[] = __('Pedido :order repetido na planilha.', ['order' => $data['numero_pedido']]);
            } elseif (Delivery::query()->where('order_number', $data['numero_pedido'])->exists()) {
                $messages[] = __('Pedido :order já foi importado antes.', ['order' => $data['numero_pedido']]);
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistRow(DeliveryImport $import, array $data, CarbonInterface $scheduledFor): Customer
    {
        $address = [
            'street' => $data['logradouro'],
            'number' => $data['numero'],
            'district' => $data['bairro'],
            'city' => $data['cidade'],
            'state' => $data['uf'],
            'zip_code' => $data['cep'],
        ];
        $addressHash = Customer::hashAddress($address);

        $customer = Customer::query()->firstOrNew(['external_code' => $data['codigo_cliente']]);

        if ($customer->exists && $customer->address_hash !== $addressHash) {
            $customer->resetLocation();
        }

        $customer->fill([
            ...$address,
            'name' => $data['nome'],
            'reference' => $data['ponto_referencia'] ?: $customer->reference,
            'address_hash' => $addressHash,
            'geocode_status' => $customer->geocode_status ?? GeocodeStatus::Pending,
        ])->save();

        if ($data['latitude'] !== null && $data['longitude'] !== null) {
            $customer->confirmLocation($data['latitude'], $data['longitude'], GeocodeStatus::Manual);
        }

        Delivery::query()->create([
            'delivery_import_id' => $import->id,
            'customer_id' => $customer->id,
            'order_number' => $data['numero_pedido'],
            'scheduled_for' => $scheduledFor->toDateString(),
            'amount' => $data['valor_pedido'],
            'sector' => $data['setor'] ?: null,
            'window_start' => $data['janela_inicio'],
            'window_end' => $data['janela_fim'],
            'status' => DeliveryStatus::Pending,
        ]);

        return $customer;
    }

    /**
     * @param  list<array{row: int|null, messages: list<string>}>  $errors
     */
    private function finish(DeliveryImport $import, int $total, int $imported, array $errors): DeliveryImport
    {
        $status = match (true) {
            $imported === 0 => DeliveryImportStatus::Failed,
            $errors !== [] => DeliveryImportStatus::CompletedWithErrors,
            default => DeliveryImportStatus::Completed,
        };

        $import->update([
            'status' => $status,
            'total_rows' => $total,
            'imported_rows' => $imported,
            'failed_rows' => max(0, $total - $imported),
            'errors' => array_slice($errors, 0, self::MAX_STORED_ERRORS),
        ]);

        return $import;
    }

    private function asText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    private function asMoney(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $parsed = BrazilianNumber::parse($value);

        return preg_match('/\d/', $value) === 1 ? $parsed : $value;
    }

    private function asTime(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }

        if ((is_float($value) || is_int($value)) && $value >= 0 && $value < 1) {
            $minutes = (int) round($value * 24 * 60);

            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $value, $matches) === 1) {
            return sprintf('%02d:%s', (int) $matches[1], $matches[2]);
        }

        return $value === '' ? null : $value;
    }

    private function asCoordinate(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : $value;
    }
}
