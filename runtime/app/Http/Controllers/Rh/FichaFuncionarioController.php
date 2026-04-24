<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\EscalaPlantaoFuncionario;
use App\Models\RhFuncionario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Ficha individual imprimível de um funcionário.
 * Rota: GET /rh/funcionarios/{funcionario}/ficha
 */
class FichaFuncionarioController extends Controller
{
    public function __invoke(Request $request, RhFuncionario $funcionario): View
    {
        $funcionario->load(['cargo', 'afastamentos' => fn ($q) => $q->orderBy('start_date')]);

        // Plantões externos deste funcionário, mais recentes primeiro
        $plantoes = EscalaPlantaoFuncionario::query()
            ->with('plantaoExterno')
            ->where('funcionario_id', $funcionario->id)
            ->orderByDesc('data')
            ->get();

        $totalPlantoes = $plantoes->count();

        // Agrupar plantões por tipo (plantao_externo)
        $plantoesPorTipo = $plantoes->groupBy(
            fn ($p) => $p->plantaoExterno?->nome ?? 'Sem tipo'
        )->map(fn ($g) => $g->count());

        return view('rh.ficha-funcionario', compact(
            'funcionario',
            'plantoes',
            'totalPlantoes',
            'plantoesPorTipo',
        ));
    }
}
