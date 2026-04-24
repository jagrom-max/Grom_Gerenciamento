<?php

namespace App\Http\Controllers\Operacional;

use App\Http\Controllers\Controller;
use App\Support\Pdf\HeadlessBrowserPdfRenderer;
use App\Support\ReportAsset;
use App\Support\Reports\MandadosReportData;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MandadosRelatorioPdfController extends Controller
{
    public function __construct(
        private readonly MandadosReportData $reportData,
        private readonly HeadlessBrowserPdfRenderer $pdfRenderer,
    ) {
    }

    public function __invoke(Request $request): BinaryFileResponse
    {
        $filters = $request->validate([
            'data_inicio'  => ['nullable', 'date'],
            'data_fim'     => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'tipo_sigla'   => ['nullable', 'string', 'max:8'],
            'procedimento' => ['nullable', 'in:Em Aberto,Cumprido,Revogado,todos'],
            'vencidos'     => ['nullable', 'boolean'],
        ]);

        $data = $this->reportData->build($filters) + [
            'brasaoSrc' => ReportAsset::dataUri('assets/pdf/brasao_pdf.png'),
            'logoSrc' => ReportAsset::dataUri('assets/pdf/logo_grom_pdf.png'),
            'watermarkSrc' => ReportAsset::dataUri('assets/marca_dagua.png'),
        ];

        $pdfPath = $this->pdfRenderer->renderBlade(
            'operacional.mandados.relatorio',
            $data,
            'mandados-relatorio'
        );

        return response()
            ->download($pdfPath, 'relatorio-de-mandados.pdf', [
                'Content-Type' => 'application/pdf',
            ])
            ->deleteFileAfterSend(true);
    }
}
