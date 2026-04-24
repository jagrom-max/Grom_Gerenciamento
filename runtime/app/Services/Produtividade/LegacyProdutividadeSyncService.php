<?php

namespace App\Services\Produtividade;

use App\Models\Cartorio;
use App\Models\CartorioManagerHistory;
use App\Models\CartorioStatusHistory;
use App\Models\ProductivityFlagrante;
use App\Models\ProductivityStatMonthly;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use SQLite3;

class LegacyProdutividadeSyncService
{
    public function sync(?User $actor = null): array
    {
        if (! config('grom_legacy.enabled')) {
            return $this->emptyResult('A sincronizacao legada de produtividade esta desabilitada neste ambiente.');
        }

        $dbPath = (string) config('grom_legacy.analise_db_path');

        if ($dbPath === '' || ! File::exists($dbPath)) {
            return $this->emptyResult('Base legada de produtividade nao encontrada no caminho configurado.');
        }

        $legacy = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $legacy->enableExceptions(true);
        $legacy->busyTimeout(5000);

        try {
            $cartorioTable = $this->resolveCartorioTable($legacy);
            $cartorioRows = $this->loadRows($legacy, sprintf('SELECT * FROM %s ORDER BY numero, id', $cartorioTable));
            $statsTable = $this->resolveStatsTable($legacy);
            $statsRows = $this->loadRows($legacy, sprintf('SELECT * FROM %s ORDER BY ano, mes, cartorio_numero, id', $statsTable));
            $flagranteRows = $this->tableExists($legacy, 'prod_flagrantes')
                ? $this->loadRows($legacy, 'SELECT * FROM prod_flagrantes ORDER BY ano, mes, id')
                : [];

            $cartorioByLegacyId = [];
            $createdCartorios = 0;
            $updatedCartorios = 0;
            $createdStats = 0;
            $updatedStats = 0;
            $createdFlagrantes = 0;
            $updatedFlagrantes = 0;
            $skippedFlagrantes = 0;

            DB::transaction(function () use (
                $actor,
                $cartorioTable,
                $cartorioRows,
                $statsRows,
                $flagranteRows,
                &$cartorioByLegacyId,
                &$createdCartorios,
                &$updatedCartorios,
                &$createdStats,
                &$updatedStats,
                &$createdFlagrantes,
                &$updatedFlagrantes,
                &$skippedFlagrantes,
            ): void {
                foreach ($cartorioRows as $row) {
                    $legacyId = (int) ($row['id'] ?? 0);
                    $number = (int) ($row['numero'] ?? 0);

                    if ($number <= 0) {
                        continue;
                    }

                    $payload = [
                        'code' => sprintf('CRT-%03d', $number),
                        'name' => $this->preferredLabel($row['nome'] ?? null, $row['designacao'] ?? null, $row['responsavel'] ?? null, $number),
                        'designacao' => $this->cleanNullable($row['designacao'] ?? null),
                        'manager_name' => $this->preferredManagerName($row['responsavel'] ?? null, $row['designacao'] ?? null),
                        'notes' => $this->buildCartorioNotes($row, $cartorioTable),
                        'is_active' => (int) ($row['ativo'] ?? 0) === 1,
                    ];

                    $cartorio = Cartorio::query()->updateOrCreate(['number' => $number], $payload);
                    $cartorioByLegacyId[$legacyId] = $cartorio;

                    if ($cartorio->wasRecentlyCreated) {
                        $createdCartorios++;
                        $this->logInitialCartorioHistory($cartorio, $actor);
                    } else {
                        $updatedCartorios++;
                    }
                }

                foreach ($statsRows as $row) {
                    $cartorio = $this->resolveCartorioForStats($row, $cartorioByLegacyId);

                    if ($cartorio === null) {
                        continue;
                    }

                    $year = (int) ($row['ano'] ?? 0);
                    $month = (int) ($row['mes'] ?? 0);

                    if ($year < 2020 || $month < 1 || $month > 12) {
                        continue;
                    }

                    $payload = [
                        'ip_instaurados' => (int) ($row['ip_instaurados'] ?? 0),
                        'ip_relatados' => (int) ($row['ip_relatados'] ?? 0),
                        'cotas' => (int) ($row['cotas'] ?? 0),
                        'despachos' => (int) ($row['despacho'] ?? $row['despachos'] ?? 0),
                        'concluidos' => (int) ($row['concluido'] ?? $row['concluidos'] ?? 0),
                        'registros' => (int) ($row['registros'] ?? 0),
                        'ips_andamento' => (int) ($row['ips_andamento'] ?? $row['ip_em_andamento'] ?? 0),
                        'flagrantes_total' => (int) ($row['flagrantes'] ?? 0),
                        'flagrantes_ddm' => 0,
                        'flagrantes_outras' => 0,
                        'source_mode' => 'LEGACY',
                        'manual_notes' => $this->buildStatsNotes($row),
                    ];

                    $stat = ProductivityStatMonthly::query()->updateOrCreate(
                        [
                            'cartorio_id' => $cartorio->id,
                            'reference_year' => $year,
                            'reference_month' => $month,
                        ],
                        $payload,
                    );

                    if ($stat->wasRecentlyCreated) {
                        $createdStats++;
                    } else {
                        $updatedStats++;
                    }
                }

                foreach ($flagranteRows as $row) {
                    $legacyCartorioId = (int) ($row['cartorio_id'] ?? 0);
                    $cartorio = $cartorioByLegacyId[$legacyCartorioId] ?? null;

                    if ($cartorio === null) {
                        $skippedFlagrantes++;
                        continue;
                    }

                    $spj = trim((string) ($row['spj'] ?? ''));
                    $ip = trim((string) ($row['ip'] ?? ''));
                    $cnj = trim((string) ($row['cnj'] ?? ''));

                    if ($spj === '' && $ip === '' && $cnj === '') {
                        $skippedFlagrantes++;
                        continue;
                    }

                    $dataFato = $this->parseLegacyDate($row['data'] ?? null);
                    $month = (int) ($row['mes'] ?? ($dataFato ? Carbon::parse($dataFato)->format('n') : 0));
                    $year = (int) ($row['ano'] ?? ($dataFato ? Carbon::parse($dataFato)->format('Y') : 0));

                    if ($dataFato === null || $year < 2020 || $month < 1 || $month > 12) {
                        $skippedFlagrantes++;
                        continue;
                    }

                    $payload = [
                        'source_item_id' => null,
                        'reference_year' => $year,
                        'reference_month' => $month,
                        'naturezas' => $this->cleanNullable($row['natureza'] ?? null),
                        'num_ip' => $this->cleanNullable($row['ip'] ?? null),
                        'num_ipe' => $this->cleanNullable($row['ip_e'] ?? null),
                        'num_cnj' => $this->cleanNullable($row['cnj'] ?? null),
                        'data_fato' => $dataFato,
                        'lavrado_unidade' => ProductivityFlagrante::LAVRADO_OUTRAS,
                        'manually_confirmed' => false,
                        'is_active' => (int) ($row['ativo'] ?? 0) === 1,
                        'confirmed_by' => null,
                        'confirmed_at' => null,
                        'notes' => $this->buildFlagranteNotes($row),
                    ];

                    $flagrante = ProductivityFlagrante::query()->updateOrCreate(
                        [
                            'cartorio_id' => $cartorio->id,
                            'spj' => $spj !== '' ? $spj : null,
                        ],
                        $payload,
                    );

                    if ($flagrante->wasRecentlyCreated) {
                        $createdFlagrantes++;
                    } else {
                        $updatedFlagrantes++;
                    }
                }
            });

            return [
                'synced' => true,
                'source_name' => basename($dbPath),
                'cartorios' => [
                    'total' => count($cartorioRows),
                    'created' => $createdCartorios,
                    'updated' => $updatedCartorios,
                ],
                'stats' => [
                    'total' => count($statsRows),
                    'created' => $createdStats,
                    'updated' => $updatedStats,
                ],
                'flagrantes' => [
                    'total' => count($flagranteRows),
                    'created' => $createdFlagrantes,
                    'updated' => $updatedFlagrantes,
                    'skipped' => $skippedFlagrantes,
                ],
                'warnings' => [],
            ];
        } finally {
            $legacy->close();
        }
    }

    private function loadRows(SQLite3 $legacy, string $query): array
    {
        $result = $legacy->query($query);
        $rows = [];

        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function resolveStatsTable(SQLite3 $legacy): string
    {
        if ($this->tableExists($legacy, 'estat_cartorio_mensal')) {
            return 'estat_cartorio_mensal';
        }

        if ($this->tableExists($legacy, 'prod_cartorio_stats')) {
            return 'prod_cartorio_stats';
        }

        throw new RuntimeException('A base legada nao possui tabela de estatisticas de cartorio.');
    }

    private function resolveCartorioTable(SQLite3 $legacy): string
    {
        if ($this->tableExists($legacy, 'cartorios')) {
            return 'cartorios';
        }

        if ($this->tableExists($legacy, 'prod_cartorios')) {
            return 'prod_cartorios';
        }

        throw new RuntimeException('A base legada nao possui tabela de cartorios.');
    }

    private function resolveCartorioForStats(array $row, array $cartorioByLegacyId): ?Cartorio
    {
        $number = (int) ($row['cartorio_numero'] ?? 0);

        if ($number > 0) {
            $cartorio = Cartorio::query()->where('number', $number)->first();

            if ($cartorio !== null) {
                return $cartorio;
            }
        }

        $legacyId = (int) ($row['cartorio_id'] ?? 0);

        return $cartorioByLegacyId[$legacyId] ?? null;
    }

    private function buildCartorioNotes(array $row, string $sourceTable): string
    {
        $parts = [
            'Origem Python',
            'tabela=' . $sourceTable,
            'id=' . ($row['id'] ?? 'N/D'),
            'numero=' . ($row['numero'] ?? 'N/D'),
        ];

        foreach (['observacao', 'obs', 'criado_em', 'atualizado_em', 'desativado_em'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value !== '') {
                $parts[] = $field . '=' . $value;
            }
        }

        return implode(' | ', $parts);
    }

    private function buildStatsNotes(array $row): string
    {
        $parts = [
            'Origem Python',
            'id=' . ($row['id'] ?? 'N/D'),
            'cartorio_numero=' . ($row['cartorio_numero'] ?? 'N/D'),
            'ano_mes=' . trim((string) ($row['ano_mes'] ?? '')),
        ];

        foreach (['observacao', 'obs', 'audit_motivo', 'audit_em', 'criado_em', 'atualizado_em'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value !== '') {
                $parts[] = $field . '=' . $value;
            }
        }

        return implode(' | ', $parts);
    }

    private function buildFlagranteNotes(array $row): string
    {
        $parts = [
            'Origem Python',
            'id=' . ($row['id'] ?? 'N/D'),
            'ano_mes=' . trim((string) (($row['ano'] ?? '') . '-' . str_pad((string) ($row['mes'] ?? ''), 2, '0', STR_PAD_LEFT))),
        ];

        foreach (['obs', 'criado_em', 'atualizado_em'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value !== '') {
                $parts[] = $field . '=' . $value;
            }
        }

        return implode(' | ', $parts);
    }

    private function logInitialCartorioHistory(Cartorio $cartorio, ?User $actor): void
    {
        CartorioStatusHistory::query()->create([
            'cartorio_id' => $cartorio->id,
            'status' => $cartorio->is_active ? 'ATIVO' : 'INATIVO',
            'reason' => 'Importado da base legada Python.',
            'changed_by' => $actor?->id,
            'changed_at' => now(),
        ]);

        if (filled($cartorio->manager_name)) {
            CartorioManagerHistory::query()->create([
                'cartorio_id' => $cartorio->id,
                'manager_name' => (string) $cartorio->manager_name,
                'reason' => 'Importado da base legada Python.',
                'changed_by' => $actor?->id,
                'changed_at' => now(),
            ]);
        }
    }

    private function parseLegacyDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '//' || $value === '00/00/0000') {
            return null;
        }

        foreach (['d/m/Y', 'd/m/y', 'Y-m-d', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function preferredLabel(mixed $name, mixed $designacao, mixed $responsavel, int $number): string
    {
        $name = trim((string) $name);
        $designacao = trim((string) $designacao);
        $responsavel = trim((string) $responsavel);

        if ($name !== '') {
            return $name;
        }

        if ($designacao !== '') {
            return $designacao;
        }

        if ($responsavel !== '') {
            return $responsavel;
        }

        return sprintf('Cartorio %d', $number);
    }

    private function preferredManagerName(mixed $responsavel, mixed $designacao): ?string
    {
        $responsavel = trim((string) $responsavel);
        $designacao = trim((string) $designacao);

        if ($responsavel !== '') {
            return $responsavel;
        }

        return $designacao !== '' ? $designacao : null;
    }

    private function cleanNullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function tableExists(SQLite3 $legacy, string $table): bool
    {
        $statement = $legacy->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:table LIMIT 1");
        $statement->bindValue(':table', $table, SQLITE3_TEXT);

        return (bool) $statement->execute()?->fetchArray(SQLITE3_ASSOC);
    }

    private function emptyResult(string $warning): array
    {
        return [
            'synced' => false,
            'source_name' => null,
            'cartorios' => ['total' => 0, 'created' => 0, 'updated' => 0],
            'stats' => ['total' => 0, 'created' => 0, 'updated' => 0],
            'flagrantes' => ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0],
            'warnings' => [$warning],
        ];
    }
}
