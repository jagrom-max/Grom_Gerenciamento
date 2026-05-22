<?php

namespace App\Http\Controllers\Operacional;

use App\Http\Controllers\Controller;
use App\Models\OperacionalMandado;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MandadosStatsController extends Controller
{
    public function index(Request $request): View
    {
        return $this->__invoke($request);
    }

    public function __invoke(Request $request): View
    {
        $year  = (int) ($request->input('year',  now()->year));
        $month = (int) ($request->input('month', 0));       // 0 = todo o ano

        // ── Base query ────────────────────────────────────────────────────
        $base = OperacionalMandado::query();

        if ($month > 0) {
            $base->whereYear('created_at', $year)->whereMonth('created_at', $month);
        } else {
            $base->whereYear('created_at', $year);
        }

        $todos = $base->get();

        // ── Por tipo (sigla) ──────────────────────────────────────────────
        $porTipo = collect(OperacionalMandado::TIPOS_SIGLA)
            ->map(function (string $label, string $sigla) use ($todos): array {
                $grupo = $todos->where('tipo_sigla', $sigla);
                return [
                    'sigla'       => $sigla,
                    'label'       => $label,
                    'total'       => $grupo->count(),
                    'em_aberto'   => $grupo->where('procedimento', 'Em Aberto')->count(),
                    'cumpridos'   => $grupo->where('procedimento', 'Cumprido')->count(),
                    'revogados'   => $grupo->where('procedimento', 'Revogado')->count(),
                ];
            })
            ->values();

        // ── Por procedimento (totais gerais) ──────────────────────────────
        $totalGeral    = $todos->count();
        $totalEmAberto = $todos->where('procedimento', 'Em Aberto')->count();
        $totalCumprido = $todos->where('procedimento', 'Cumprido')->count();
        $totalRevogado = $todos->where('procedimento', 'Revogado')->count();

        // ── Vencidos (em aberto e com validade < hoje) ────────────────────
        $hoje = Carbon::today();
        $totalVencidos = $todos
            ->where('procedimento', 'Em Aberto')
            ->filter(fn ($m) => $m->validade && $m->validade->lt($hoje))
            ->count();

        // ── Histórico mensal (últimos 12 meses, por emissão) ──────────────
        // Pega todos (sem filtro de ano) para construir o histórico
        $historicoBase = OperacionalMandado::query()
            ->whereYear('data_emissao', '>=', $year - 1)
            ->whereYear('data_emissao', '<=', $year)
            ->get();

        $historicoMensal = [];
        for ($m = 1; $m <= 12; $m++) {
            $grupo = $historicoBase
                ->filter(fn ($i) => $i->data_emissao
                    && $i->data_emissao->year  === $year
                    && $i->data_emissao->month === $m
                );
            $historicoMensal[] = [
                'mes'       => Carbon::create($year, $m)->isoFormat('MMM'),
                'total'     => $grupo->count(),
                'em_aberto' => $grupo->where('procedimento', 'Em Aberto')->count(),
                'cumpridos' => $grupo->where('procedimento', 'Cumprido')->count(),
            ];
        }

        // ── Período label ─────────────────────────────────────────────────
        $nomesMeses = [
            1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',
            5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',
            9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro',
        ];
        $periodoLabel = $month > 0
            ? "{$nomesMeses[$month]} de {$year}"
            : "Ano de {$year}";

        return view('operacional.mandados.stats', compact(
            'porTipo',
            'totalGeral',
            'totalEmAberto',
            'totalCumprido',
            'totalRevogado',
            'totalVencidos',
            'historicoMensal',
            'periodoLabel',
            'year',
            'month',
        ));
    }
}
