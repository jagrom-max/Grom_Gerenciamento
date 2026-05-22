<?php

namespace App\Http\Controllers\Escalas;

use App\Http\Controllers\Controller;
use App\Models\EscalaPlantaoExterno;
use App\Models\EscalaPlantaoFuncionario;
use App\Models\RhFuncionario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Relatório imprimível de plantões externos.
 *
 * Modos:
 *  - Sem funcionario_id → relação completa de todos no período (DDM)
 *  - Com funcionario_id → plantões de um funcionário específico
 *
 * Rota: GET /escalas/plantoes/relatorio
 */
class PlantaoRelatorioController extends Controller
{
    public function index(Request $request): View
    {
        return $this->__invoke($request);
    }

    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'funcionario_id' => ['nullable', 'exists:rh_funcionarios,id'],
            'year'           => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month'          => ['nullable', 'integer', 'min:0', 'max:12'],
            'plantao_id'     => ['nullable', 'exists:escalas_plantoes_externos,id'],
        ]);

        $now   = Carbon::now();
        $year  = (int) ($filters['year']  ?? $now->year);
        $month = (int) ($filters['month'] ?? $now->month);

        $funcionarios = RhFuncionario::query()
            ->with('cargo')
            ->orderBy('name')
            ->get();

        $catalogo = EscalaPlantaoExterno::query()->orderBy('nome')->get();

        $funcionario = isset($filters['funcionario_id'])
            ? $funcionarios->firstWhere('id', $filters['funcionario_id'])
            : null;

        $plantaoFiltro = isset($filters['plantao_id'])
            ? $catalogo->firstWhere('id', $filters['plantao_id'])
            : null;

        // ── Consulta principal ──────────────────────────────────────────
        $query = EscalaPlantaoFuncionario::query()
            ->with(['funcionario.cargo', 'plantaoExterno'])
            ->when($funcionario, fn ($q) => $q->where('funcionario_id', $funcionario->id))
            ->when($plantaoFiltro, fn ($q) => $q->where('plantao_externo_id', $plantaoFiltro->id))
            ->whereYear('data', $year)
            ->when($month > 0, fn ($q) => $q->whereMonth('data', $month))
            ->orderBy('data')
            ->orderBy('funcionario_id');

        $registros = $query->get();

        // ── Totalizadores: por funcionário ──────────────────────────────
        $porFuncionario = $registros
            ->groupBy('funcionario_id')
            ->map(function ($group) {
                $func = $group->first()->funcionario;
                return [
                    'funcionario'  => $func,
                    'total'        => $group->count(),
                    'por_tipo'     => $group->groupBy(fn ($p) => $p->plantaoExterno?->nome ?? '—')
                                           ->map->count(),
                    'registros'    => $group,
                ];
            })
            ->sortBy('funcionario.name')
            ->values();

        // ── Totalizadores: por tipo de plantão ──────────────────────────
        $porTipo = $registros
            ->groupBy(fn ($p) => $p->plantaoExterno?->nome ?? '—')
            ->map(fn ($g, $tipo) => [
                'tipo'  => $tipo,
                'sigla' => $g->first()->plantaoExterno?->sigla ?? '—',
                'total' => $g->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $totalGeral = $registros->count();

        $periodoLabel = $month > 0
            ? Carbon::create($year, $month, 1)->translatedFormat('F \d\e Y')
            : (string) $year;

        return view('escalas.relatorio-plantoes', compact(
            'filters',
            'funcionarios', 'catalogo',
            'funcionario', 'plantaoFiltro',
            'porFuncionario', 'porTipo',
            'totalGeral', 'year', 'month', 'periodoLabel',
        ));
    }
}
