<?php

namespace App\Services\Escalas;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use SQLite3;

class LegacyEscalasReader
{
    public function snapshotForMonth(?User $actor = null, ?int $year = null, ?int $month = null): array
    {
        if (! config('grom_legacy.enabled')) {
            throw new RuntimeException('A leitura da base legada esta desabilitada neste ambiente.');
        }

        $year ??= (int) now()->format('Y');
        $month ??= (int) now()->format('n');

        $dbPath = (string) config('grom_legacy.analise_db_path');

        if ($dbPath === '') {
            throw new InvalidArgumentException('Caminho da base legada nao configurado.');
        }

        if (! File::exists($dbPath)) {
            throw new InvalidArgumentException('Base legada nao encontrada no caminho configurado.');
        }

        $legacy = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $legacy->enableExceptions(true);
        $legacy->busyTimeout(5000);

        try {
            $scaleExists = $this->tableExists($legacy, 'escala_mensal');
            $plantoesExists = $this->tableExists($legacy, 'plantoes_funcionarios');
            $funcionariosExists = $this->tableExists($legacy, 'funcionarios');
            $holidayExists = $this->tableExists($legacy, 'feriados');
            $plantoesExternosExists = $this->tableExists($legacy, 'plantoes_externos');
            $afastamentoRows = $funcionariosExists
                ? $this->loadRows($legacy, 'SELECT * FROM afastamentos ORDER BY data_inicio ASC, id ASC')
                : [];

            $availableYears = $scaleExists ? $this->loadDistinctYears($legacy, 'escala_mensal', 'ano') : [];
            $availableMonths = $scaleExists ? $this->loadDistinctMonths($legacy, 'escala_mensal', 'ano', $year) : [];
            $currentVersion = $scaleExists ? $this->loadCurrentVersion($legacy, $year, $month) : null;
            $scaleRows = $scaleExists && $currentVersion !== null
                ? $this->loadScaleRows($legacy, $year, $month, $currentVersion)
                : [];
            $holidays = $holidayExists
                ? $this->loadHolidays($legacy, $year, $month)
                : [];
            $plantoes = $plantoesExists
                ? $this->loadPlantaoAssignments($legacy, $year, $month)
                : [];
            $catalog = $plantoesExternosExists
                ? $this->loadPlantaoCatalog($legacy)
                : [];
            $funcionariosSummary = $funcionariosExists
                ? $this->loadFuncionariosSummary($legacy)
                : [
                    'total' => 0,
                    'ativos' => 0,
                    'concorrem_escala' => 0,
                    'em_afastamento' => 0,
                ];
            $legacyFuncionarios = $funcionariosExists
                ? $this->loadFuncionarios($legacy, $afastamentoRows)
                : [];
        } finally {
            $legacy->close();
        }

        $scaleRows = $this->decorateScaleRows($scaleRows, $holidays);

        $afastamentosMes = $funcionariosExists
            ? $this->loadAfastamentosMes($afastamentoRows, $legacyFuncionarios, $year, $month)
            : [];

        return [
            'source_name' => basename($dbPath),
            'year' => $year,
            'month' => $month,
            'month_label' => Carbon::create()->month($month)->locale('pt_BR')->isoFormat('MMMM'),
            'available_years' => $availableYears,
            'available_months' => $availableMonths,
            'version' => $currentVersion,
            'scale_rows' => $scaleRows,
            'holidays' => $holidays,
            'plantoes' => $plantoes,
            'plantao_catalog' => $catalog,
            'funcionarios' => $legacyFuncionarios,
            'afastamentos_mes' => $afastamentosMes,
            'summary' => [
                'dias_total' => count($scaleRows),
                'dias_com_escrivao' => $this->countFilled($scaleRows, 'escrivao'),
                'dias_com_operacional' => $this->countFilled($scaleRows, 'operacional'),
                'dias_com_delegada' => $this->countFilled($scaleRows, 'delegada'),
                'dias_com_plantao_externo' => $this->countFilled($scaleRows, 'plantao_externo'),
                'feriados_mes' => count($holidays),
                'plantoes_atribuicoes' => count($plantoes),
                'plantoes_catalogo_ativos' => $this->countPlantaoCatalogActive($catalog),
                'funcionarios_total' => $funcionariosSummary['total'],
                'funcionarios_ativos' => $funcionariosSummary['ativos'],
                'funcionarios_concorrem' => $funcionariosSummary['concorrem_escala'],
                'funcionarios_em_afastamento' => $funcionariosSummary['em_afastamento'],
            ],
            'warnings' => $this->buildWarnings($currentVersion, $scaleRows, $plantoes, $holidays),
        ];
    }

    public function availableMonthsForYear(?int $year = null): array
    {
        $year ??= (int) now()->format('Y');

        if (! config('grom_legacy.enabled')) {
            return [];
        }

        $dbPath = (string) config('grom_legacy.analise_db_path');

        if ($dbPath === '' || ! File::exists($dbPath)) {
            return [];
        }

        $legacy = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $legacy->enableExceptions(true);

        try {
            if (! $this->tableExists($legacy, 'escala_mensal')) {
                return [];
            }

            return $this->loadDistinctMonths($legacy, 'escala_mensal', 'ano', $year);
        } finally {
            $legacy->close();
        }
    }

    private function loadCurrentVersion(SQLite3 $legacy, int $year, int $month): ?int
    {
        $statement = $legacy->prepare('SELECT MAX(versao) AS versao FROM escala_mensal WHERE ano = :ano AND mes = :mes');
        $statement->bindValue(':ano', $year, SQLITE3_INTEGER);
        $statement->bindValue(':mes', $month, SQLITE3_INTEGER);
        $result = $statement->execute();
        $row = $result?->fetchArray(SQLITE3_ASSOC);

        if (! $row || $row['versao'] === null) {
            return null;
        }

        return (int) $row['versao'];
    }

    private function loadScaleRows(SQLite3 $legacy, int $year, int $month, int $version): array
    {
        $statement = $legacy->prepare(
            'SELECT data, escrivao, operacional, fechar, delegada, plantao_externo
             FROM escala_mensal
             WHERE ano = :ano AND mes = :mes AND versao = :versao
             ORDER BY data ASC',
        );
        $statement->bindValue(':ano', $year, SQLITE3_INTEGER);
        $statement->bindValue(':mes', $month, SQLITE3_INTEGER);
        $statement->bindValue(':versao', $version, SQLITE3_INTEGER);
        $result = $statement->execute();
        $rows = [];

        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $date = $this->parseDate((string) ($row['data'] ?? ''));

            $rows[] = [
                'date' => $date?->toDateString(),
                'day_label' => $date?->locale('pt_BR')->isoFormat('ddd'),
                'weekday_iso' => $date?->dayOfWeekIso,
                'date_label' => $date?->format('d/m') ?? '',
                'escrivao' => trim((string) ($row['escrivao'] ?? '')),
                'operacional' => trim((string) ($row['operacional'] ?? '')),
                'fechar' => trim((string) ($row['fechar'] ?? '')),
                'delegada' => trim((string) ($row['delegada'] ?? '')),
                'plantao_externo' => trim((string) ($row['plantao_externo'] ?? '')),
            ];
        }

        return $rows;
    }

    private function loadHolidays(SQLite3 $legacy, int $year, int $month): array
    {
        $statement = $legacy->prepare(
            'SELECT data, descricao, tipo
             FROM feriados
             WHERE substr(data, 1, 7) = :ym
             ORDER BY data ASC',
        );
        $statement->bindValue(':ym', sprintf('%04d-%02d', $year, $month), SQLITE3_TEXT);
        $result = $statement->execute();
        $rows = [];

        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $date = $this->parseDate((string) ($row['data'] ?? ''));

            $rows[] = [
                'date' => $date?->toDateString(),
                'date_label' => $date?->format('d/m') ?? '',
                'descricao' => trim((string) ($row['descricao'] ?? '')),
                'tipo' => trim((string) ($row['tipo'] ?? '')),
            ];
        }

        return $rows;
    }

    private function loadPlantaoAssignments(SQLite3 $legacy, int $year, int $month): array
    {
        $statement = $legacy->prepare(
            'SELECT
                pf.data AS data,
                pf.funcionario_id AS funcionario_id,
                pf.plantao_id AS plantao_id,
                COALESCE(NULLIF(TRIM(f.nome_simplificado), \'\'), f.nome) AS funcionario_nome,
                f.cargo AS funcionario_cargo,
                f.setor AS funcionario_setor,
                COALESCE(pe.nome, \'\') AS plantao_nome,
                COALESCE(pe.sigla, \'\') AS plantao_sigla,
                COALESCE(pe.unidade, \'\') AS plantao_unidade,
                COALESCE(pe.regra, \'\') AS plantao_regra
             FROM plantoes_funcionarios pf
             LEFT JOIN funcionarios f ON f.id = pf.funcionario_id
             LEFT JOIN plantoes_externos pe ON pe.id = pf.plantao_id
             WHERE substr(pf.data, 1, 7) = :ym
             ORDER BY pf.data ASC, COALESCE(pe.sigla, pe.nome), COALESCE(f.nome_simplificado, f.nome)',
        );
        $statement->bindValue(':ym', sprintf('%04d-%02d', $year, $month), SQLITE3_TEXT);
        $result = $statement->execute();
        $rows = [];

        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $date = $this->parseDate((string) ($row['data'] ?? ''));

            $rows[] = [
                'date' => $date?->toDateString(),
                'date_label' => $date?->format('d/m') ?? '',
                'weekday' => $date?->locale('pt_BR')->isoFormat('ddd'),
                'funcionario_id' => $row['funcionario_id'] !== null ? (int) $row['funcionario_id'] : null,
                'funcionario_nome' => trim((string) ($row['funcionario_nome'] ?? '')) ?: 'Nao informado',
                'funcionario_cargo' => trim((string) ($row['funcionario_cargo'] ?? '')),
                'funcionario_setor' => trim((string) ($row['funcionario_setor'] ?? '')),
                'plantao_id' => $row['plantao_id'] !== null ? (int) $row['plantao_id'] : null,
                'plantao_nome' => trim((string) ($row['plantao_nome'] ?? '')),
                'plantao_sigla' => trim((string) ($row['plantao_sigla'] ?? '')),
                'plantao_unidade' => trim((string) ($row['plantao_unidade'] ?? '')),
                'plantao_regra' => trim((string) ($row['plantao_regra'] ?? '')),
            ];
        }

        return $rows;
    }

    private function loadPlantaoCatalog(SQLite3 $legacy): array
    {
        $result = $legacy->query(
            'SELECT id, nome, sigla, unidade, regra, ativo
             FROM plantoes_externos
             ORDER BY ativo DESC, sigla, nome',
        );
        $rows = [];

        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nome' => trim((string) ($row['nome'] ?? '')),
                'sigla' => trim((string) ($row['sigla'] ?? '')),
                'unidade' => trim((string) ($row['unidade'] ?? '')),
                'regra' => trim((string) ($row['regra'] ?? '')),
                'ativo' => (int) ($row['ativo'] ?? 0) === 1,
            ];
        }

        return $rows;
    }

    private function buildCurrentAfastamentoMap(array $afastamentoRows): array
    {
        $today = Carbon::today();
        $map = [];

        foreach ($afastamentoRows as $row) {
            $funcionarioId = (int) ($row['funcionario_id'] ?? 0);
            $startDate = $this->parseLegacyDate($row['data_inicio'] ?? null);
            $endDate = $this->parseLegacyDate($row['data_fim'] ?? null);
            $active = (int) ($row['ativo'] ?? 0) === 1;

            if ($funcionarioId <= 0 || $startDate === null || ! $active) {
                continue;
            }

            $start = Carbon::parse($startDate);
            $end = $endDate !== null ? Carbon::parse($endDate) : null;

            if ($start->greaterThan($today)) {
                continue;
            }

            if ($end !== null && $end->lessThan($today)) {
                continue;
            }

            if (! isset($map[$funcionarioId])) {
                $map[$funcionarioId] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'tipo' => trim((string) ($row['tipo'] ?? '')),
                    'data_inicio' => $startDate,
                    'data_fim' => $endDate,
                    'motivo' => trim((string) ($row['motivo'] ?? '')),
                    'observacoes' => trim((string) ($row['observacoes'] ?? '')),
                    'ativo' => true,
                ];
            }
        }

        return $map;
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

    private function loadFuncionarios(SQLite3 $legacy, array $afastamentoRows): array
    {
        $result = $legacy->query(
            'SELECT *
             FROM funcionarios
             ORDER BY ativo DESC, nome ASC',
        );
        $afastamentosMap = $this->buildCurrentAfastamentoMap($afastamentoRows);
        $rows = [];

        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $legacyId = (int) ($row['id'] ?? 0);
            $currentAfastamento = $afastamentosMap[$legacyId] ?? null;

            $rows[] = [
                'legacy_id' => $legacyId,
                'legacy_key' => sprintf('LEG-%04d', $legacyId),
                'nome' => trim((string) ($row['nome'] ?? '')),
                'nome_simplificado' => trim((string) ($row['nome_simplificado'] ?? '')),
                'cargo' => trim((string) ($row['cargo'] ?? '')),
                'setor' => trim((string) ($row['setor'] ?? '')),
                'telefone' => trim((string) ($row['telefone'] ?? '')),
                'rg' => trim((string) ($row['rg'] ?? '')),
                'cpf' => trim((string) ($row['cpf'] ?? '')),
                'data_aniversario' => $this->parseDate((string) ($row['data_aniversario'] ?? ''))?->toDateString(),
                'data_designacao' => $this->parseDate((string) ($row['data_designacao'] ?? ''))?->toDateString(),
                'data_remocao' => $this->parseDate((string) ($row['data_remocao'] ?? ''))?->toDateString(),
                'data_afastamento' => $this->parseDate((string) ($row['data_afastamento'] ?? ''))?->toDateString(),
                'concorre_escala' => (int) ($row['concorre_escala'] ?? 0) === 1,
                'ativo' => (int) ($row['ativo'] ?? 0) === 1,
                'observacoes' => trim((string) ($row['observacoes'] ?? '')),
                'current_afastamento' => $currentAfastamento,
            ];
        }

        return $rows;
    }

    private function loadFuncionariosSummary(SQLite3 $legacy): array
    {
        $summary = [
            'total' => (int) $legacy->querySingle('SELECT COUNT(*) FROM funcionarios'),
            'ativos' => (int) $legacy->querySingle('SELECT COUNT(*) FROM funcionarios WHERE COALESCE(ativo, 0) = 1'),
            'concorrem_escala' => (int) $legacy->querySingle('SELECT COUNT(*) FROM funcionarios WHERE COALESCE(ativo, 0) = 1 AND COALESCE(concorre_escala, 0) = 1'),
            'em_afastamento' => (int) $legacy->querySingle('SELECT COUNT(*) FROM funcionarios WHERE COALESCE(data_afastamento, \'\') <> \'\''),
        ];

        return $summary;
    }

    private function loadDistinctYears(SQLite3 $legacy, string $table, string $column): array
    {
        $result = $legacy->query(sprintf('SELECT DISTINCT %s AS value FROM %s WHERE %s IS NOT NULL ORDER BY value DESC', $column, $table, $column));
        $values = [];

        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            if ($row['value'] !== null) {
                $values[] = (int) $row['value'];
            }
        }

        return $values;
    }

    private function loadDistinctMonths(SQLite3 $legacy, string $table, string $yearColumn, int $year): array
    {
        $statement = $legacy->prepare(sprintf(
            'SELECT DISTINCT mes AS value FROM %s WHERE %s = :year AND mes IS NOT NULL ORDER BY value ASC',
            $table,
            $yearColumn,
        ));
        $statement->bindValue(':year', $year, SQLITE3_INTEGER);
        $result = $statement->execute();
        $values = [];

        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            if ($row['value'] !== null) {
                $values[] = (int) $row['value'];
            }
        }

        return $values;
    }

    private function buildWarnings(?int $version, array $scaleRows, array $plantoes, array $holidays): array
    {
        $warnings = [];

        if ($version === null) {
            $warnings[] = 'Nao foi encontrada versao salva para o mes consultado.';
        }

        if (empty($scaleRows)) {
            $warnings[] = 'Nao ha linhas de escala mensal para o periodo consultado.';
        }

        if (empty($plantoes)) {
            $warnings[] = 'Nao ha plantoes externos vinculados ao periodo consultado.';
        }

        if (empty($holidays)) {
            $warnings[] = 'Nao ha feriados registrados neste mes na base legada.';
        }

        return $warnings;
    }

    private function decorateScaleRows(array $rows, array $holidays): array
    {
        $holidayMap = [];

        foreach ($holidays as $holiday) {
            if (! empty($holiday['date'])) {
                $holidayMap[$holiday['date']] = $holiday;
            }
        }

        return array_map(static function (array $row) use ($holidayMap): array {
            $holiday = $row['date'] && isset($holidayMap[$row['date']]) ? $holidayMap[$row['date']] : null;
            $weekdayIso = (int) ($row['weekday_iso'] ?? 0);
            $rawHolidayMarker = collect([
                $row['escrivao'] ?? null,
                $row['operacional'] ?? null,
                $row['fechar'] ?? null,
                $row['delegada'] ?? null,
            ])->contains(fn ($value): bool => trim((string) $value) === 'FERIADO');

            $row['is_holiday'] = $holiday !== null;
            $row['is_weekend'] = ! $row['is_holiday'] && in_array($weekdayIso, [6, 7], true);
            $row['holiday_label'] = $holiday['descricao'] ?? null;
            $row['display_mode'] = $row['is_holiday'] || $rawHolidayMarker
                ? 'holiday'
                : ($row['is_weekend'] ? 'weekend' : 'normal');

            return $row;
        }, $rows);
    }

    private function countFilled(array $rows, string $key): int
    {
        return Collection::make($rows)->filter(function (array $row) use ($key): bool {
            $value = trim((string) ($row[$key] ?? ''));

            return $value !== '' && strtoupper($value) !== 'FERIADO';
        })->count();
    }

    private function countPlantaoCatalogActive(array $catalog): int
    {
        return Collection::make($catalog)->where('ativo', true)->count();
    }

    private function parseLegacyDate(mixed $value): ?string
    {
        $date = $this->parseDate((string) $value);

        return $date?->toDateString();
    }

    private function loadAfastamentosMes(array $afastamentoRows, array $legacyFuncionarios, int $year, int $month): array
    {
        $mesInicio = sprintf('%04d-%02d-01', $year, $month);
        $mesFim    = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $funcMap = [];
        foreach ($legacyFuncionarios as $f) {
            $funcMap[(int) $f['legacy_id']] = $f;
        }

        $result = [];
        foreach ($afastamentoRows as $row) {
            if ((int) ($row['ativo'] ?? 0) !== 1) {
                continue;
            }

            $dataInicio = $this->parseLegacyDate($row['data_inicio'] ?? null);
            $dataFim    = $this->parseLegacyDate($row['data_fim'] ?? null);

            if ($dataInicio === null || $dataInicio > $mesFim) {
                continue;
            }

            if ($dataFim !== null && $dataFim < $mesInicio) {
                continue;
            }

            $funcId = (int) ($row['funcionario_id'] ?? 0);
            $func   = $funcMap[$funcId] ?? null;

            $result[] = [
                'funcionario_id'    => $funcId,
                'funcionario_nome'  => $func ? (trim((string) ($func['nome_simplificado'] ?? '')) ?: trim((string) ($func['nome'] ?? ''))) : 'N/D',
                'funcionario_cargo' => $func ? trim((string) ($func['cargo'] ?? '')) : '',
                'tipo'              => trim((string) ($row['tipo'] ?? '')),
                'data_inicio'       => $dataInicio,
                'data_fim'          => $dataFim,
            ];
        }

        usort($result, static fn (array $a, array $b): int => strcmp($a['data_inicio'], $b['data_inicio']));

        return $result;
    }

    private function tableExists(SQLite3 $legacy, string $table): bool
    {
        $statement = $legacy->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:table LIMIT 1");
        $statement->bindValue(':table', $table, SQLITE3_TEXT);

        return (bool) $statement->execute()?->fetchArray(SQLITE3_ASSOC);
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
