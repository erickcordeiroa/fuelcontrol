<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Relatório de Combustível') }} — {{ $periodLabel }}</title>
        <style>
            @page { size: A4 landscape; margin: 14mm; }
            * { box-sizing: border-box; }
            body { margin: 0; padding: 24px; font-family: Calibri, Arial, Helvetica, sans-serif; color: #000; background: #fff; }
            .toolbar { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 16px; }
            .toolbar button, .toolbar a { padding: 6px 14px; border: 1px solid #333; border-radius: 6px; background: #fff; color: #000; font-size: 13px; cursor: pointer; text-decoration: none; }
            h1 { margin: 0 0 4px; font-size: 20px; text-align: center; }
            .period { margin: 0 0 16px; text-align: center; font-size: 13px; }
            table { width: 100%; border-collapse: collapse; font-size: 13px; }
            th, td { border: 1px solid #000; padding: 4px 8px; }
            th { font-weight: 700; text-transform: uppercase; text-align: center; white-space: nowrap; }
            td { text-align: center; }
            td.seller { text-align: left; }
            .empty { padding: 16px; text-align: center; }
            @media print {
                body { padding: 0; }
                .toolbar { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="toolbar">
            <a href="{{ route('reports') }}">{{ __('Voltar') }}</a>
            <button type="button" onclick="window.print()">{{ __('Imprimir / Salvar PDF') }}</button>
        </div>

        <h1>{{ __('Relatório de Combustível') }}</h1>
        <p class="period">{{ __('Período: :period', ['period' => $periodLabel]) }}</p>

        <table>
            <thead>
                <tr>
                    <th>{{ __('Vendedor') }}</th>
                    <th>{{ __('Veículo') }}</th>
                    <th>{{ __('Custo combustível') }}</th>
                    <th>{{ __('Litros') }}</th>
                    <th>{{ __('Consumo médio') }}</th>
                    <th>{{ __('Preço médio') }}</th>
                    <th>{{ __('KM rodado') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="seller">{{ $row['drivers'] !== [] ? implode(', ', $row['drivers']) : '—' }}</td>
                        <td>{{ $row['plate'] }}</td>
                        <td>{{ format_money_brl($row['fuel_cost']) }}</td>
                        <td>{{ number_format($row['liters'], 2, ',', '.') }} L</td>
                        <td>{{ $row['km_per_liter'] !== null ? number_format($row['km_per_liter'], 2, ',', '.').' KM' : '—' }}</td>
                        <td>{{ $row['cost_per_km'] !== null ? format_money_brl($row['cost_per_km']) : '—' }}</td>
                        <td>{{ number_format($row['km'], 0, ',', '.') }} KM</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">{{ __('Nenhum lançamento no período.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <script>
            window.addEventListener('load', () => window.print());
        </script>
    </body>
</html>
