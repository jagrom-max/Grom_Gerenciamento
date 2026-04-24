<?php

namespace App\Services\Escalas;

use App\Models\EscalaDia;
use App\Models\EscalaPlantaoExterno;
use App\Models\EscalaPlantaoFuncionario;
use App\Models\RhFuncionario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use SQLite3;

/**
 * Importa / sincroniza os dados de escala do SQLite legado
 * para as tabelas PHP: escalas_dias, escalas_plantoes_externos,
 * escalas_plantoes_funcionarios.
 *
 * Estratégia de merge idempotente: chave = legacy_id.
 * Pode ser executado múltiplas vezes sem duplicar registros.
 */
class LegacyEscalasSyncService
{
    /** @return array{dias: array, plantoes_externos: array, plantoes_funcionarios: array, errors: string[]} */
    public function syncAll(string $userId): array
    {
        $db = $this->openDb();

        $result = [
            'dias'                 => ['inserted' => 0, 'updated' => 0, 'skipped' => 0],
            'plantoes_externos'    => ['inserted' => 0, 'updated' => 0, 'skipped' => 0],
            'plantoes_funcionarios'=> ['inserted' => 0, 'updated' => 0, 'skipped' => 0],
            'errors'               => [],
        ];

        try {
            if ($this->tableExists($db, 'plantoes_externos')) {
                $this->syncPlantaoCatalog($db, $userId, $result['plantoes_externos']);
            }

            if ($this->tableExists($db, 'escala_mensal')) {
                $this->syncEscalaDias($db, $userId, $result['dias']);
            }

            if ($this->tableExists($db, 'plantoes_funcionarios')) {
                $this->syncPlantaoFuncionarios($db, $userId, $result['plantoes_funcionarios'], $result['errors']);
            }
        } finally {
            $db->close();
        }

        return $result;
    }

    // -------------------------------------------------------
    // Sync — plantões externos (catálogo)
    // -------------------------------------------------------
    /** @param array<string,int> $stats */
    private function syncPlantaoCatalog(SQLite3 $db, string $userId, array &$stats): void
    {
        $result = $db->query('SELECT * FROM plantoes_externos ORDER BY id ASC');

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $legacyId = (int) $row['id'];

            $payload = [
                'nome'       => $this->str($row['nome'] ?? null) ?? '—',
                'sigla'      => $this->str($row['sigla'] ?? null),
                'unidade'    => $this->str($row['unidade'] ?? null),
                'regra'      => $this->str($row['regra'] ?? null),
                'observacao' => $this->str($row['observacao'] ?? null),
                'is_active'  => (bool) ($row['ativo'] ?? 1),
            ];

            $existing = EscalaPlantaoExterno::query()->where('legacy_id', $legacyId)->first();

            if ($existing === null) {
                EscalaPlantaoExterno::query()->create(array_merge($payload, ['legacy_id' => $legacyId]));
                $stats['inserted']++;
            } else {
                $dirty = array_filter($payload, fn ($v, $k) => $existing->getAttribute($k) !== $v, ARRAY_FILTER_USE_BOTH);
                if (count($dirty) > 0) {
                    $existing->update($dirty);
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
            }
        }
    }

    // -------------------------------------------------------
    // Sync — escala diária
    // -------------------------------------------------------
    /** @param array<string,int> $stats */
    private function syncEscalaDias(SQLite3 $db, string $userId, array &$stats): void
    {
        /*
         * O legado NÃO tem PK útil para `escala_mensal` no contexto de sync:
         * o campo `id` é autoincrement e pode mudar. A chave de negócio real é
         * (data, versao) — usamos isso para gerar um legacy_id sintético:
         *   legacy_id = id do registro original (é único na prática pois nunca
         *   há dois registros com mesmo id, apesar de poderem ter mesma data/versao em
         *   diferentes instâncias). Mas como o SQLite original usa autoincrement, id é
         *   estável. Usamos id diretamente.
         */
        $stmt = $db->query(
            'SELECT id, data, escrivao, operacional, fechar, delegada, plantao_externo,
                    mes, ano, versao
             FROM escala_mensal
             ORDER BY id ASC'
        );

        while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) {
            $legacyId = (int) $row['id'];
            $dataStr  = $this->parseDate($row['data'] ?? null);

            if ($dataStr === null) {
                $stats['skipped']++;
                continue;
            }

            $payload = [
                'data'           => $dataStr,
                'mes'            => (int) $row['mes'],
                'ano'            => (int) $row['ano'],
                'versao'         => (int) $row['versao'],
                'is_fechada'     => false,
                'escrivao'       => $this->str($row['escrivao'] ?? null),
                'operacional'    => $this->str($row['operacional'] ?? null),
                'fechar_nome'    => $this->str($row['fechar'] ?? null),
                'delegada'       => $this->str($row['delegada'] ?? null),
                'plantao_externo'=> $this->str($row['plantao_externo'] ?? null),
                'created_by'     => $userId,
                'updated_by'     => $userId,
            ];

            $existing = EscalaDia::query()->where('legacy_id', $legacyId)->first();

            if ($existing === null) {
                EscalaDia::query()->create(array_merge($payload, ['legacy_id' => $legacyId]));
                $stats['inserted']++;
            } else {
                $dirty = array_filter(
                    $payload,
                    fn ($v, $k) => !in_array($k, ['created_by'], true) && $existing->getAttribute($k) !== $v,
                    ARRAY_FILTER_USE_BOTH
                );
                if (count($dirty) > 0) {
                    $existing->update(array_merge($dirty, ['updated_by' => $userId]));
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
            }
        }
    }

    // -------------------------------------------------------
    // Sync — plantões por funcionário
    // -------------------------------------------------------
    /** @param array<string,int> $stats */
    private function syncPlantaoFuncionarios(SQLite3 $db, string $userId, array &$stats, array &$errors): void
    {
        // Mapa legacy_id -> PHP UUID para funcionarios
        $funcMap = RhFuncionario::query()
            ->whereNotNull('legacy_id')
            ->pluck('id', 'legacy_id')
            ->all();

        // Mapa legacy_id -> PHP id para plantoes_externos
        $plantaoMap = EscalaPlantaoExterno::query()
            ->whereNotNull('legacy_id')
            ->pluck('id', 'legacy_id')
            ->all();

        $stmt = $db->query('SELECT * FROM plantoes_funcionarios ORDER BY id ASC');

        while ($row = $stmt->fetchArray(SQLITE3_ASSOC)) {
            $legacyId      = (int) $row['id'];
            $legacyFuncId  = (int) $row['funcionario_id'];
            $legacyPlantId = (int) $row['plantao_id'];
            $dataStr       = $this->parseDate($row['data'] ?? null);

            if ($dataStr === null) {
                $stats['skipped']++;
                continue;
            }

            $funcUuid   = $funcMap[$legacyFuncId] ?? null;
            $plantaoPhpId = $plantaoMap[$legacyPlantId] ?? null;

            if ($funcUuid === null || $plantaoPhpId === null) {
                $errors[] = "plantao_func id={$legacyId}: funcionario_id={$legacyFuncId} ou plantao_id={$legacyPlantId} nao encontrado no PHP.";
                $stats['skipped']++;
                continue;
            }

            $existing = EscalaPlantaoFuncionario::query()->where('legacy_id', $legacyId)->first();

            if ($existing === null) {
                EscalaPlantaoFuncionario::query()->create([
                    'data'              => $dataStr,
                    'funcionario_id'    => $funcUuid,
                    'plantao_externo_id'=> $plantaoPhpId,
                    'created_by'        => $userId,
                    'legacy_id'         => $legacyId,
                ]);
                $stats['inserted']++;
            } else {
                $stats['skipped']++;
            }
        }
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------
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
        return (int) $db->querySingle(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='" . SQLite3::escapeString($table) . "'"
        ) > 0;
    }

    private function str(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }

    private function parseDate(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s;
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return null;
    }
}
