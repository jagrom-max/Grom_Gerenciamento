<?php

namespace App\Services\Produtividade;

use App\Models\User;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use SQLite3;

class LegacyAnaliseSyncService
{
    public function __construct(
        private readonly FlagranteImportService $importService,
    ) {
    }

    public function syncFlagrantes(User $actor): array
    {
        if (! config('grom_legacy.enabled')) {
            throw new RuntimeException('A sincronizacao com a base legada esta desabilitada neste ambiente.');
        }

        $dbPath = (string) config('grom_legacy.analise_db_path');
        if ($dbPath === '') {
            throw new InvalidArgumentException('Caminho da base legada nao configurado.');
        }

        if (! File::exists($dbPath)) {
            throw new InvalidArgumentException('Base legada da Analise de Dados nao encontrada no caminho configurado.');
        }

        $legacy = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $legacy->enableExceptions(true);

        try {
            if (! $this->tableExists($legacy, 'analise_ocorrencias')) {
                throw new RuntimeException('A base legada nao possui a tabela analise_ocorrencias.');
            }

            $naturezaMap = $this->loadNaturezaMap($legacy);
            $rows = $this->loadFlagranteRows($legacy, $naturezaMap);
        } finally {
            $legacy->close();
        }

        return $this->importService->importStructuredRows($rows, $actor, [
            'source_name' => basename($dbPath),
            'source_type' => 'LEGACY_SQLITE',
            'source_hash' => hash_file('sha256', $dbPath) ?: null,
            'sheet_name' => null,
            'header_row' => null,
            'notes_prefix' => 'Sincronizacao direta da base legada Analise de Dados em modo somente leitura.',
            'allowed_cartorio_ids' => $actor->isSuperAdmin() ? [] : $actor->scopeKeys('cartorio')->all(),
            'allowed_lavrado_unidades' => $actor->isSuperAdmin() ? [] : $actor->scopeKeys('lavrado_unidade')->all(),
        ]);
    }

    private function loadFlagranteRows(SQLite3 $legacy, array $naturezaMap): array
    {
        $cols = $this->tableColumns($legacy, 'analise_ocorrencias');
        $spjExpr = in_array('spj_fmt', $cols, true)
            ? "COALESCE(NULLIF(o.spj_fmt,''), o.spj)"
            : 'o.spj';
        $cartExpr = "COALESCE(NULLIF(o.cartorio_designado,''), NULLIF(o.cartorio_ip,''), '')";
        $cnjExpr = "COALESCE(NULLIF(o.cnj_mpu,''), NULLIF(o.cnj_ip_importado,''), '')";
        $ipEExpr = in_array('num_ip_e', $cols, true) ? "COALESCE(o.num_ip_e,'')" : "''";
        $lavradoExpr = "COALESCE(NULLIF(o.lavrado,''), '')";
        $fromExpr = 'FROM analise_ocorrencias o';

        if ($this->tableExists($legacy, 'analise_ocorrencias_extra')) {
            $fromExpr .= ' LEFT JOIN analise_ocorrencias_extra e ON e.spj = o.spj';
            $lavradoExpr = "COALESCE(NULLIF(o.lavrado,''), NULLIF(e.lavrado_unidade,''), '')";
        }

        $sql = <<<SQL
            SELECT
                o.spj AS spj_raw,
                {$spjExpr} AS spj_ref,
                COALESCE(o.data_ocorrencia, '') AS data_fato,
                {$lavradoExpr} AS lavrado_unidade,
                COALESCE(o.num_ip, '') AS ip,
                {$ipEExpr} AS ip_e,
                {$cnjExpr} AS cnj,
                {$cartExpr} AS cartorio_label,
                COALESCE(o.flagrante, 0) AS flagrante
            {$fromExpr}
            WHERE COALESCE(o.flagrante, 0) = 1
              AND COALESCE(TRIM({$spjExpr}), '') <> ''
            ORDER BY o.data_ocorrencia DESC, o.spj
        SQL;

        $result = $legacy->query($sql);
        $rows = [];

        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $spjRaw = trim((string) ($row['spj_raw'] ?? ''));
            $spjRef = trim((string) ($row['spj_ref'] ?? ''));

            if ($spjRef === '') {
                continue;
            }

            $rows[] = [
                'source_process_key' => $spjRef,
                'spj' => $spjRef,
                'naturezas' => $naturezaMap[$spjRaw] ?? '',
                'data_fato' => (string) ($row['data_fato'] ?? ''),
                'status' => 'Flagrante',
                'flagrante' => (string) ($row['flagrante'] ?? '1'),
                'num_ip' => (string) ($row['ip'] ?? ''),
                'num_ipe' => (string) ($row['ip_e'] ?? ''),
                'num_cnj' => (string) ($row['cnj'] ?? ''),
                'cartorio_designado' => (string) ($row['cartorio_label'] ?? ''),
                'lavrado_unidade' => (string) ($row['lavrado_unidade'] ?? ''),
            ];
        }

        return $rows;
    }

    private function loadNaturezaMap(SQLite3 $legacy): array
    {
        if (! $this->tableExists($legacy, 'analise_naturezas')) {
            return [];
        }

        $result = $legacy->query('SELECT spj, natureza FROM analise_naturezas ORDER BY spj, slot');
        $map = [];

        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $spj = trim((string) ($row['spj'] ?? ''));
            $natureza = trim((string) ($row['natureza'] ?? ''));

            if ($spj === '' || $natureza === '') {
                continue;
            }

            $map[$spj] ??= [];
            $key = mb_strtolower($natureza);
            $existingKeys = array_map('mb_strtolower', $map[$spj]);

            if (! in_array($key, $existingKeys, true)) {
                $map[$spj][] = $natureza;
            }
        }

        return array_map(fn (array $items) => implode('; ', $items), $map);
    }

    private function tableExists(SQLite3 $legacy, string $table): bool
    {
        $statement = $legacy->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:table LIMIT 1");
        $statement->bindValue(':table', $table, SQLITE3_TEXT);

        return (bool) $statement->execute()?->fetchArray(SQLITE3_ASSOC);
    }

    private function tableColumns(SQLite3 $legacy, string $table): array
    {
        $result = $legacy->query(sprintf('PRAGMA table_info(%s)', $table));
        $columns = [];

        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $columns[] = (string) ($row['name'] ?? '');
        }

        return $columns;
    }
}
