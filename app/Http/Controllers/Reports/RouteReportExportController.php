<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\RouteReportExportRequest;
use App\Services\MetricsService;
use App\Support\BrazilianNumber;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RouteReportExportController extends Controller
{
    /**
     * Export the fuel report with one row per vehicle (PDF via print view or CSV for Excel).
     */
    public function __invoke(RouteReportExportRequest $request, MetricsService $metrics): View|StreamedResponse
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        $rows = $request->hasNoDriverScope()
            ? []
            : $metrics->getVehicleFuelReportRows(
                $startDate,
                $endDate,
                $request->vehicleIds(),
                $request->scopedDriverId(),
            );

        $periodLabel = Carbon::parse($startDate)->format('d/m/Y').' a '.Carbon::parse($endDate)->format('d/m/Y');

        if ($request->validated('format') === 'csv') {
            return $this->csvResponse($rows, $startDate, $endDate);
        }

        return view('reports.vehicle-fuel-export', [
            'rows' => $rows,
            'periodLabel' => $periodLabel,
        ]);
    }

    /**
     * @param  list<array{plate: string, drivers: list<string>, fuel_cost: float, liters: float, km: int, km_per_liter: float|null, cost_per_km: float|null}>  $rows
     */
    private function csvResponse(array $rows, string $startDate, string $endDate): StreamedResponse
    {
        $filename = sprintf('relatorio-combustivel-%s-a-%s.csv', $startDate, $endDate);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Vendedor', 'Veículo', 'Custo combustível', 'Litros', 'Consumo médio (km/L)', 'Preço médio (R$/km)', 'KM rodado'], ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    implode(', ', $row['drivers']),
                    $row['plate'],
                    'R$ '.BrazilianNumber::format($row['fuel_cost'], 2),
                    BrazilianNumber::format($row['liters'], 2).' L',
                    $row['km_per_liter'] !== null ? BrazilianNumber::format($row['km_per_liter'], 2).' km' : '—',
                    $row['cost_per_km'] !== null ? 'R$ '.BrazilianNumber::format($row['cost_per_km'], 2) : '—',
                    BrazilianNumber::format($row['km'], 0).' km',
                ], ';', '"', '');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
