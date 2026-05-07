<?php

namespace App\Http\Controllers\Operacional;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Support\Reports\MandadosReportData;

class MandadosRelatorioController extends Controller
{
    public function index(Request $request): View
    {
        return $this->__invoke($request);
    }
    public function __construct(
        private readonly MandadosReportData $reportData,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'data_inicio'  => ['nullable', 'date'],
            'data_fim'     => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'tipo_sigla'   => ['nullable', 'string', 'max:8'],
            'procedimento' => ['nullable', 'in:Em Aberto,Cumprido,Revogado,todos'],
            'vencidos'     => ['nullable', 'boolean'],
        ]);

        return view('operacional.mandados.relatorio', $this->reportData->build($filters));
    }
}
