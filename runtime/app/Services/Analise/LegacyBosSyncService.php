<?php

namespace App\Services\Analise;

use App\Services\Legacy\LegacyDatabaseService;
use Illuminate\Support\Facades\DB;
use SQLite3;

/**
 * Copia os BOs do banco legado (analise_ocorrencias + filhas)
 * para as tabelas PHP (analise_bos + analise_bo_*).
 *
 * Estratégia de merge idempotente: chave = SPJ (UNIQUE em analise_bos).
 * Pode ser executado múltiplas vezes — nunca duplica registros.
 */
final class LegacyBosSyncService
{
    public function __construct(
        private readonly LegacyDatabaseService $legacy,
    ) {}

    /**
     * Executa a sincronização completa.
     *
     * @return array{inserted:int,updated:int,skipped:int,errors:int,messages:string[]}
     */
    public function syncAll(): array
    {
        if (! $this->legacy->isAvailable()) {
            return [
                'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0,
                'messages' => ['Banco legado indisponível — nada a sincronizar.'],
            ];
        }

        // ── 1. Carregar tudo do legado em três queries ─────────────────────
        $ocorrencias = $this->loadOcorrencias();
        $naturezas   = $this->loadNaturezas();     // indexado por SPJ
        $vitimas     = $this->loadVitimas();        // indexado por SPJ
        $autores     = $this->loadAutores();        // indexado por SPJ

        if (empty($ocorrencias)) {
            return [
                'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0,
                'messages' => ['Tabela analise_ocorrencias vazia no legado.'],
            ];
        }

        // ── 2. Processar em transação única ────────────────────────────────
        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = 0;
        $messages = [];

        DB::transaction(function () use (
            $ocorrencias, $naturezas, $vitimas, $autores,
            &$inserted, &$updated, &$skipped, &$errors, &$messages
        ): void {
            foreach ($ocorrencias as $row) {
                try {
                    $spj = (string) ($row['spj'] ?? '');
                    if ($spj === '') {
                        $skipped++;
                        continue;
                    }

                    $payload = [
                        'spj_prefix'        => $this->str($row['spj_prefix'] ?? null),
                        'spj_seq'           => isset($row['spj_seq']) ? (int) $row['spj_seq'] : null,
                        'spj_year'          => isset($row['spj_year']) ? (int) $row['spj_year'] : null,
                        'spj_fmt'           => $this->str($row['spj_fmt'] ?? null),
                        'data_ocorrencia'   => $this->str($row['data_ocorrencia'] ?? null),
                        'lavrado'           => $this->str($row['lavrado'] ?? null),
                        'area_fato'         => $this->str($row['area_fato'] ?? null),
                        'flagrante'         => (bool) ($row['flagrante'] ?? false),
                        'ato_infracional'   => (bool) ($row['ato_infracional'] ?? false),
                        'mpu_numero'        => $this->str($row['mpu_numero'] ?? null),
                        'cnj_mpu'           => $this->str($row['cnj_mpu'] ?? null),
                        'cartorio_designado'=> $this->str($row['cartorio_designado'] ?? null),
                        'num_ip'            => $this->str($row['num_ip'] ?? null),
                        'cartorio_ip'       => $this->str($row['cartorio_ip'] ?? null),
                        'cnj_ip'            => $this->str($row['cnj_ip_importado'] ?? null),
                        'import_source'     => 'legado',
                        'import_hash'       => null,
                        'updated_at'        => now(),
                    ];

                    $exists = DB::table('analise_bos')->where('spj', $spj)->exists();

                    if ($exists) {
                        DB::table('analise_bos')->where('spj', $spj)->update($payload);
                        $updated++;
                    } else {
                        DB::table('analise_bos')->insert(array_merge(
                            ['spj' => $spj, 'created_at' => now()],
                            $payload
                        ));
                        $inserted++;
                    }

                    // Naturezas
                    foreach ($naturezas[$spj] ?? [] as $nat) {
                        $natureza = $this->str($nat['natureza'] ?? null);
                        if (! $natureza) {
                            continue;
                        }

                        $label = $this->str($nat['natureza_label'] ?? null)
                            ?: NaturezaNorm::label($natureza);

                        DB::table('analise_bo_naturezas')->updateOrInsert(
                            ['spj' => $spj, 'slot' => (int) $nat['slot']],
                            [
                                'natureza'          => $natureza,
                                'natureza_label'    => $label,
                                'tentado_consumado' => $this->str($nat['tentado_consumado'] ?? null),
                                'updated_at'        => now(),
                                'created_at'        => now(),
                            ]
                        );
                    }

                    // Vítimas
                    foreach ($vitimas[$spj] ?? [] as $vit) {
                        $nome = $this->str($vit['nome_upper'] ?? null);
                        if (! $nome) {
                            continue;
                        }

                        DB::table('analise_bo_vitimas')->updateOrInsert(
                            ['spj' => $spj, 'slot' => (int) $vit['slot']],
                            [
                                'nome'       => $nome,
                                'nome_key'   => $this->str($vit['nome_key'] ?? null) ?? mb_strtolower($nome),
                                'tipo'       => $this->str($vit['tipo'] ?? null),
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                    }

                    // Autores
                    foreach ($autores[$spj] ?? [] as $aut) {
                        $nome = $this->str($aut['nome_upper'] ?? null);
                        if (! $nome) {
                            continue;
                        }

                        DB::table('analise_bo_autores')->updateOrInsert(
                            ['spj' => $spj, 'slot' => (int) $aut['slot']],
                            [
                                'nome'       => $nome,
                                'nome_key'   => $this->str($aut['nome_key'] ?? null) ?? mb_strtolower($nome),
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $messages[] = 'Erro SPJ=' . ($row['spj'] ?? '?') . ': ' . $e->getMessage();
                }
            }
        });

        $messages[] = sprintf(
            'BOs: %d inseridos, %d atualizados, %d ignorados, %d erros.',
            $inserted, $updated, $skipped, $errors
        );

        return compact('inserted', 'updated', 'skipped', 'errors', 'messages');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Leitura do legado
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<int, array<string,mixed>> */
    private function loadOcorrencias(): array
    {
        if (! $this->legacy->tableExists('analise_ocorrencias')) {
            return [];
        }

        return $this->legacy->fetchAll(
            'SELECT spj, spj_prefix, spj_seq, spj_year, spj_fmt,
                    data_ocorrencia, lavrado, area_fato,
                    flagrante, ato_infracional,
                    mpu_numero, cnj_mpu, cartorio_designado,
                    num_ip, cartorio_ip, cnj_ip_importado
             FROM analise_ocorrencias
             ORDER BY spj_year ASC, spj_seq ASC'
        );
    }

    /**
     * Carrega todas as naturezas e indexa por SPJ.
     *
     * @return array<string, array<int, array<string,mixed>>>
     */
    private function loadNaturezas(): array
    {
        if (! $this->legacy->tableExists('analise_naturezas')) {
            return [];
        }

        $rows = $this->legacy->fetchAll(
            'SELECT spj, slot, natureza, tentado_consumado, natureza_label
             FROM analise_naturezas
             ORDER BY spj, slot'
        );

        return $this->groupBySpj($rows);
    }

    /**
     * Carrega todas as vítimas e indexa por SPJ.
     *
     * @return array<string, array<int, array<string,mixed>>>
     */
    private function loadVitimas(): array
    {
        if (! $this->legacy->tableExists('analise_vitimas')) {
            return [];
        }

        $rows = $this->legacy->fetchAll(
            'SELECT spj, slot, nome_upper, nome_key, tipo
             FROM analise_vitimas
             ORDER BY spj, slot'
        );

        return $this->groupBySpj($rows);
    }

    /**
     * Carrega todos os autores e indexa por SPJ.
     *
     * @return array<string, array<int, array<string,mixed>>>
     */
    private function loadAutores(): array
    {
        if (! $this->legacy->tableExists('analise_autores')) {
            return [];
        }

        $rows = $this->legacy->fetchAll(
            'SELECT spj, slot, nome_upper, nome_key
             FROM analise_autores
             ORDER BY spj, slot'
        );

        return $this->groupBySpj($rows);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Agrupa linhas por campo 'spj'.
     *
     * @param  array<int, array<string,mixed>>     $rows
     * @return array<string, array<int, array<string,mixed>>>
     */
    private function groupBySpj(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $spj = (string) ($row['spj'] ?? '');
            if ($spj !== '') {
                $indexed[$spj][] = $row;
            }
        }

        return $indexed;
    }

    private function str(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
