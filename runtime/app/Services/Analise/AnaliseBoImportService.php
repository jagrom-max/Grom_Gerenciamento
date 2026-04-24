<?php

namespace App\Services\Analise;

use App\Models\AnaliseFlagramtePendencia;
use App\Support\SpreadsheetImportReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Importa planilha Excel de análise de BOs para as tabelas PHP.
 *
 * Formato esperado — mesmo da planilha do Python:
 *   Colunas obrigatórias: "Nº RDO", "Data da Ocorrência"
 *   Colunas opcionais:    Lavrado, Flagrante, Ato Infracional, Área do Fato,
 *                         MPU, Nº CNJ MPU, BO designado (Cartório),
 *                         Nº IP, Cartório do IP (final)
 *   Slots de natureza:    "Natureza 1".."Natureza 6", "Consumo/Tentativa 1".."6"
 *   Slots de vítima:      "Vítima 1 (Nome)".."Vítima 6 (Nome)" + "(Tipo)"
 *   Slots de autor:       "Autor 1".."Autor 3"
 */
final class AnaliseBoImportService
{
    // Mapeamento de cabeçalho → chave interna (normalizado sem acento/espaço)
    private const COL_MAP = [
        // obrigatório
        'NºRDO'                   => 'spj',
        'NRDO'                    => 'spj',
        'NUMERORDO'               => 'spj',
        'SPJ'                     => 'spj',
        'DatadaOcorrência'        => 'data_ocorrencia',
        'DatadaOcorrencia'        => 'data_ocorrencia',
        'DataOcorrencia'          => 'data_ocorrencia',
        'DATAOCORRENCIA'          => 'data_ocorrencia',
        // demais
        'Lavrado'                 => 'lavrado',
        'LAVRADO'                 => 'lavrado',
        'Flagrante'               => 'flagrante',
        'FLAGRANTE'               => 'flagrante',
        'AtoInfracional'          => 'ato_infracional',
        'ATOINFRACIONAL'          => 'ato_infracional',
        'ÁreadoFato'              => 'area_fato',
        'AreadoFato'              => 'area_fato',
        'AreaFato'                => 'area_fato',
        'AREAFATO'                => 'area_fato',
        'MPU'                     => 'mpu_numero',
        'NºCNJMPU'                => 'cnj_mpu',
        'NCNJMPU'                 => 'cnj_mpu',
        'CNFMPU'                  => 'cnj_mpu',
        'BOdesignado(Cartório)'   => 'cartorio_designado',
        'BOdesignado(Cartorio)'   => 'cartorio_designado',
        'CartorioIP'              => 'cartorio_ip',
        'NºIP'                    => 'num_ip',
        'NIP'                     => 'num_ip',
        'NumerodoIP'              => 'num_ip',
        'CartóriodoIP(final)'     => 'cartorio_ip',
        'CartoriodoIP(final)'     => 'cartorio_ip',
        'CartoriodoIPfinal'       => 'cartorio_ip',
    ];

    public function __construct(
        private readonly SpreadsheetImportReader $reader,
    ) {}

    /**
     * Importa um arquivo XLSX/CSV, grava no banco PHP e retorna sumário.
     *
     * @return array{inserted:int,updated:int,skipped:int,errors:int,source:string,
     *               flagrantes_total:int,flagrantes_sem_cartorio:int,pendencias_criadas:int}
     */
    public function importUploadedFile(UploadedFile $file): array
    {
        $parsed = $this->reader->read($file);
        $rows   = $parsed['rows'] ?? [];
        $source = $file->getClientOriginalName();
        $hash   = hash_file('sha256', (string) $file->getRealPath());

        if (empty($rows)) {
            throw new RuntimeException('A planilha está vazia ou sem dados legíveis.');
        }

        return $this->processRows($rows, $source, $hash);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internos
    // ─────────────────────────────────────────────────────────────────────────

    /** @param  array<int,array<string,mixed>>  $rows */
    private function processRows(array $rows, string $source, ?string $hash = null): array
    {
        $inserted           = 0;
        $updated            = 0;
        $skipped            = 0;
        $errors             = 0;
        $flagrantesTotal    = 0;
        $flagrantesSemCart  = 0;
        $pendenciasCriadas  = 0;
        $pendenciasBuffer   = [];

        DB::transaction(function () use ($rows, $source, $hash, &$inserted, &$updated, &$skipped, &$errors, &$flagrantesTotal, &$flagrantesSemCart, &$pendenciasBuffer): void {
            foreach ($rows as $raw) {
                try {
                    $norm = $this->normalizeRow($raw);
                } catch (InvalidArgumentException) {
                    $errors++;
                    continue;
                }

                if (! $norm['spj']) {
                    $skipped++;
                    continue;
                }

                $exists = DB::table('analise_bos')->where('spj', $norm['spj'])->exists();

                if ($exists) {
                    DB::table('analise_bos')
                        ->where('spj', $norm['spj'])
                        ->update(array_merge($this->boFields($norm), [
                            'import_source' => $source,
                            'import_hash'   => $hash,
                            'updated_at'    => now(),
                        ]));
                    $updated++;
                } else {
                    DB::table('analise_bos')->insert(array_merge($this->boFields($norm), [
                        'import_source' => $source,
                        'import_hash'   => $hash,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]));
                    $inserted++;
                }

                // ── Detectar flagrante sem cartório → fila de auditoria ──────
                if ($norm['flagrante']) {
                    $flagrantesTotal++;
                    if (empty(trim($norm['cartorio_ip']))) {
                        $flagrantesSemCart++;
                        // Acumula para inserção em lote após a transação
                        $pendenciasBuffer[] = [
                            'spj'                  => $norm['spj'],
                            'spj_prefix'           => $norm['spj_prefix'] ?: null,
                            'spj_year'             => $norm['spj_year'] ?: null,
                            'data_ocorrencia'      => $norm['data_ocorrencia'] ?: null,
                            'lavrado'              => $norm['lavrado'] ?: null,
                            'area_fato'            => $norm['area_fato'] ?: null,
                            'naturezas'            => ! empty($norm['naturezas'])
                                ? implode(', ', array_column($norm['naturezas'], 'natureza'))
                                : null,
                            'num_ip'               => $norm['num_ip'] ?: null,
                            'mpu_numero'           => $norm['mpu_numero'] ?: null,
                            'cartorio_ip_planilha' => null,
                            'import_source'        => $source,
                        ];
                    }
                }

                $spj = $norm['spj'];

                // Naturezas
                foreach ($norm['naturezas'] as $nat) {
                    if (! $nat['natureza']) {
                        continue;
                    }
                    DB::table('analise_bo_naturezas')->updateOrInsert(
                        ['spj' => $spj, 'slot' => $nat['slot']],
                        [
                            'natureza'          => $nat['natureza'],
                            'natureza_label'    => NaturezaNorm::label($nat['natureza']),
                            'tentado_consumado' => $nat['tentado_consumado'],
                            'updated_at'        => now(),
                            'created_at'        => now(),
                        ]
                    );
                }

                // Vítimas
                foreach ($norm['vitimas'] as $vit) {
                    if (! $vit['nome']) {
                        continue;
                    }
                    DB::table('analise_bo_vitimas')->updateOrInsert(
                        ['spj' => $spj, 'slot' => $vit['slot']],
                        [
                            'nome'       => $vit['nome'],
                            'nome_key'   => self::normKey($vit['nome']),
                            'tipo'       => $vit['tipo'],
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }

                // Autores
                foreach ($norm['autores'] as $aut) {
                    if (! $aut['nome']) {
                        continue;
                    }
                    DB::table('analise_bo_autores')->updateOrInsert(
                        ['spj' => $spj, 'slot' => $aut['slot']],
                        [
                            'nome'       => $aut['nome'],
                            'nome_key'   => self::normKey($aut['nome']),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        });

        // Criar pendências de auditoria para flagrantes sem cartório
        // (fora da transação principal para não misturar falhas)
        foreach ($pendenciasBuffer as $pendencia) {
            // Evita duplicar: se já existe pendência pending para o mesmo SPJ, ignora
            $jaExiste = AnaliseFlagramtePendencia::where('spj', $pendencia['spj'])
                ->where('status', 'pending')
                ->exists();

            if (! $jaExiste) {
                AnaliseFlagramtePendencia::create(array_merge($pendencia, ['status' => 'pending']));
                $pendenciasCriadas++;
            }
        }

        return compact(
            'inserted', 'updated', 'skipped', 'errors', 'source',
            'flagrantesTotal', 'flagrantesSemCart', 'pendenciasCriadas'
        );
    }

    /**
     * Normaliza uma linha bruta da planilha para um array estruturado.
     *
     * @param  array<string,mixed>  $raw
     * @return array{spj:string,data_ocorrencia:string,lavrado:string,area_fato:string,
     *               flagrante:bool,ato_infracional:bool,mpu_numero:string,cnj_mpu:string,
     *               cartorio_designado:string,num_ip:string,cartorio_ip:string,
     *               naturezas:array,vitimas:array,autores:array}
     */
    private function normalizeRow(array $raw): array
    {
        $mapped = $this->mapColumns($raw);

        $spjParsed = self::parseSpj((string) ($mapped['spj'] ?? ''));
        if (! $spjParsed['spj_fmt']) {
            return array_merge($spjParsed, [
                'spj' => '', 'data_ocorrencia' => '', 'lavrado' => '', 'area_fato' => '',
                'flagrante' => false, 'ato_infracional' => false,
                'mpu_numero' => '', 'cnj_mpu' => '', 'cartorio_designado' => '',
                'num_ip' => '', 'cartorio_ip' => '',
                'naturezas' => [], 'vitimas' => [], 'autores' => [],
            ]);
        }

        $dataRaw = $mapped['data_ocorrencia'] ?? '';
        $dataStr = self::parseDate($dataRaw);

        // Naturezas — slots 1..6
        $naturezas = [];
        for ($i = 1; $i <= 6; $i++) {
            $nat = self::str($mapped["natureza_$i"] ?? null);
            $tc  = self::str($mapped["tc_$i"] ?? null);
            if ($nat && ! in_array($nat, ['-', 'N/A', ''], true)) {
                $naturezas[] = ['slot' => $i, 'natureza' => $nat, 'tentado_consumado' => $tc ?: null];
            }
        }

        // Vítimas — slots 1..6
        $vitimas = [];
        for ($i = 1; $i <= 6; $i++) {
            $nome = self::str($mapped["vitima_nome_$i"] ?? null);
            $tipo = self::str($mapped["vitima_tipo_$i"] ?? null);
            if ($nome) {
                $vitimas[] = ['slot' => $i, 'nome' => $nome, 'tipo' => $tipo ?: null];
            }
        }

        // Autores — slots 1..3
        $autores = [];
        for ($i = 1; $i <= 3; $i++) {
            $nome = self::str($mapped["autor_$i"] ?? null);
            if ($nome) {
                $autores[] = ['slot' => $i, 'nome' => $nome];
            }
        }

        return [
            'spj'               => $spjParsed['spj_fmt'],
            'spj_prefix'        => $spjParsed['spj_prefix'],
            'spj_seq'           => $spjParsed['spj_seq'],
            'spj_year'          => $spjParsed['spj_year'],
            'spj_fmt'           => $spjParsed['spj_fmt'],
            'data_ocorrencia'   => $dataStr,
            'lavrado'           => self::str($mapped['lavrado'] ?? null),
            'area_fato'         => self::str($mapped['area_fato'] ?? null),
            'flagrante'         => self::parseBool($mapped['flagrante'] ?? null),
            'ato_infracional'   => self::parseBool($mapped['ato_infracional'] ?? null),
            'mpu_numero'        => self::str($mapped['mpu_numero'] ?? null),
            'cnj_mpu'           => self::str($mapped['cnj_mpu'] ?? null),
            'cartorio_designado'=> self::str($mapped['cartorio_designado'] ?? null),
            'num_ip'            => self::str($mapped['num_ip'] ?? null),
            'cartorio_ip'       => self::str($mapped['cartorio_ip'] ?? null),
            'naturezas'         => $naturezas,
            'vitimas'           => $vitimas,
            'autores'           => $autores,
        ];
    }

    /**
     * Mapeia as chaves brutas da planilha para as chaves internas do serviço.
     * Suporta cabeçalhos do Python + variações com/sem acento.
     *
     * @param  array<string,mixed>  $raw
     * @return array<string,mixed>
     */
    private function mapColumns(array $raw): array
    {
        $mapped = [];

        foreach ($raw as $header => $value) {
            // Normaliza o cabeçalho: remove espaços, normaliza acento para chave
            $key = $this->normalizeHeaderKey($header);

            // Tenta mapa direto
            $field = self::COL_MAP[$key] ?? null;
            if ($field) {
                $mapped[$field] = $value;
                continue;
            }

            // Slots dinâmicos: "Natureza 1" .. "Natureza 6"
            if (preg_match('/^natureza(\d)$/i', $key, $m)) {
                $mapped["natureza_{$m[1]}"] = $value;
                continue;
            }
            if (preg_match('/^consumo[\/\-]?tentativa(\d)$/i', $key, $m) ||
                preg_match('/^tc(\d)$/i', $key, $m)) {
                $mapped["tc_{$m[1]}"] = $value;
                continue;
            }
            // "Vítima 1 (Nome)" → vitima_nome_1
            if (preg_match('/^v[ií]tima(\d)nome$/i', $key, $m) ||
                preg_match('/^v[ií]tima(\d)\(?nome\)?$/i', $key, $m)) {
                $mapped["vitima_nome_{$m[1]}"] = $value;
                continue;
            }
            // "Vítima 1 (Tipo)" → vitima_tipo_1
            if (preg_match('/^v[ií]tima(\d)tipo$/i', $key, $m) ||
                preg_match('/^v[ií]tima(\d)\(?tipo\)?$/i', $key, $m)) {
                $mapped["vitima_tipo_{$m[1]}"] = $value;
                continue;
            }
            // "Autor 1" → autor_1
            if (preg_match('/^autor(\d)$/i', $key, $m)) {
                $mapped["autor_{$m[1]}"] = $value;
                continue;
            }
        }

        return $mapped;
    }

    private function normalizeHeaderKey(string $header): string
    {
        // Remove espaços, parênteses e converte para comparável
        $s = preg_replace('/[\s\(\)\[\]]+/', '', $header) ?? $header;
        // Remove acentos com tabela de substituição direta (sem mb_string extra)
        $s = str_replace(
            ['á','à','ã','â','é','ê','í','ó','ô','õ','ú','ç','Á','À','Ã','Â','É','Ê','Í','Ó','Ô','Õ','Ú','Ç','ª','º','°','ñ','Ñ'],
            ['a','a','a','a','e','e','i','o','o','o','u','c','A','A','A','A','E','E','I','O','O','O','U','C','a','o','o','n','N'],
            $s
        );
        // Remove "Nº" → "N" ou "Numero"
        $s = str_replace('Nº', 'N', $s);
        $s = str_replace('°', 'o', $s);
        $s = preg_replace('/[^A-Za-z0-9\/\-]/', '', $s) ?? $s;
        return $s;
    }

    /** @return array<string,mixed> */
    private function boFields(array $norm): array
    {
        return [
            'spj'                => $norm['spj'],
            'spj_prefix'         => $norm['spj_prefix'] ?: null,
            'spj_seq'            => $norm['spj_seq'] ?: null,
            'spj_year'           => $norm['spj_year'] ?: null,
            'spj_fmt'            => $norm['spj_fmt'] ?: $norm['spj'],
            'data_ocorrencia'    => $norm['data_ocorrencia'] ?: null,
            'lavrado'            => $norm['lavrado'] ?: null,
            'area_fato'          => $norm['area_fato'] ?: null,
            'flagrante'          => $norm['flagrante'] ? 1 : 0,
            'ato_infracional'    => $norm['ato_infracional'] ? 1 : 0,
            'mpu_numero'         => $norm['mpu_numero'] ?: null,
            'cnj_mpu'            => $norm['cnj_mpu'] ?: null,
            'cartorio_designado' => $norm['cartorio_designado'] ?: null,
            'num_ip'             => $norm['num_ip'] ?: null,
            'cartorio_ip'        => $norm['cartorio_ip'] ?: null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilitários estáticos (portados de bo_import_excel_ad.py)
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{spj_fmt:string,spj_prefix:string|null,spj_seq:int|null,spj_year:int|null} */
    public static function parseSpj(string $raw): array
    {
        $s = strtoupper(trim(preg_replace('/\s+/', '', $raw) ?? ''));

        if (! $s) {
            return ['spj_fmt' => '', 'spj_prefix' => null, 'spj_seq' => null, 'spj_year' => null];
        }

        // Tenta "TB4615/2025" ou "4615/2025" ou "TB4615"
        if (preg_match('/^([A-Z]{2})?(\d+)(?:\/(\d{4}))?$/', $s, $m)) {
            $prefix = $m[1] ?: null;
            $seq    = (int) $m[2];
            $ano    = isset($m[3]) && $m[3] ? (int) $m[3] : (int) date('Y');
            $base   = ($prefix ?? '') . $m[2];
            $fmt    = "{$base}/{$ano}";
            return ['spj_fmt' => $fmt, 'spj_prefix' => $prefix, 'spj_seq' => $seq, 'spj_year' => $ano];
        }

        return ['spj_fmt' => $s, 'spj_prefix' => null, 'spj_seq' => null, 'spj_year' => null];
    }

    public static function parseDate(mixed $v): string
    {
        if ($v === null || trim((string) $v) === '') {
            return '';
        }

        // Objeto DateTime do PhpSpreadsheet
        if ($v instanceof \DateTimeInterface) {
            return $v->format('d/m/Y');
        }

        $s = trim((string) $v);

        // Formatos: d/m/Y, Y-m-d, d-m-Y
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y'] as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat($fmt, $s);
            if ($dt !== false) {
                return $dt->format('d/m/Y');
            }
        }

        // Número serial do Excel (dias desde 1900-01-01)
        if (is_numeric($s) && (int) $s > 1000) {
            try {
                $base = new \DateTimeImmutable('1900-01-01');
                $dt   = $base->modify('+' . ((int) $s - 2) . ' days');
                return $dt->format('d/m/Y');
            } catch (\Exception) {}
        }

        return $s;
    }

    public static function parseBool(mixed $v): bool
    {
        $s = strtoupper(trim((string) ($v ?? '')));
        return in_array($s, ['SIM', 'S', 'TRUE', '1', 'X', 'YES'], true);
    }

    public static function str(mixed $v): string
    {
        return trim((string) ($v ?? ''));
    }

    public static function normKey(string $nome): string
    {
        $s = strtolower(trim($nome));
        $s = str_replace(
            ['á','à','ã','â','é','ê','í','ó','ô','õ','ú','ç'],
            ['a','a','a','a','e','e','i','o','o','o','u','c'],
            $s
        );
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return $s;
    }
}
