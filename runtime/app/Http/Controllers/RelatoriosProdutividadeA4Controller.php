<?php

namespace App\Http\Controllers;

use App\Support\ReportAsset;
use App\Support\Reports\ProdutividadeA4ReportData;
use Illuminate\Contracts\View\View;

class RelatoriosProdutividadeA4Controller extends Controller
{
    public function __construct(private readonly ProdutividadeA4ReportData $reportData)
    {
    }

    public function __invoke(): View
    {
        $year = max((int) request()->integer('year', (int) now()->format('Y')), 2020);
        $month = max((int) request()->integer('month', (int) now()->format('n')), 1);

        return view('relatorios.produtividade_a4', $this->reportData->build($year, $month) + [
            'compact' => true,
            'brasaoSrc' => ReportAsset::dataUri('assets/brasao.png'),
            'logoSrc' => ReportAsset::dataUri('assets/logo_grom.png'),
            'watermarkSrc' => ReportAsset::dataUri('assets/marca_dagua.png'),
        ]);
    }
}
