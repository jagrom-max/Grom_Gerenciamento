<?php

namespace App\Http\Controllers\Analise;

use App\Http\Controllers\Controller;
use App\Services\Analise\AnaliseBoImportService;
use App\Services\Analise\NaturezaNorm;
use App\Services\Legacy\LegacyDatabaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnaliseBoSearchController extends Controller
{
    public function __invoke(Request $request, LegacyDatabaseService $legacyDb): View
    {
        $q        = trim((string) ($request->query('q', '')));
        $papel    = $request->query('papel', ''); // '' | 'vitima' | 'autor'
        $imprimir = (bool) $request->query('imprimir', false);

        $results       = [];
        $hasSearch     = mb_strlen($q) >= 3;
        $legadoWarning = ! $legacyDb->isAvailable();

        if ($hasSearch) {

            // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
            // BANCO PHP â€” um resultado por BO (agrupa pessoas + naturezas)
            // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
            // Divide a query em palavras para busca por fragmentos (ex: "joao silva" → AND LIKE %joao% AND LIKE %silva%)
            $words = array_values(array_filter(
                array_map('trim', preg_split('/\s+/', AnaliseBoImportService::normKey($q)) ?: [])
            ));

            // Pessoas que correspondem (vítimas)
            $vitPessoas = $papel !== 'autor'
                ? (function () use ($words) {
                    $q = DB::table('analise_bo_vitimas')
                        ->select('spj', 'nome', 'tipo', DB::raw("'Vítima' as papel"));
                    foreach ($words as $w) {
                        $q->where('nome_key', 'like', '%' . $w . '%');
                    }
                    return $q->get();
                })()
                : collect();

            // Pessoas que correspondem (autores)
            $autPessoas = $papel !== 'vitima'
                ? (function () use ($words) {
                    $q = DB::table('analise_bo_autores')
                        ->select('spj', 'nome', DB::raw("'' as tipo"), DB::raw("'Autor' as papel"));
                    foreach ($words as $w) {
                        $q->where('nome_key', 'like', '%' . $w . '%');
                    }
                    return $q->get();
                })()
                : collect();

            // SPJs Ãºnicos encontrados no PHP (limite 80 BOs)
            /** @var Collection $pessoasPorSpjPhp */
            $pessoasPorSpjPhp = $vitPessoas->concat($autPessoas)->groupBy('spj');
            $phpSpjs          = $pessoasPorSpjPhp->keys()->take(80)->values();

            if ($phpSpjs->isNotEmpty()) {
                // Dados principais dos BOs
                $bosPhp = DB::table('analise_bos')
                    ->whereIn('spj', $phpSpjs)
                    ->orderByDesc('spj_year')
                    ->orderByDesc('spj_seq')
                    ->get()
                    ->keyBy('spj');

                // Naturezas para esses BOs
                $naturezasPhp = DB::table('analise_bo_naturezas')
                    ->whereIn('spj', $phpSpjs)
                    ->whereNotNull('natureza_label')
                    ->where('natureza_label', '!=', '')
                    ->orderBy('spj')->orderBy('slot')
                    ->select('spj', 'natureza_label', 'tentado_consumado')
                    ->get()
                    ->groupBy('spj');

                foreach ($phpSpjs as $spj) {
                    $bo      = $bosPhp->get($spj);
                    $pessoas = $pessoasPorSpjPhp->get($spj, collect());
                    $nats    = $naturezasPhp->get($spj, collect());

                    $results[] = $this->buildRow($spj, $bo, $pessoas, $nats, 'php', (bool)($bo->flagrante ?? false), (bool)($bo->ato_infracional ?? false));
                }
            }

            // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
            // BANCO LEGADO â€” mesma lÃ³gica
            // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
            if ($legacyDb->isAvailable()) {
                // Fragmentos normalizados para busca no legado (sem acentos, cada palavra separada)
                $legWords = array_values(array_filter(
                    array_map(fn ($w) => strtolower(trim(str_replace(
                        ['á','à','ã','â','é','ê','í','ó','ô','õ','ú','ç'],
                        ['a','a','a','a','e','e','i','o','o','o','u','c'],
                        $w
                    ))), preg_split('/\s+/', $q) ?: [])
                ));
                $legCond  = implode(' AND ', array_fill(0, count($legWords), 'nome_key LIKE ?'));
                $legWhere = array_map(fn ($w) => '%' . $w . '%', $legWords);

                $legVit = $papel !== 'autor'
                    ? $legacyDb->fetchAll(
                        "SELECT spj, nome_upper AS nome, tipo, 'Vítima' AS papel
                         FROM analise_vitimas
                         WHERE ({$legCond})
                           AND (
                               tipo IS NULL
                               OR (
                                   tipo NOT LIKE '%TESTEMUNHA%'
                                   AND upper(trim(tipo)) NOT IN ('DECLARANTE', 'CONDUTOR', 'AUTOR')
                               )
                           )
                         LIMIT 120",
                        $legWhere
                    )
                    : [];

                $legAut = $papel !== 'vitima'
                    ? $legacyDb->fetchAll(
                        "SELECT spj, nome_upper AS nome, '' AS tipo, 'Autor' AS papel
                         FROM analise_autores
                         WHERE {$legCond}
                         LIMIT 120",
                        $legWhere
                    )
                    : [];

                // Agrupa pessoas por SPJ
                $legPessoas = [];
                foreach (array_merge($legVit, $legAut) as $p) {
                    $legPessoas[$p['spj']][] = $p;
                }

                // Limita a 80 BOs legados
                $legSpjs = array_slice(array_keys($legPessoas), 0, 80);

                if (! empty($legSpjs)) {
                    $placeholders = implode(',', array_fill(0, count($legSpjs), '?'));

                    // Dados das ocorrÃªncias
                    $bosLeg = $legacyDb->fetchAll(
                        "SELECT spj, spj_fmt, data_ocorrencia,
                                COALESCE(lavrado, '') AS lavrado,
                                area_fato,
                                COALESCE(flagrante, 0) AS flagrante,
                                COALESCE(ato_infracional, 0) AS ato_infracional,
                                mpu_numero, cnj_mpu, cartorio_designado,
                                num_ip, cartorio_ip, cnj_ip_importado AS cnj_ip,
                                spj_year, spj_seq
                         FROM analise_ocorrencias
                         WHERE spj IN ({$placeholders})
                         ORDER BY spj_year DESC, spj_seq DESC",
                        $legSpjs
                    );
                    $bosLegMap = [];
                    foreach ($bosLeg as $b) {
                        $bosLegMap[$b['spj']] = $b;
                    }

                    // Naturezas
                    $natsLeg = $legacyDb->fetchAll(
                        "SELECT spj, natureza_label, tentado_consumado
                         FROM analise_naturezas
                         WHERE spj IN ({$placeholders})
                           AND trim(coalesce(natureza_label,'')) <> ''
                         ORDER BY spj, slot",
                        $legSpjs
                    );
                    $natsLegMap = [];
                    foreach ($natsLeg as $n) {
                        $natsLegMap[$n['spj']][] = $n;
                    }

                    foreach ($legSpjs as $spj) {
                        // Pula se o SPJ jÃ¡ veio do banco PHP
                        if (in_array($spj, array_column($results, 'spj'), true)) {
                            continue;
                        }
                        $bo      = $bosLegMap[$spj] ?? null;
                        $pessoas = collect($legPessoas[$spj] ?? []);
                        $nats    = collect($natsLegMap[$spj] ?? []);

                        $results[] = $this->buildRow(
                            $spj, $bo ? (object) $bo : null, $pessoas, $nats, 'legado',
                            (bool) ($bo['flagrante'] ?? false),
                            (bool) ($bo['ato_infracional'] ?? false)
                        );
                    }
                }
            }

            // Ordena por SPJ decrescente
            usort($results, fn ($a, $b) => strcmp((string) ($b['spj'] ?? ''), (string) ($a['spj'] ?? '')));
            $results = array_slice($results, 0, 100);
        }

        $viewData = [
            'q'              => $q,
            'papel'          => $papel,
            'results'        => $results,
            'hasSearch'      => $hasSearch,
            'legadoWarning'  => $legadoWarning,
            'totalBos'       => count($results),
            'totalComMpu'    => count(array_filter($results, fn ($r) => ! empty($r['mpu_numero']))),
            'totalComIp'     => count(array_filter($results, fn ($r) => ! empty($r['num_ip']))),
            'totalFlagrante' => count(array_filter($results, fn ($r) => ! empty($r['flagrante']))),
            'geradoEm'       => now()->format('d/m/Y H:i'),
            'generatedAt'    => now(),
            'brasaoSrc'      => asset('assets/brasao.png'),
            'logoSrc'        => asset('assets/logo_grom.png'),
            'watermarkSrc'   => asset('assets/marca_dagua.png'),
        ];

        if ($imprimir && $hasSearch) {
            return view('analise.bos.relatorio-pessoa', $viewData);
        }

        return view('analise.bos.search', $viewData);
    }

    // â”€â”€â”€ ConstrÃ³i um array padronizado para um BO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function buildRow(
        string $spj,
        ?object $bo,
        Collection $pessoas,
        Collection $nats,
        string $fonte,
        bool $flagrante,
        bool $atoInfracional,
    ): array {
        // Pessoas encontradas com papel
        // Quando tipo contÃ©m AUTOR e VÃTIMA ao mesmo tempo (ex: "AUTOR/VITIMA"),
        // a linha Ã© duplicada: aparece uma vez como VÃ­tima e outra como Autor.
        $pessoasStr = $pessoas->flatMap(function ($p) {
            $p         = (object) $p;
            $tipoUpper = strtoupper(trim((string) ($p->tipo ?? '')));
            $isAmbo    = str_contains($tipoUpper, 'AUTOR') && (
                str_contains($tipoUpper, 'VITIMA') || str_contains($tipoUpper, 'VÃTIMA')
            );

            if ($isAmbo) {
                $tipoStr = ! empty($p->tipo) ? " ({$p->tipo})" : '';
                return [
                    "{$p->nome}{$tipoStr} [VÃ­tima]",
                    "{$p->nome} [Autor]",
                ];
            }

            $label = $p->papel === 'VÃ­tima' ? 'VÃ­tima' : 'Autor';
            $tipo  = ! empty($p->tipo) ? " ({$p->tipo})" : '';
            return ["{$p->nome}{$tipo} [{$label}]"];
        })->implode("\n");

        // Lista de papeis presentes (para tag)
        $papeis = $pessoas->flatMap(function ($p) {
            $p         = (object) $p;
            $tipoUpper = strtoupper(trim((string) ($p->tipo ?? '')));
            if (str_contains($tipoUpper, 'AUTOR') && (
                str_contains($tipoUpper, 'VITIMA') || str_contains($tipoUpper, 'VÃTIMA')
            )) {
                return ['VÃ­tima', 'Autor'];
            }
            return [$p->papel];
        })->unique()->values()->toArray();

        // Naturezas concatenadas — normaliza pelo padrão canônico (NaturezaNorm)
        $naturezasStr = $nats->map(function ($n) {
            $n     = (object) $n;
            $label = NaturezaNorm::label((string) ($n->natureza_label ?? ''));
            if ($label === '') {
                return null;
            }
            $tc = ! empty($n->tentado_consumado) ? " ({$n->tentado_consumado})" : '';
            return $label . $tc;
        })->filter()->unique()->implode('; ');

        return [
            'spj'               => $spj,
            'spj_fmt'           => $bo?->spj_fmt ?? $spj,
            'data_ocorrencia'   => $bo?->data_ocorrencia ?? '',
            'lavrado'           => $bo?->lavrado ?? '',
            'area_fato'         => $bo?->area_fato ?? '',
            'flagrante'         => $flagrante,
            'ato_infracional'   => $atoInfracional,
            'mpu_numero'        => $bo?->mpu_numero ?? '',
            'cnj_mpu'           => $bo?->cnj_mpu ?? '',
            'cartorio_designado'=> $bo?->cartorio_designado ?? '',
            'num_ip'            => $bo?->num_ip ?? '',
            'cartorio_ip'       => $bo?->cartorio_ip ?? '',
            'cnj_ip'            => $bo?->cnj_ip ?? '',
            'pessoas'           => $pessoasStr,
            'papeis'            => $papeis,
            'naturezas'         => $naturezasStr,
            'fonte'             => $fonte,
        ];
    }
}

