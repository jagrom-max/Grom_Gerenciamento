<?php

namespace App\Http\Controllers;

use App\Support\Pdf\HeadlessBrowserPdfRenderer;
use App\Support\ReportAsset;
use App\Support\Reports\ProdutividadeA4ReportData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RelatoriosProdutividadeA4PdfController extends Controller
{
    public function __construct(
        private readonly ProdutividadeA4ReportData $reportData,
        private readonly HeadlessBrowserPdfRenderer $pdfRenderer,
    ) {
    }

    public function __invoke(): BinaryFileResponse
    {
        $year = max((int) request()->integer('year', (int) now()->format('Y')), 2020);
        $month = max((int) request()->integer('month', (int) now()->format('n')), 1);

        $data = $this->reportData->build($year, $month) + [
            'brasaoSrc' => ReportAsset::dataUri('assets/brasao.png'),
            'logoSrc' => ReportAsset::dataUri('assets/logo_grom.png'),
            'watermarkSrc' => ReportAsset::dataUri('assets/marca_dagua.png'),
        ];

        $pdfPath = $this->pdfRenderer->renderBlade(
            'relatorios.produtividade_a4',
            $data,
            sprintf('produtividade-a4-%d-%02d', $year, $month)
        );

        return response()
            ->download($pdfPath, sprintf('produtividade-a4-%d-%02d.pdf', $year, $month), [
                'Content-Type' => 'application/pdf',
            ])
            ->deleteFileAfterSend(true);
    }
}
