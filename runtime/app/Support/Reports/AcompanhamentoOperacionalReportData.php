<?php

namespace App\Support\Reports;

use App\Models\User;
use App\Support\Produtividade\ProdutividadeStatsDashboardData;

class AcompanhamentoOperacionalReportData
{
    public function __construct(
        private readonly ProdutividadeStatsDashboardData $dashboardData,
    ) {
    }

    public function build(User $user, int $year, int $month, ?string $cartorioId = null): array
    {
        $dashboard = $this->dashboardData->build($user, $year, $month, $cartorioId);

        return [
            'year' => $year,
            'month' => $month,
            'periodLabel' => $dashboard['periodLabel'] ?? now()->copy()->setYear($year)->setMonth($month)->translatedFormat('F \\d\\e Y'),
            'generatedAt' => now(),
            'dashboard' => $dashboard,
            'scaleSnapshot' => null,
            'legacyPeople' => null,
            'warnings' => array_values(array_filter(
                $dashboard['summary']['pendencias_abertas'] > 0 ? ['Acompanhar a fila de pendencias abertas no periodo.'] : [],
            )),
        ];
    }
}
