<?php

namespace App\Http\Controllers\Analise;

use App\Http\Controllers\Controller;
use App\Services\Analise\LegacyAnaliseReader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AnaliseEstatisticasController extends Controller
{
    public function __invoke(Request $request, LegacyAnaliseReader $legado): View
    {
        $sumario        = $legado->sumario();
        $evolucaoMensal = $legado->evolucaoPorMes(24);
        $evolucaoAnual  = $legado->evolucaoPorAno();
        $porDiaSemana   = $legado->porDiaSemana();

        // Todas as ocorrências
        $topNaturezas        = $legado->topNaturezasCompleto(20);
        $porArea             = $legado->porAreaCompleta();

        // Somente flagrantes
        $topNaturezasFlag    = $legado->topNaturezasFlagrante(20);
        $porAreaFlag         = $legado->porAreaFlagrante();

        $porCartorio    = $legado->porCartorio();
        $tiposVitimas   = $legado->tiposVitimas(10);

        $total = (int) ($sumario['total'] ?? 0);

        $taxaFlagrante = $total > 0
            ? round(($sumario['flagrantes'] / $total) * 100, 1)
            : 0.0;

        $maxMensal   = count($evolucaoMensal) > 0 ? max(array_map(fn ($r) => (int) $r['total'],       $evolucaoMensal))     : 1;
        $maxDia      = count($porDiaSemana)   > 0 ? max(array_map(fn ($r) => (int) $r['total'],       $porDiaSemana))       : 1;
        $maxArea     = count($porArea)         > 0 ? max(array_map(fn ($r) => (int) $r['total'],       $porArea))            : 1;
        $maxNat      = count($topNaturezas)    > 0 ? max(array_map(fn ($r) => (int) $r['total'],       $topNaturezas))       : 1;
        $maxAreaFlag = count($porAreaFlag)     > 0 ? max(array_map(fn ($r) => (int) $r['total'],       $porAreaFlag))        : 1;
        $maxNatFlag  = count($topNaturezasFlag)> 0 ? max(array_map(fn ($r) => (int) $r['total'],       $topNaturezasFlag))   : 1;

        return view('analise.estatisticas.index', [
            'sumario'           => $sumario,
            'taxaFlagrante'     => $taxaFlagrante,
            'evolucaoMensal'    => $evolucaoMensal,
            'evolucaoAnual'     => $evolucaoAnual,
            'porDiaSemana'      => $porDiaSemana,
            // todas as ocorrências
            'topNaturezas'      => $topNaturezas,
            'porArea'           => $porArea,
            // somente flagrantes
            'topNaturezasFlag'  => $topNaturezasFlag,
            'porAreaFlag'       => $porAreaFlag,
            // cartório e vítimas
            'porCartorio'       => $porCartorio,
            'tiposVitimas'      => $tiposVitimas,
            // maxes
            'maxMensal'         => max($maxMensal,   1),
            'maxDia'            => max($maxDia,      1),
            'maxArea'           => max($maxArea,     1),
            'maxNat'            => max($maxNat,      1),
            'maxAreaFlag'       => max($maxAreaFlag, 1),
            'maxNatFlag'        => max($maxNatFlag,  1),
        ]);
    }
}
