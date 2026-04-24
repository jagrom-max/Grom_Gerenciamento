<?php

namespace App\Http\Controllers\Produtividade;

use App\Http\Controllers\Controller;
use App\Models\AnaliseFlagramtePendencia;
use App\Models\Cartorio;
use App\Models\ImportItem;
use App\Models\ProductivityBoletim;
use App\Models\ProductivityFlagrante;
use App\Models\ProductivityStatMonthly;
use App\Support\Produtividade\ProdutividadeStatsDashboardData;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProdutividadeHubController extends Controller
{
    public function __construct(private readonly ProdutividadeStatsDashboardData $dashboardData) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        // Período atual
        $now   = now();
        $year  = (int) $now->year;
        $month = (int) $now->month;

        // Dados consolidados do mês atual via o serviço canônico
        $data = $this->dashboardData->build($user, $year, $month);

        $stats  = $data['selectedStats'];
        $ranking = $data['ranking'];             // top cartórios por flagrantes
        $breakdown = $data['monthlyBreakdown'];  // evolução mensal (12 meses)
        $pending = $data['pendingItems'];        // fila de sugestões pendentes

        // ── Comparativo mês anterior ──────────────────────────────────────────
        $prevYear  = $month === 1 ? $year - 1 : $year;
        $prevMonth = $month === 1 ? 12 : $month - 1;

        $cartorios = $data['cartorios'];
        $cartorioIds = $cartorios->pluck('id')->all();

        $statsPrev = ProductivityStatMonthly::query()
            ->where('reference_year', $prevYear)
            ->where('reference_month', $prevMonth)
            ->when($cartorioIds !== [], fn ($q) => $q->whereIn('cartorio_id', $cartorioIds))
            ->get();

        $prevStats = [
            'boletins_total' => (int) ProductivityBoletim::query()
                ->where('is_active', true)
                ->when($cartorioIds !== [], fn ($q) => $q->whereIn('cartorio_id', $cartorioIds))
                ->where('reference_year', $prevYear)
                ->where('reference_month', $prevMonth)
                ->count(),
            'flagrantes_total' => (int) $statsPrev->sum('flagrantes_total'),
            'ip_instaurados'   => (int) $statsPrev->sum('ip_instaurados'),
            'ip_relatados'     => (int) $statsPrev->sum('ip_relatados'),
        ];

        $boletinsAtual = ProductivityBoletim::query()
            ->where('is_active', true)
            ->when($cartorioIds !== [], fn ($q) => $q->whereIn('cartorio_id', $cartorioIds))
            ->where('reference_year', $year)
            ->where('reference_month', $month);

        $boletimStats = [
            'boletins_total' => (clone $boletinsAtual)->count(),
            'nao_flagrantes_total' => (clone $boletinsAtual)->where('is_flagrante', false)->count(),
            'mpu_sem_ip_total' => (clone $boletinsAtual)
                ->whereNotNull('mpu_numero')
                ->where('mpu_numero', '!=', '')
                ->where(function ($q): void {
                    $q->whereNull('num_ip')->orWhere('num_ip', '');
                })
                ->count(),
        ];

        // ── Alertas ───────────────────────────────────────────────────────────
        $pendingSemCartorio = (int) $data['summary']['pendencias_abertas'];
        $auditoriaPendentes = AnaliseFlagramtePendencia::where('status', 'pending')->count();

        // ── Evolução de flagrantes (12 meses para mini-chart) ─────────────────
        $maxFlagrante = $breakdown->max('flagrantes_total') ?: 1;

        // ── Cartórios com pendência ────────────────────────────────────────────
        $cartoriosComPendencia = ImportItem::query()
            ->whereNotNull('cartorio_id')
            ->where('import_status', 'pending')
            ->select('cartorio_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('cartorio_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('cartorio:id,number,name')
            ->get();

        $periodoLabel = Carbon::create($year, $month, 1)->translatedFormat('F \d\e Y');
        $periodoAnteriorLabel = Carbon::create($prevYear, $prevMonth, 1)->translatedFormat('F \d\e Y');

        return view('produtividade.hub', [
            'year'                   => $year,
            'month'                  => $month,
            'periodoLabel'           => $periodoLabel,
            'periodoAnteriorLabel'   => $periodoAnteriorLabel,
            'stats'                  => $stats,
            'boletimStats'           => $boletimStats,
            'prevStats'              => $prevStats,
            'ranking'                => $ranking->take(8),
            'breakdown'              => $breakdown,
            'maxFlagrante'           => $maxFlagrante,
            'pendingCount'           => $pending->count(),
            'pendingSemCartorio'     => $pendingSemCartorio,
            'auditoriaPendentes'     => $auditoriaPendentes,
            'cartoriosComPendencia'  => $cartoriosComPendencia,
            'totalCartorios'         => $cartorios->count(),
            'cartoriosAtivos'        => $cartorios->where('is_active', true)->count(),
            'summary'                => $data['summary'],
            'recentBatches'          => $data['recentBatches']->take(3),
        ]);
    }
}
