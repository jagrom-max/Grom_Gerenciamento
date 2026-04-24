<?php

namespace App\Support\Reports;

use App\Models\User;
use App\Services\Escalas\LegacyEscalasReader;
use App\Services\Rh\LegacyFuncionariosReader;
use App\Support\Produtividade\ProdutividadeStatsDashboardData;

class AcompanhamentoOperacionalReportData
{
    public function __construct(
        private readonly ProdutividadeStatsDashboardData $dashboardData,
        private readonly LegacyEscalasReader $legacyEscalasReader,
        private readonly LegacyFuncionariosReader $legacyFuncionariosReader,
    ) {
    }

    public function build(User $user, int $year, int $month, ?string $cartorioId = null): array
    {
        $dashboard = $this->dashboardData->build($user, $year, $month, $cartorioId);
        $scaleSnapshot = null;
        $scaleWarnings = [];
        $legacyPeople = null;
        $peopleWarnings = [];

        try {
            $scaleSnapshot = $this->legacyEscalasReader->snapshotForMonth($user, $year, $month);
        } catch (\Throwable $exception) {
            $scaleWarnings[] = $exception->getMessage();
        }

        try {
            $legacyPeople = $this->legacyFuncionariosReader->snapshot();
        } catch (\Throwable $exception) {
            $peopleWarnings[] = $exception->getMessage();
        }

        return [
            'year' => $year,
            'month' => $month,
            'periodLabel' => $dashboard['periodLabel'] ?? now()->copy()->setYear($year)->setMonth($month)->translatedFormat('F \\d\\e Y'),
            'generatedAt' => now(),
            'dashboard' => $dashboard,
            'scaleSnapshot' => $scaleSnapshot,
            'legacyPeople' => $legacyPeople,
            'warnings' => array_values(array_filter(array_merge(
                $dashboard['summary']['pendencias_abertas'] > 0 ? ['Acompanhar a fila de pendencias abertas no periodo.'] : [],
                $scaleWarnings,
                $peopleWarnings,
            ))),
        ];
    }
}
