<?php

namespace App\Services\Operacional;

use App\Models\OperacionalMandado;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use SQLite3;

/**
 * Importa / sincroniza mandados do banco SQLite legado
 * (main/grom_database.sqlite3, tabela `mandados`) para a tabela
 * Eloquent `operacional_mandados`.
 *
 * Regra de merge: chave é `legacy_id` (PK do legado).
 * - Se não existir → insere.
 * - Se existir e houver diferença em qualquer campo relevante → atualiza.
 * - Caso contrário → conta como skipped.
 */
class LegacyMandadosSyncService
{
    /** @return array{inserted:int, updated:int, skipped:int, errors:int, messages:string[]} */
    public function sync(string $userId): array
    {
        $db = $this->openDb();

        $result = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'messages' => []];

        try {
            if (! $this->tableExists($db, 'mandados')) {
                $result['messages'][] = 'Tabela mandados nao encontrada no banco legado.';
                return $result;
            }

            $stmtAll = $db->prepare(
                'SELECT id, tipo_mandado, subtipo_prisao, tipo_sigla,
                        cnj_numero, numero_processo, vara, nome, cpf, rg,
                        data_emissao, validade,
                        tipificacao_penal, artigo, paragrafo, tipificacoes_extra,
                        pena_anos, pena_meses, pena_dias, regime,
                        procedimento, cumprido_por, data_cumprimento, numero_ocorrencia,
                        observacoes
                 FROM mandados
                 ORDER BY id ASC'
            );
            $rows = $stmtAll->execute();

            while ($row = $rows->fetchArray(SQLITE3_ASSOC)) {
                try {
                    $this->processRow($row, $userId, $result);
                } catch (\Throwable $e) {
                    $result['errors']++;
                    $result['messages'][] = 'Erro legado id=' . ($row['id'] ?? '?') . ': ' . $e->getMessage();
                }
            }
        } finally {
            $db->close();
        }

        return $result;
    }

    // -------------------------------------------------------
    // Internals
    // -------------------------------------------------------
    /** @param array<string,mixed> $result */
    private function processRow(array $row, string $userId, array &$result): void
    {
        $legacyId = (int) ($row['id'] ?? 0);
        if ($legacyId <= 0) {
            $result['skipped']++;
            return;
        }

        // Determina sigla canônica
        $tipoSigla = trim((string) ($row['tipo_sigla'] ?? ''));
        if ($tipoSigla === '' || ! array_key_exists($tipoSigla, OperacionalMandado::TIPOS_SIGLA)) {
            $tipoSigla = OperacionalMandado::siglaFromLegacy(
                (string) ($row['tipo_mandado'] ?? ''),
                isset($row['subtipo_prisao']) ? (string) $row['subtipo_prisao'] : null
            );
        }

        $mapping   = OperacionalMandado::SIGLA_PARA_TIPO;
        [$tipo, $subtipo] = $mapping[$tipoSigla] ?? ['Mandado de Prisão', null];

        $payload = [
            'tipo_sigla'        => $tipoSigla,
            'tipo_mandado'      => $tipo,
            'subtipo_prisao'    => $subtipo,
            'cnj_numero'        => $this->str($row['cnj_numero'] ?? null)
                                    ?: $this->str($row['numero_processo'] ?? null),
            'vara'              => $this->str($row['vara'] ?? null),
            'nome'              => $this->str($row['nome'] ?? null) ?? 'Desconhecido',
            'cpf'               => $this->digitsOnly($row['cpf'] ?? null),
            'rg'                => $this->str($row['rg'] ?? null),
            'data_emissao'      => $this->parseDate($row['data_emissao'] ?? null),
            'validade'          => $this->parseDate($row['validade'] ?? null),
            'tipificacao_penal' => $this->str($row['tipificacao_penal'] ?? null),
            'artigo'            => $this->str($row['artigo'] ?? null),
            'paragrafo'         => $this->str($row['paragrafo'] ?? null),
            'tipificacoes_extra'=> $this->parseJson($row['tipificacoes_extra'] ?? null),
            'pena_anos'         => $this->int($row['pena_anos'] ?? null),
            'pena_meses'        => $this->int($row['pena_meses'] ?? null),
            'pena_dias'         => $this->int($row['pena_dias'] ?? null),
            'regime'            => $this->str($row['regime'] ?? null),
            'procedimento'      => $this->resolveProc($row['procedimento'] ?? null),
            'cumprido_por'      => $this->str($row['cumprido_por'] ?? null),
            'data_cumprimento'  => $this->parseDate($row['data_cumprimento'] ?? null),
            'bo_numero'         => $this->str($row['numero_ocorrencia'] ?? null),  // campo legado
            'observacoes'       => $this->str($row['observacoes'] ?? null),
        ];

        $existing = OperacionalMandado::query()
            ->where('legacy_id', $legacyId)
            ->first();

        if ($existing === null) {
            OperacionalMandado::query()->create(array_merge($payload, [
                'legacy_id'   => $legacyId,
                'created_by'  => $userId,
                'updated_by'  => $userId,
            ]));
            $result['inserted']++;
        } else {
            $dirty = array_filter(
                $payload,
                fn ($v, $k) => $existing->getAttribute($k) !== $v,
                ARRAY_FILTER_USE_BOTH
            );

            if (count($dirty) > 0) {
                $existing->update(array_merge($dirty, ['updated_by' => $userId]));
                $result['updated']++;
            } else {
                $result['skipped']++;
            }
        }
    }

    private function openDb(): SQLite3
    {
        if (! config('grom_legacy.enabled')) {
            throw new RuntimeException('Sincronizacao legada desabilitada neste ambiente.');
        }

        $path = (string) config('grom_legacy.analise_db_path');

        if ($path === '' || ! File::exists($path)) {
            throw new InvalidArgumentException('Banco legado nao encontrado: ' . $path);
        }

        $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
        $db->busyTimeout(5000);

        return $db;
    }

    private function tableExists(SQLite3 $db, string $table): bool
    {
        $r = $db->querySingle(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='" . $db->escapeString($table) . "'"
        );
        return (int) $r > 0;
    }

    private function str(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }

    private function int(mixed $v): ?int
    {
        return ($v === null || $v === '') ? null : (int) $v;
    }

    private function digitsOnly(mixed $v): ?string
    {
        $d = preg_replace('/\D+/', '', (string) ($v ?? ''));
        return ($d === '' || $d === null) ? null : $d;
    }

    private function parseDate(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        // aceita ISO yyyy-mm-dd ou dd/mm/yyyy
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s;
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return null;
    }

    private function resolveProc(mixed $v): string
    {
        $s = trim((string) ($v ?? ''));
        $valid = OperacionalMandado::PROCEDIMENTOS;
        return in_array($s, $valid, true) ? $s : 'Em Aberto';
    }

    private function parseJson(mixed $v): ?array
    {
        if ($v === null || $v === '') {
            return null;
        }
        $decoded = json_decode((string) $v, true);
        return is_array($decoded) ? $decoded : null;
    }
}
