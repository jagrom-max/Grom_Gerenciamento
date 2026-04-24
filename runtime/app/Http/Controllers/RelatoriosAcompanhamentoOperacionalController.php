<?php

namespace App\Http\Controllers;

use App\Support\ReportAsset;
use App\Support\Reports\AcompanhamentoOperacionalReportData;
use Illuminate\Contracts\View\View;

class RelatoriosAcompanhamentoOperacionalController extends Controller
{
    public function __construct(private readonly AcompanhamentoOperacionalReportData $reportData)
    {
    }

    public function __invoke(): View
    {
        $year = max((int) request()->integer('year', (int) now()->format('Y')), 2020);
        $month = max((int) request()->integer('month', (int) now()->format('n')), 1);
        $cartorioId = request()->string('cartorio_id')->toString() ?: null;

        return view('relatorios.acompanhamento_operacional', $this->reportData->build(
            request()->user(),
            $year,
            $month,
            $cartorioId,
        ) + [
            'compact' => false,
            'brasaoSrc' => ReportAsset::dataUri('assets/brasao.png'),
            'logoSrc' => ReportAsset::dataUri('assets/logo_grom.png'),
            'watermarkSrc' => ReportAsset::dataUri('assets/marca_dagua.png'),
        ]);
    }
}
