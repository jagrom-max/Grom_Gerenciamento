<?php

namespace App\Http\Controllers;

use App\Support\Pdf\HeadlessBrowserPdfRenderer;
use App\Support\ReportAsset;
use App\Support\Reports\AcompanhamentoOperacionalReportData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RelatoriosAcompanhamentoOperacionalPdfController extends Controller
{
    public function __construct(
        private readonly AcompanhamentoOperacionalReportData $reportData,
        private readonly HeadlessBrowserPdfRenderer $pdfRenderer,
    ) {
    }

    public function __invoke(): BinaryFileResponse
    {
        $year = max((int) request()->integer('year', (int) now()->format('Y')), 2020);
        $month = max((int) request()->integer('month', (int) now()->format('n')), 1);
        $cartorioId = request()->string('cartorio_id')->toString() ?: null;

        $data = $this->reportData->build(request()->user(), $year, $month, $cartorioId) + [
            'compact' => true,
            'brasaoSrc' => ReportAsset::dataUri('assets/brasao.png'),
            'logoSrc' => ReportAsset::dataUri('assets/logo_grom.png'),
            'watermarkSrc' => ReportAsset::dataUri('assets/marca_dagua.png'),
        ];

        $pdfPath = $this->pdfRenderer->renderBlade(
            'relatorios.acompanhamento_operacional',
            $data,
            sprintf('acompanhamento-operacional-%d-%02d', $year, $month)
        );

        return response()
            ->download($pdfPath, sprintf('acompanhamento-operacional-%d-%02d.pdf', $year, $month), [
                'Content-Type' => 'application/pdf',
            ])
            ->deleteFileAfterSend(true);
    }
}
