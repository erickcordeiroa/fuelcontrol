<?php

namespace App\Http\Controllers\Deliveries;

use App\Http\Controllers\Controller;
use App\Services\Deliveries\DeliveryImporter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DeliveryImportTemplateController extends Controller
{
    /**
     * Baixa o modelo .xlsx com as colunas esperadas pela importação e duas linhas de exemplo.
     */
    public function __invoke(): BinaryFileResponse
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'modelo-pedidos');
        unlink($tempFile);
        $path = $tempFile.'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_keys(DeliveryImporter::COLUMNS)));
        $writer->addRow(Row::fromValues([
            'C-1042', 'PED-88213', 'Mercearia São Jorge', 'Rua Shitiro Maeji', '410', 'Centro',
            'Registro', 'SP', '11900-000', 'Portão azul ao lado da farmácia', '1.284,90', 'Centro', '08:00', '12:00', '', '', '(13) 3821-4410',
        ]));
        $writer->addRow(Row::fromValues([
            'C-0877', 'PED-88214', 'Padaria Pão Dourado', 'Avenida Wild José de Souza', '2210', 'Vila Tupi',
            'Registro', 'SP', '11900-000', 'Esquina com a Avenida Clara Gianotti de Souza', '643,00', 'Oeste', '', '', '', '', '',
        ]));
        $writer->close();

        return response()->download($path, 'modelo-pedidos.xlsx')->deleteFileAfterSend();
    }
}
