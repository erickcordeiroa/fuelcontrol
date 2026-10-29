<?php

namespace Database\Seeders;

use App\Enums\DeliveryImportStatus;
use App\Enums\DeliveryRouteStatus;
use App\Enums\DeliveryStatus;
use App\Enums\GeocodeStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryImport;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\RouteStop;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Routing\DeliveryRoutePlanner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dados fictícios para demonstrar o módulo de rotas (Registro/SP, bairros e ruas reais; números e coordenadas sorteados dentro de cada bairro):
 * - últimos dias úteis: rotas concluídas (histórico);
 * - hoje: uma rota concluída, duas em andamento (uma parada há 14 min), uma planejada e um setor sem rota;
 * - amanhã: pedidos importados aguardando roteirização.
 *
 * php artisan db:seed --class=RouteManagementSeeder
 *
 * Pode ser executado de novo: apaga e recria apenas os registros com prefixo DEMO-.
 */
class RouteManagementSeeder extends Seeder
{
    private const PREFIX = 'DEMO-';

    private const HISTORY_DAYS = 5;

    /** Centro de distribuição usado como origem das rotas. */
    private const DEPOT = ['lat' => -24.4960, 'lng' => -47.8470];

    /**
     * @var array<string, array{code: string, center: array{0: float, 1: float}, radius: float, count: int, districts: list<string>}>
     */
    private const SECTORS = [
        'Sul' => ['code' => 'S', 'center' => [-24.5090, -47.8400], 'radius' => 0.006, 'count' => 32, 'districts' => ['Vila Ribeirópolis', 'Nosso Teto']],
        'Oeste' => ['code' => 'O', 'center' => [-24.4990, -47.8540], 'radius' => 0.006, 'count' => 28, 'districts' => ['Vila Tupi', 'Vila São Francisco', 'Jardim Caiçara', 'Vila Fátima']],
        'Leste' => ['code' => 'L', 'center' => [-24.5230, -47.8190], 'radius' => 0.006, 'count' => 22, 'districts' => ['Jardim São Paulo', 'Jardim Paulistano']],
        'Centro' => ['code' => 'C', 'center' => [-24.4915, -47.8420], 'radius' => 0.004, 'count' => 20, 'districts' => ['Centro', 'Vila Alay José Correa', 'Vila Nova']],
        'Agrochá' => ['code' => 'A', 'center' => [-24.5260, -47.8790], 'radius' => 0.005, 'count' => 8, 'districts' => ['Jardim Agrochá', 'Carapiranga']],
    ];

    /** Setores que saem para rota nos dias do histórico. */
    private const HISTORY_SECTORS = ['Sul', 'Oeste', 'Leste', 'Centro'];

    private const KINDS = ['Mercado', 'Padaria', 'Farmácia', 'Lanchonete', 'Restaurante', 'Distribuidora', 'Empório', 'Pet Shop', 'Açougue', 'Conveniência', 'Hortifruti', 'Papelaria', 'Pizzaria', 'Sorveteria', 'Peixaria', 'Casa de Chá'];

    private const NAMES = ['Central', 'Boa Vista', 'Primavera', 'do Bairro', 'Santa Luzia', 'Novo Horizonte', 'Ipê', 'Estrela', 'Aurora', 'Popular', 'União', 'Vale do Ribeira', 'Ribeira', 'São Jorge', 'Bom Preço', 'Pão Dourado'];

    private const STREETS = ['Avenida Clara Gianotti de Souza', 'Avenida Prefeito Jonas Banks Leite', 'Rua Shitiro Maeji', 'Avenida Wild José de Souza', 'Rua Capitão João Pocci', 'Avenida Saburo Kameyama'];

    private const REFERENCES = ['Portão cinza', 'Ao lado da padaria', 'Loja 2', 'Entrada lateral', 'Em frente ao ponto de ônibus', 'Casa de esquina', 'Interfone 101', 'Doca de recebimento', null, null];

    private const FAILURE_REASONS = ['Cliente ausente', 'Estabelecimento fechado', 'Recusou o pedido', 'Endereço não localizado no local'];

    public function __construct(private readonly DeliveryRoutePlanner $planner) {}

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@fueltrack.test')->first()
            ?? User::query()->where('role', UserRole::Admin)->first();

        if ($admin === null) {
            $this->command?->warn('Nenhum admin encontrado. Rode php artisan db:seed antes.');

            return;
        }

        mt_srand(2026);
        $tenantId = $admin->id;
        $today = Carbon::today();

        $this->purgeDemoData($tenantId);

        $drivers = $this->drivers($tenantId);
        $vehicles = Vehicle::query()->withoutGlobalScopes()->where('user_id', $tenantId)->orderBy('id')->get();

        $customersBySector = collect(self::SECTORS)->map(
            fn (array $config, string $sector): Collection => $this->createCustomers($tenantId, $sector, $config),
        );

        foreach ($this->historyDates($today) as $date) {
            $this->seedHistoryDay($tenantId, $date, $customersBySector, $drivers, $vehicles);
        }

        $this->seedToday($tenantId, $today, $customersBySector, $drivers, $vehicles);

        $tomorrow = $today->copy()->addDay();
        $import = $this->createImport($tenantId, $tomorrow, failedRows: 2);
        $customersBySector->each(fn (Collection $customers, string $sector) => $this->createDeliveries($tenantId, $import, $sector, $customers->filter(fn (): bool => mt_rand(1, 100) <= 75), $tomorrow));
        $this->finishImport($import);

        $this->command?->info('Dados de demonstração criados: histórico de '.self::HISTORY_DAYS.' dias, hoje ('.$today->format('d/m/Y').') e amanhã. Acesse /entregas e /rotas.');
    }

    private function purgeDemoData(int $tenantId): void
    {
        $demoDeliveryIds = Delivery::query()->withoutGlobalScopes()
            ->where('user_id', $tenantId)
            ->where('order_number', 'like', self::PREFIX.'%')
            ->pluck('id');

        $demoRouteIds = RouteStop::query()->whereIn('delivery_id', $demoDeliveryIds)->distinct()->pluck('delivery_route_id');

        DeliveryRoute::query()->withoutGlobalScopes()->whereKey($demoRouteIds)->delete();
        Delivery::query()->withoutGlobalScopes()->whereKey($demoDeliveryIds)->delete();
        DeliveryImport::query()->withoutGlobalScopes()
            ->where('user_id', $tenantId)
            ->where('file_name', 'like', self::PREFIX.'%')
            ->delete();
        Customer::query()->withoutGlobalScopes()
            ->where('user_id', $tenantId)
            ->where('external_code', 'like', self::PREFIX.'%')
            ->delete();
    }

    /**
     * @return list<Driver>
     */
    private function drivers(int $tenantId): array
    {
        $existing = Driver::query()->withoutGlobalScopes()->where('user_id', $tenantId)->orderBy('id')->get();
        $names = ['Ricardo Oliveira', 'Ana Souza', 'João Pereira', 'Marcos Lima'];

        return collect($names)
            ->map(fn (string $name, int $index): Driver => $existing->get($index) ?? Driver::query()->create([
                'user_id' => $tenantId,
                'name' => $name,
                'license_number' => '8821000'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            ]))
            ->all();
    }

    /**
     * Dias úteis anteriores a hoje, do mais antigo para o mais recente.
     *
     * @return list<Carbon>
     */
    private function historyDates(Carbon $today): array
    {
        $dates = [];
        $date = $today->copy();

        while (count($dates) < self::HISTORY_DAYS) {
            $date->subDay();

            if (! $date->isSunday()) {
                $dates[] = $date->copy();
            }
        }

        return array_reverse($dates);
    }

    /**
     * Dia já encerrado: todas as rotas concluídas, com poucas falhas.
     *
     * @param  Collection<string, Collection<int, Customer>>  $customersBySector
     * @param  list<Driver>  $drivers
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function seedHistoryDay(int $tenantId, Carbon $date, Collection $customersBySector, array $drivers, Collection $vehicles): void
    {
        $import = $this->createImport($tenantId, $date, failedRows: mt_rand(0, 3));

        foreach (self::HISTORY_SECTORS as $position => $sector) {
            $customers = $customersBySector[$sector]->filter(fn (Customer $customer): bool => $customer->hasCoordinates() && mt_rand(1, 100) <= 70);
            $deliveries = $this->createDeliveries($tenantId, $import, $sector, $customers, $date);

            $route = $this->createRoute($tenantId, $sector, $date, $deliveries, $drivers[$position], $vehicles);
            $failed = collect(range(0, $deliveries->count() - 1))->filter(fn (): bool => mt_rand(1, 100) <= 5)->values()->all();
            $finishedAt = $date->copy()->setTime(mt_rand(16, 17), mt_rand(0, 59));

            $this->simulateProgress($route, $deliveries->count(), $failed, $finishedAt, confirmsLocations: false);
        }

        $this->finishImport($import);
    }

    /**
     * @param  Collection<string, Collection<int, Customer>>  $customersBySector
     * @param  list<Driver>  $drivers
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function seedToday(int $tenantId, Carbon $today, Collection $customersBySector, array $drivers, Collection $vehicles): void
    {
        $import = $this->createImport($tenantId, $today, failedRows: 1);

        $deliveriesBySector = $customersBySector->map(
            fn (Collection $customers, string $sector): Collection => $this->createDeliveries($tenantId, $import, $sector, $customers, $today),
        );

        $this->finishImport($import);

        $plans = [
            'Centro' => ['driver' => $drivers[3], 'progress' => PHP_INT_MAX, 'failed' => [4], 'lastPingMinutesAgo' => 35, 'isStalled' => false],
            'Sul' => ['driver' => $drivers[0], 'progress' => 17, 'failed' => [6, 13], 'lastPingMinutesAgo' => 1, 'isStalled' => false],
            'Oeste' => ['driver' => $drivers[1], 'progress' => 24, 'failed' => [9], 'lastPingMinutesAgo' => 14, 'isStalled' => true],
            'Leste' => ['driver' => $drivers[2], 'progress' => 0, 'failed' => [], 'lastPingMinutesAgo' => null, 'isStalled' => false],
        ];

        foreach ($plans as $sector => $plan) {
            $deliveries = $deliveriesBySector[$sector]->filter(fn (Delivery $d): bool => $d->customer->hasCoordinates());
            $route = $this->createRoute($tenantId, $sector, $today, $deliveries, $plan['driver'], $vehicles);

            if ($plan['progress'] > 0) {
                $this->simulateProgress(
                    $route,
                    min($plan['progress'], $deliveries->count()),
                    $plan['failed'],
                    now()->subMinutes($plan['lastPingMinutesAgo']),
                    $plan['isStalled'],
                );
            }
        }
    }

    /**
     * @param  Collection<int, Delivery>  $deliveries
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function createRoute(int $tenantId, string $sector, Carbon $date, Collection $deliveries, Driver $driver, Collection $vehicles): DeliveryRoute
    {
        $sectorIndex = array_search($sector, array_keys(self::SECTORS), true);

        return $this->planner->create([
            'user_id' => $tenantId,
            'name' => 'Setor '.$sector,
            'scheduled_for' => $date->toDateString(),
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicles->isEmpty() ? null : $vehicles->get($sectorIndex % $vehicles->count())->id,
            'origin_latitude' => self::DEPOT['lat'],
            'origin_longitude' => self::DEPOT['lng'],
        ], $deliveries);
    }

    /**
     * @param  array{code: string, center: array{0: float, 1: float}, radius: float, count: int, districts: list<string>}  $config
     * @return Collection<int, Customer>
     */
    private function createCustomers(int $tenantId, string $sector, array $config): Collection
    {
        return collect(range(1, $config['count']))->map(function (int $index) use ($tenantId, $sector, $config): Customer {
            $address = [
                'street' => $this->pick(self::STREETS),
                'number' => (string) mt_rand(10, 2800),
                'district' => $this->pick($config['districts']),
                'city' => 'Registro',
                'state' => 'SP',
                'zip_code' => '11900000',
            ];

            $latitude = $config['center'][0] + $this->jitter($config['radius']);
            $longitude = $config['center'][1] + $this->jitter($config['radius']);

            return Customer::query()->create([
                'user_id' => $tenantId,
                'external_code' => self::PREFIX.$config['code'].str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'name' => $this->pick(self::KINDS).' '.$this->pick(self::NAMES),
                ...$address,
                'reference' => $this->pick(self::REFERENCES),
                'address_hash' => Customer::hashAddress($address),
                ...$this->locationFor($sector, $index, $latitude, $longitude),
            ]);
        });
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @return Collection<int, Delivery>
     */
    private function createDeliveries(int $tenantId, DeliveryImport $import, string $sector, Collection $customers, Carbon $date): Collection
    {
        $code = self::SECTORS[$sector]['code'];

        return $customers->values()->map(function (Customer $customer, int $index) use ($tenantId, $import, $sector, $code, $date): Delivery {
            $hasWindow = mt_rand(1, 100) <= 25;
            $windowStart = mt_rand(8, 14);

            $delivery = Delivery::query()->create([
                'user_id' => $tenantId,
                'delivery_import_id' => $import->id,
                'customer_id' => $customer->id,
                'order_number' => self::PREFIX.$date->format('ymd').'-'.$code.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'scheduled_for' => $date->toDateString(),
                'amount' => mt_rand(3_000, 250_000) / 100,
                'sector' => $sector,
                'window_start' => $hasWindow ? sprintf('%02d:00', $windowStart) : null,
                'window_end' => $hasWindow ? sprintf('%02d:00', $windowStart + 3) : null,
                'status' => DeliveryStatus::Pending,
            ]);

            return $delivery->setRelation('customer', $customer);
        });
    }

    private function createImport(int $tenantId, Carbon $date, int $failedRows): DeliveryImport
    {
        return DeliveryImport::query()->create([
            'user_id' => $tenantId,
            'file_name' => self::PREFIX.'pedidos-'.$date->format('Y-m-d').'.xlsx',
            'scheduled_for' => $date->toDateString(),
            'status' => DeliveryImportStatus::Completed,
            'failed_rows' => $failedRows,
            'errors' => collect(array_fill(0, $failedRows, null))
                ->map(fn (): array => [
                    'row' => mt_rand(2, 120),
                    'messages' => [$this->pick(['CEP inválido.', 'Número do pedido repetido na planilha.', 'Valor do pedido não informado.', 'Cidade não informada.'])],
                ])
                ->all(),
        ]);
    }

    /**
     * Fecha a importação com os totais dos pedidos criados nela.
     */
    private function finishImport(DeliveryImport $import): void
    {
        $imported = $import->deliveries()->count();

        $import->update([
            'imported_rows' => $imported,
            'total_rows' => $imported + $import->failed_rows,
            'status' => $import->failed_rows > 0 ? DeliveryImportStatus::CompletedWithErrors : DeliveryImportStatus::Completed,
        ]);
    }

    /**
     * A maioria dos clientes já tem coordenada própria (confirmada em entregas anteriores);
     * alguns usam o cache do Google; no setor Agrochá, dois precisam de revisão.
     *
     * @return array<string, mixed>
     */
    private function locationFor(string $sector, int $index, float $latitude, float $longitude): array
    {
        if ($sector === 'Agrochá' && $index === 1) {
            return ['geocode_status' => GeocodeStatus::Failed, 'geocode_error' => 'Endereço não encontrado pelo Google.'];
        }

        if ($sector === 'Agrochá' && $index === 2) {
            return [
                'geocode_status' => GeocodeStatus::Approximate,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'location_type' => 'GEOMETRIC_CENTER',
                'geocoded_at' => now()->subDays(2),
            ];
        }

        if ($index % 10 < 3) {
            return [
                'geocode_status' => GeocodeStatus::Resolved,
                'geocode_provider' => 'geoapify',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'location_type' => 'ROOFTOP',
                'geocoded_at' => now()->subDays(mt_rand(1, 20)),
            ];
        }

        return [
            'geocode_status' => GeocodeStatus::Confirmed,
            'confirmed_latitude' => $latitude,
            'confirmed_longitude' => $longitude,
            'confirmed_at' => now()->subDays(mt_rand(5, 120)),
        ];
    }

    /**
     * Simula o motorista percorrendo as primeiras paradas. Quando todas são feitas, a rota
     * termina com a volta ao centro de distribuição em $lastPingAt.
     *
     * @param  list<int>  $failedPositions  Posições (0-based) que viram "não entregue"
     * @param  bool  $confirmsLocations  Grava o GPS da entrega como coordenada própria do cliente (desligado no histórico para manter clientes com cache do Google)
     */
    private function simulateProgress(DeliveryRoute $route, int $done, array $failedPositions, Carbon $lastPingAt, bool $isStalled = false, bool $confirmsLocations = true): void
    {
        $stops = $route->stops()->with('delivery.customer')->get();
        $isComplete = $done >= $stops->count();
        $startedAt = $lastPingAt->copy()->subMinutes($done * 8 + 20);

        $route->update(['status' => DeliveryRouteStatus::InProgress, 'started_at' => $startedAt]);

        $previous = $route->origin();
        $time = $startedAt->copy();

        foreach ($stops as $position => $stop) {
            $delivery = $stop->delivery;
            $coordinates = $delivery->customer->coordinates();

            if ($position >= $done) {
                $delivery->update(['status' => DeliveryStatus::InTransit]);
                $this->ping($route, $this->between($previous, $coordinates, 0.55), $lastPingAt, $isStalled ? 0.0 : 28.0);

                break;
            }

            $this->ping($route, $this->between($previous, $coordinates, 0.5), $time->copy()->addMinutes(3), 26.0);
            $time->addMinutes(8);
            $this->ping($route, $coordinates, $time, 0.0);

            $failed = in_array($position, $failedPositions, true);
            $stop->update(['arrived_at' => $time]);
            $delivery->update([
                'status' => $failed ? DeliveryStatus::Failed : DeliveryStatus::Delivered,
                'delivered_at' => $failed ? null : $time,
                'failure_reason' => $failed ? $this->pick(self::FAILURE_REASONS) : null,
            ]);

            if ($confirmsLocations && ! $failed && $delivery->customer->geocode_status === GeocodeStatus::Resolved) {
                $delivery->customer->confirmLocation($coordinates['lat'] + $this->jitter(0.0002), $coordinates['lng'] + $this->jitter(0.0002));
            }

            $previous = $coordinates;
        }

        if ($isComplete) {
            $this->ping($route, $route->origin(), $lastPingAt, 0.0);
            $route->update(['status' => DeliveryRouteStatus::Completed, 'finished_at' => $lastPingAt]);
        }
    }

    /**
     * @param  array{lat: float, lng: float}  $position
     */
    private function ping(DeliveryRoute $route, array $position, Carbon $at, float $speed): void
    {
        DriverLocation::query()->create([
            'user_id' => $route->user_id,
            'delivery_route_id' => $route->id,
            'driver_id' => $route->driver_id,
            'latitude' => $position['lat'],
            'longitude' => $position['lng'],
            'accuracy_m' => mt_rand(4, 18),
            'speed_kmh' => $speed,
            'recorded_at' => $at,
        ]);
    }

    /**
     * @param  array{lat: float, lng: float}  $from
     * @param  array{lat: float, lng: float}  $to
     * @return array{lat: float, lng: float}
     */
    private function between(array $from, array $to, float $fraction): array
    {
        return [
            'lat' => $from['lat'] + ($to['lat'] - $from['lat']) * $fraction,
            'lng' => $from['lng'] + ($to['lng'] - $from['lng']) * $fraction,
        ];
    }

    private function jitter(float $radius): float
    {
        return (mt_rand() / mt_getrandmax() * 2 - 1) * $radius;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }
}
