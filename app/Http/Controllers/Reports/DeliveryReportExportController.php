<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\DeliveryReportExportRequest;
use App\Services\Billing\PlanLimits;
use App\Services\Deliveries\DeliveryReport;
use App\Support\BrazilianNumber;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryReportExportController extends Controller
{
    /**
     * Relatório de entregas em CSV (Excel): uma linha por motorista no período.
     */
    public function __invoke(DeliveryReportExportRequest $request, DeliveryReport $report, PlanLimits $planLimits): StreamedResponse
    {
        abort_unless(
            $planLimits->allows($request->user()->tenantOwnerId(), 'report_export'),
            403,
            __('Exportar relatórios não faz parte do seu plano. Mude de plano em Assinatura.'),
        );

        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');
        $rows = $report->build($startDate, $endDate, $request->driverId())['drivers'];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Motorista', 'Rotas', 'Paradas', 'Entregues', 'Não entregues', 'Sem baixa', 'Taxa de entrega', 'Valor entregue', 'Gasto (combustível + despesas)', 'Custo por entrega', 'Km planejados', 'Km rodados', 'Tempo médio de rota'], ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['driver'],
                    $row['routes'],
                    $row['stops'],
                    $row['delivered'],
                    $row['failed'],
                    $row['open'],
                    $row['successRate'] !== null ? BrazilianNumber::format($row['successRate'], 1).'%' : '—',
                    'R$ '.BrazilianNumber::format($row['amountDelivered'], 2),
                    'R$ '.BrazilianNumber::format($row['cost'], 2),
                    $row['costPerDelivery'] !== null ? 'R$ '.BrazilianNumber::format($row['costPerDelivery'], 2) : '—',
                    BrazilianNumber::format($row['plannedKm'], 1).' km',
                    BrazilianNumber::format($row['realKm'], 0).' km',
                    $row['avgRouteMinutes'] !== null ? $row['avgRouteMinutes'].' min' : '—',
                ], ';', '"', '');
            }

            fclose($handle);
        }, sprintf('relatorio-entregas-%s-a-%s.csv', $startDate, $endDate), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
