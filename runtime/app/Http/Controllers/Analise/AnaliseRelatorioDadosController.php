<?php

namespace App\Http\Controllers\Analise;

use App\Http\Controllers\Controller;
use App\Services\Analise\NaturezaNorm;
use App\Support\Pdf\HeadlessBrowserPdfRenderer;
use App\Support\ReportAsset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Relatórios analíticos impressos de Análise de Dados.
 *
 * Tipos disponíveis (parâmetro ?tipo=):
 *  flagrantes        – Flagrantes por período / área / natureza
 *  naturezas         – Total por Natureza (ranking)
 *  areas             – Ocorrências por Área
 *  ips-cartorio      – IPs por Cartório
 *  ips-totais        – IPs Totais (lista)
 */
class AnaliseRelatorioDadosController extends Controller
{
    private const TIPOS = [
        'flagrantes'   => 'Flagrantes',
        'naturezas'    => 'Total por Natureza',
        'areas'        => 'Ocorrências por Área',
        'ips-cartorio' => 'IPs por Cartório',
        'ips-totais'   => 'IPs Totais',
    ];

    public function __construct(
        private readonly HeadlessBrowserPdfRenderer $pdfRenderer,
    ) {
    }

    public function index(): View
    {
        $kpis = $this->kpis();
        return view('analise.relatorios.index', [
            'tipos'       => self::TIPOS,
            'kpis'        => $kpis,
            'generatedAt' => now(),
        ]);
    }

    public function show(Request $request, string $tipo): View
    {
        abort_unless(array_key_exists($tipo, self::TIPOS), 404);

        $base = [
            'tipo'           => $tipo,
            'tipoLabel'      => self::TIPOS[$tipo],
            'generatedAt'    => now(),
            'geradoEm'       => now()->format('d/m/Y H:i'),
            'brasaoSrc'      => asset('assets/brasao.png'),
            'logoSrc'        => asset('assets/logo_grom.png'),
            'watermarkSrc'   => asset('assets/marca_dagua.png'),
        ];

        $data = match ($tipo) {
            'flagrantes'   => $this->dataFlagrantes(),
            'naturezas'    => $this->dataNaturezas(),
            'areas'        => $this->dataAreas(),
            'ips-cartorio' => $this->dataIpsCartorio(),
            'ips-totais'   => $this->dataIpsTotais(),
        };

        return view("analise.relatorios.dados-{$tipo}", array_merge($base, $data));
    }

    public function downloadPdf(string $tipo): BinaryFileResponse
    {
        abort_unless(array_key_exists($tipo, self::TIPOS), 404);

        $base = [
            'tipo'           => $tipo,
            'tipoLabel'      => self::TIPOS[$tipo],
            'generatedAt'    => now(),
            'geradoEm'       => now()->format('d/m/Y H:i'),
            'brasaoSrc'      => ReportAsset::dataUri('assets/brasao.png'),
            'logoSrc'        => ReportAsset::dataUri('assets/logo_grom.png'),
            'watermarkSrc'   => ReportAsset::dataUri('assets/marca_dagua.png'),
        ];

        $data = match ($tipo) {
            'flagrantes'   => $this->dataFlagrantes(),
            'naturezas'    => $this->dataNaturezas(),
            'areas'        => $this->dataAreas(),
            'ips-cartorio' => $this->dataIpsCartorio(),
            'ips-totais'   => $this->dataIpsTotais(),
        };

        $label = self::TIPOS[$tipo];
        $prefix = 'analise-' . $tipo . '-' . now()->format('Y-m-d');

        $pdfPath = $this->pdfRenderer->renderBlade(
            "analise.relatorios.dados-{$tipo}",
            array_merge($base, $data),
            $prefix
        );

        return response()
            ->download($pdfPath, $prefix . '.pdf', ['Content-Type' => 'application/pdf'])
            ->deleteFileAfterSend(true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // KPIs gerais (para o index)
    // ─────────────────────────────────────────────────────────────────────────

    private function kpis(): array
    {
        $row = DB::table('analise_bos')
            ->selectRaw('
                COUNT(*) AS total,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes,
                SUM(CASE WHEN num_ip IS NOT NULL AND trim(num_ip) <> \'\' THEN 1 ELSE 0 END) AS com_ip,
                SUM(CASE WHEN mpu_numero IS NOT NULL AND trim(mpu_numero) <> \'\' THEN 1 ELSE 0 END) AS com_mpu
            ')
            ->first();

        return [
            'total'      => (int) ($row->total      ?? 0),
            'flagrantes' => (int) ($row->flagrantes ?? 0),
            'com_ip'     => (int) ($row->com_ip     ?? 0),
            'com_mpu'    => (int) ($row->com_mpu    ?? 0),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FLAGRANTES
    // ─────────────────────────────────────────────────────────────────────────

    private function dataFlagrantes(): array
    {
        // KPIs de flagrante
        $kpi = DB::table('analise_bos')
            ->selectRaw("
                COUNT(*) AS total_bos,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS total_flagrantes,
                SUM(CASE WHEN flagrante = 1 AND ato_infracional = 1 THEN 1 ELSE 0 END) AS atos_infracionais,
                SUM(CASE WHEN flagrante = 1 AND (num_ip IS NOT NULL AND trim(num_ip) <> '') THEN 1 ELSE 0 END) AS flagrantes_com_ip
            ")
            ->first();

        // Evolução mensal de flagrantes
        $evolucao = DB::table('analise_bos')
            ->whereNotNull('data_ocorrencia')
            ->where('data_ocorrencia', '!=', '')
            ->selectRaw("
                strftime('%Y-%m', data_ocorrencia) AS periodo,
                COUNT(*) AS total,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes
            ")
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->limit(24)
            ->get()->toArray();

        // Flagrantes por área
        $porArea = DB::table('analise_bos')
            ->where('flagrante', 1)
            ->selectRaw("
                COALESCE(NULLIF(trim(area_fato), ''), 'Não informada') AS area,
                COUNT(*) AS total,
                SUM(CASE WHEN ato_infracional = 1 THEN 1 ELSE 0 END) AS atos_infracionais,
                SUM(CASE WHEN num_ip IS NOT NULL AND trim(num_ip) <> '' THEN 1 ELSE 0 END) AS com_ip
            ")
            ->groupBy('area')
            ->orderByDesc('total')
            ->get()->toArray();

        // Flagrantes por cartório designado (lavrado)
        $porLavrado = DB::table('analise_bos')
            ->where('flagrante', 1)
            ->selectRaw("
                COALESCE(NULLIF(trim(lavrado), ''), 'Não informado') AS lavrado,
                COUNT(*) AS total
            ")
            ->groupBy('lavrado')
            ->orderByDesc('total')
            ->get()->toArray();

        // Naturezas dos flagrantes
        $natsRaw = DB::table('analise_bo_naturezas')
            ->join('analise_bos', 'analise_bo_naturezas.spj', '=', 'analise_bos.spj')
            ->where('analise_bos.flagrante', 1)
            ->whereNotNull('analise_bo_naturezas.natureza_label')
            ->where('analise_bo_naturezas.natureza_label', '!=', '')
            ->selectRaw("
                analise_bo_naturezas.natureza_label,
                COUNT(DISTINCT analise_bo_naturezas.spj) AS total
            ")
            ->groupBy('analise_bo_naturezas.natureza_label')
            ->get();

        $naturezas = $this->aggregateNaturezas($natsRaw, ['total']);

        // TOP 9 + "Demais" = 10 linhas
        if (count($naturezas) > 9) {
            $demaisTotal = array_sum(array_column(array_slice($naturezas, 9), 'total'));
            $naturezas   = array_slice($naturezas, 0, 9);
            $naturezas[] = ['natureza' => 'Demais', 'total' => $demaisTotal];
        }

        $maxEvolucao = max(1, ...array_map(fn ($r) => (int) ($r->total ?? 0), $evolucao));

        return compact('kpi', 'evolucao', 'porArea', 'porLavrado', 'naturezas', 'maxEvolucao');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NATUREZAS
    // ─────────────────────────────────────────────────────────────────────────

    private function dataNaturezas(): array
    {
        $natsRaw = DB::table('analise_bo_naturezas')
            ->join('analise_bos', 'analise_bo_naturezas.spj', '=', 'analise_bos.spj')
            ->whereNotNull('analise_bo_naturezas.natureza_label')
            ->where('analise_bo_naturezas.natureza_label', '!=', '')
            ->selectRaw("
                analise_bo_naturezas.natureza_label,
                COUNT(DISTINCT analise_bo_naturezas.spj) AS total,
                SUM(CASE WHEN analise_bos.flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes
            ")
            ->groupBy('analise_bo_naturezas.natureza_label')
            ->get();

        $naturezas = $this->aggregateNaturezas($natsRaw, ['total', 'flagrantes']);

        $totalGeral = (int) DB::table('analise_bos')->count();
        $maxTotal   = max(1, ...array_column($naturezas, 'total'));

        return compact('naturezas', 'totalGeral', 'maxTotal');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÁREAS
    // ─────────────────────────────────────────────────────────────────────────

    private function dataAreas(): array
    {
        $areas = DB::table('analise_bos')
            ->selectRaw("
                COALESCE(NULLIF(trim(area_fato), ''), 'Não informada') AS area,
                COUNT(*) AS total,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes,
                SUM(CASE WHEN ato_infracional = 1 THEN 1 ELSE 0 END) AS atos_infracionais,
                SUM(CASE WHEN num_ip IS NOT NULL AND trim(num_ip) <> '' THEN 1 ELSE 0 END) AS com_ip,
                SUM(CASE WHEN mpu_numero IS NOT NULL AND trim(mpu_numero) <> '' THEN 1 ELSE 0 END) AS com_mpu
            ")
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();

        $totalGeral = (int) $areas->sum('total');
        $maxTotal   = max(1, (int) $areas->max('total'));

        return compact('areas', 'totalGeral', 'maxTotal');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // IPs POR CARTÓRIO
    // ─────────────────────────────────────────────────────────────────────────

    private function dataIpsCartorio(): array
    {
        $porCartorio = DB::table('analise_bos')
            ->selectRaw("
                COALESCE(NULLIF(trim(cartorio_ip), ''), 'Sem cartório') AS cartorio,
                COUNT(*) AS total_bos,
                SUM(CASE WHEN num_ip IS NOT NULL AND trim(num_ip) <> '' THEN 1 ELSE 0 END) AS total_ips,
                SUM(CASE WHEN mpu_numero IS NOT NULL AND trim(mpu_numero) <> '' THEN 1 ELSE 0 END) AS com_mpu,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes,
                SUM(CASE WHEN ato_infracional = 1 THEN 1 ELSE 0 END) AS atos_infracionais
            ")
            ->groupBy('cartorio')
            ->orderByDesc('total_bos')
            ->get();

        $totalBos = (int) $porCartorio->sum('total_bos');
        $totalIps = (int) $porCartorio->sum('total_ips');

        return compact('porCartorio', 'totalBos', 'totalIps');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // IPs TOTAIS (lista)
    // ─────────────────────────────────────────────────────────────────────────

    private function dataIpsTotais(): array
    {
        $ips = DB::table('analise_bos')
            ->whereNotNull('num_ip')
            ->where('num_ip', '!=', '')
            ->leftJoin('analise_bo_naturezas', function ($join) {
                $join->on('analise_bo_naturezas.spj', '=', 'analise_bos.spj')
                     ->where('analise_bo_naturezas.slot', '=', 1);
            })
            ->select(
                'analise_bos.spj_fmt',
                'analise_bos.data_ocorrencia',
                'analise_bos.num_ip',
                'analise_bos.cartorio_ip',
                'analise_bos.cnj_ip',
                'analise_bos.mpu_numero',
                'analise_bos.area_fato',
                'analise_bos.lavrado',
                'analise_bos.flagrante',
                'analise_bo_naturezas.natureza_label AS natureza_principal'
            )
            ->orderByDesc('analise_bos.spj_year')
            ->orderByDesc('analise_bos.spj_seq')
            ->get()
            ->map(function ($row) {
                $row->natureza_principal = $row->natureza_principal
                    ? NaturezaNorm::label($row->natureza_principal)
                    : '—';
                return $row;
            })
            ->toArray();

        // Distribuição por natureza principal
        $porNatureza = [];
        foreach ($ips as $ip) {
            $nat = $ip->natureza_principal ?: 'Não informada';
            $porNatureza[$nat] = ($porNatureza[$nat] ?? 0) + 1;
        }
        arsort($porNatureza);

        $totalIps     = count($ips);
        $totalComCnj  = count(array_filter($ips, fn ($r) => ! empty($r->cnj_ip)));
        $totalComMpu  = count(array_filter($ips, fn ($r) => ! empty($r->mpu_numero)));
        $totalFlag    = count(array_filter($ips, fn ($r) => $r->flagrante));

        return compact('ips', 'porNatureza', 'totalIps', 'totalComCnj', 'totalComMpu', 'totalFlag');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: agrupa e normaliza naturezas
    // ─────────────────────────────────────────────────────────────────────────

    /** @param string[] $sumFields */
    private function aggregateNaturezas(\Illuminate\Support\Collection $rows, array $sumFields): array
    {
        $agg = [];
        foreach ($rows as $row) {
            $label = NaturezaNorm::label((string) ($row->natureza_label ?? ''));
            if ($label === '') {
                continue;
            }
            if (! isset($agg[$label])) {
                $agg[$label] = array_merge(['natureza' => $label], array_fill_keys($sumFields, 0));
            }
            foreach ($sumFields as $f) {
                $agg[$label][$f] += (int) ($row->{$f} ?? 0);
            }
        }

        uasort($agg, fn ($a, $b) => $b['total'] - $a['total']);

        return array_values($agg);
    }
}
