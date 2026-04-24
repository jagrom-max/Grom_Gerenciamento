<?php

namespace App\Http\Controllers\Operacional;

use App\Http\Controllers\Controller;
use App\Models\RhFuncionario;
use App\Services\Escalas\LegacyEscalasReader;
use App\Services\Rh\LegacyFuncionariosReader;
use App\Support\Produtividade\ProdutividadeStatsDashboardData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OperacionalController extends Controller
{
    public function __construct(
        private readonly ProdutividadeStatsDashboardData $dashboardData,
        private readonly LegacyEscalasReader $legacyEscalasReader,
        private readonly LegacyFuncionariosReader $legacyFuncionariosReader,
    )
    {
    }

    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:0', 'max:12'],
            'cartorio_id' => ['nullable', 'exists:cartorios,id'],
        ]);

        $latestPeriod = $this->dashboardData->latestAvailablePeriod();
        $year = max((int) ($filters['year'] ?? $latestPeriod['year']), 2020);
        $month = array_key_exists('month', $filters)
            ? max((int) $filters['month'], 0)
            : $latestPeriod['month'];

        $data = $this->dashboardData->build(
            $request->user(),
            $year,
            $month,
            $filters['cartorio_id'] ?? null,
        );

        $legacyMonth = $month > 0 ? $month : (int) now()->month;
        $legacyYear = $year;
        $operationalLegacy = null;
        $operationalLegacyWarnings = [];

        try {
            $operationalLegacy = $this->legacyEscalasReader->snapshotForMonth($request->user(), $legacyYear, $legacyMonth);
        } catch (\Throwable $exception) {
            $operationalLegacyWarnings[] = $exception->getMessage();
        }

        try {
            $legacyFuncionarios = $this->legacyFuncionariosReader->snapshot();
        } catch (\Throwable $exception) {
            $legacyFuncionarios = [
                'available' => false,
                'source_name' => null,
                'employees' => [],
                'summary' => [
                    'total' => 0,
                    'ativos' => 0,
                    'concorrem_escala' => 0,
                    'em_afastamento' => 0,
                    'cargos_total' => 0,
                    'afastamentos_total' => 0,
                    'afastamentos_em_vigor' => 0,
                ],
                'warnings' => [$exception->getMessage()],
            ];
        }

        $phpFuncionarios = RhFuncionario::query()
            ->with(['cargo', 'afastamentos' => fn ($query) => $query->orderByDesc('start_date')])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(8)
            ->get();

        return view('operacional.index', $data + [
            'filters' => $filters,
            'operationalLegacy' => $operationalLegacy,
            'operationalLegacyWarnings' => $operationalLegacyWarnings,
            'legacyFuncionarios' => $legacyFuncionarios,
            'phpFuncionarios' => $phpFuncionarios,
            'phpFuncionariosSummary' => [
                'total' => $phpFuncionarios->count(),
                'ativos' => $phpFuncionarios->where('is_active', true)->count(),
                'concorrem_escala' => $phpFuncionarios->where('concorre_escala', true)->count(),
                'em_afastamento' => $phpFuncionarios->filter(fn (RhFuncionario $funcionario): bool => $funcionario->currentAfastamento() !== null)->count(),
            ],
        ]);
    }
}
