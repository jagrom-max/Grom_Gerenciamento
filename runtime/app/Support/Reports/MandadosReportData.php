<?php

namespace App\Support\Reports;

use App\Models\OperacionalMandado;
use Illuminate\Support\Carbon;

class MandadosReportData
{
    public function build(array $filters = []): array
    {
        $query = OperacionalMandado::query()
            ->orderBy('tipo_sigla')
            ->orderByDesc('data_emissao');

        if (!empty($filters['data_inicio'])) {
            $query->whereDate('data_emissao', '>=', $filters['data_inicio']);
        }

        if (!empty($filters['data_fim'])) {
            $query->whereDate('data_emissao', '<=', $filters['data_fim']);
        }

        if (!empty($filters['tipo_sigla']) && $filters['tipo_sigla'] !== 'todos') {
            $query->where('tipo_sigla', $filters['tipo_sigla']);
        }

        $procedimento = $filters['procedimento'] ?? 'todos';
        if ($procedimento && $procedimento !== 'todos') {
            $query->where('procedimento', $procedimento);
        }

        $today = Carbon::today();

        if (!empty($filters['vencidos'])) {
            $query->where('procedimento', 'Em Aberto')
                  ->whereDate('validade', '<', $today);
        }

        $mandados = $query->get();

        $summary = [
            'total'     => $mandados->count(),
            'em_aberto' => $mandados->where('procedimento', 'Em Aberto')->count(),
            'cumpridos' => $mandados->where('procedimento', 'Cumprido')->count(),
            'revogados' => $mandados->where('procedimento', 'Revogado')->count(),
            'vencidos'  => $mandados->filter(fn ($mandado) =>
                $mandado->procedimento === 'Em Aberto'
                && $mandado->validade !== null
                && $mandado->validade < $today
            )->count(),
        ];

        $porTipo = $mandados->groupBy('tipo_sigla')
            ->map(fn ($group) => $group->count());

        $period = $this->buildPeriodLabel($filters);

        return [
            'mandados' => $mandados,
            'summary' => $summary,
            'porTipo' => $porTipo,
            'filters' => $filters,
            'period' => $period,
            'today' => $today,
            'tiposSigla' => OperacionalMandado::TIPOS_SIGLA,
            'procedimentos' => OperacionalMandado::PROCEDIMENTOS,
        ];
    }

    private function buildPeriodLabel(array $filters): string
    {
        if (!empty($filters['data_inicio']) && !empty($filters['data_fim'])) {
            $period = Carbon::parse($filters['data_inicio'])->format('d/m/Y')
                . ' a '
                . Carbon::parse($filters['data_fim'])->format('d/m/Y');
        } elseif (!empty($filters['data_inicio'])) {
            $period = 'A partir de ' . Carbon::parse($filters['data_inicio'])->format('d/m/Y');
        } elseif (!empty($filters['data_fim'])) {
            $period = 'Até ' . Carbon::parse($filters['data_fim'])->format('d/m/Y');
        } else {
            $period = 'Todos os registros';
        }

        if (!empty($filters['procedimento']) && $filters['procedimento'] !== 'todos') {
            $period .= ' — ' . $filters['procedimento'];
        }

        if (!empty($filters['tipo_sigla']) && $filters['tipo_sigla'] !== 'todos') {
            $period .= ' — ' . $filters['tipo_sigla'];
        }

        return $period;
    }
}
