<?php

namespace App\Http\Controllers\Calendarios;

use App\Http\Controllers\Controller;
use App\Models\RhAfastamento;
use App\Models\RhHoliday;
use App\Services\Rh\LegacyFuncionariosReader;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use SQLite3;

class CalendariosController extends Controller
{
    public function __invoke(Request $request, LegacyFuncionariosReader $reader): View
    {
        return $this->index($request, $reader);
    }

    public function index(Request $request, LegacyFuncionariosReader $reader): View
    {
        $filters = $this->resolveFilters($request);
        try {
            $legacySnapshot = $reader->snapshot();
        } catch (\Throwable) {
            $legacySnapshot = ['employees' => [], 'afastamentos' => [], 'warning' => 'Base legada indisponivel.'];
        }

        $monthStart = Carbon::create($filters['ano'], $filters['mes'], 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $afastamentos = RhAfastamento::query()
            ->with(['funcionario.cargo'])
            ->whereDate('start_date', '<=', $monthEnd->toDateString())
            ->where(function ($query) use ($monthStart): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $monthStart->toDateString());
            })
            ->orderBy('start_date')
            ->orderBy('created_at')
            ->get();

        $contextHolidays = RhHoliday::query()
            ->where('is_active', true)
            ->whereDate('holiday_date', '>=', $monthStart->toDateString())
            ->whereDate('holiday_date', '<=', $monthEnd->toDateString())
            ->orderBy('holiday_date')
            ->get();

        $legacyHolidayRows = collect($this->loadLegacyHolidayRows($filters['ano'], $filters['mes']));
        $legacyAllHolidayRows = collect($this->loadLegacyHolidayRows());
        $legacyAbsenceRowsSource = collect($legacySnapshot['afastamentos'] ?? []);
        $legacyEmployees = $legacySnapshot['employees'] ?? [];

        $availableYears = $this->buildAvailableYears($legacyAbsenceRowsSource, $legacyAllHolidayRows);
        $availableMonths = range(1, 12);

        [$days, $criticalDays, $absenceRows] = $this->buildAbsenceCalendar($monthStart, $monthEnd, $afastamentos, $contextHolidays);
        [$legacyCriticalDays, $legacyAbsenceRows, $legacyStats] = $this->buildLegacyAbsenceCalendar(
            $legacyAbsenceRowsSource,
            $legacyEmployees,
            $monthStart,
            $monthEnd,
        );

        $afastamentosNoMes = collect($absenceRows);
        $funcionariosAfastados = $afastamentosNoMes
            ->pluck('funcionario_id')
            ->filter()
            ->unique()
            ->count();

        $afastamentosEmAberto = $afastamentosNoMes
            ->filter(fn (array $afastamento): bool => $afastamento['end_date'] === null)
            ->count();

        $diasComAfastamento = collect($days)
            ->filter(fn (array $day): bool => $day['absence_count'] > 0)
            ->count();

        return view('calendarios.index', [
            'filters' => $filters,
            'snapshot' => [
                'source_name' => $legacySnapshot['source_name'] ?? null,
                'year' => $filters['ano'],
                'month' => $filters['mes'],
                'month_label' => Carbon::create()->month($filters['mes'])->locale('pt_BR')->isoFormat('MMMM'),
                'available_years' => $availableYears,
                'available_months' => $availableMonths,
                'summary' => $legacySnapshot['summary'] ?? [],
            ],
            'days' => $days,
            'calendarSlots' => $this->buildCalendarSlots($days, $monthStart),
            'criticalDays' => $criticalDays,
            'absenceRows' => $afastamentosNoMes,
            'legacyCriticalDays' => $legacyCriticalDays,
            'legacyAbsenceRows' => $legacyAbsenceRows,
            'editingHoliday' => $request->filled('holiday')
                ? RhHoliday::query()->find($request->string('holiday')->toString())
                : null,
            'rhHolidays' => $contextHolidays,
            'contextHolidays' => $contextHolidays,
            'upcomingRhHolidays' => RhHoliday::query()
                ->where('is_active', true)
                ->whereDate('holiday_date', '>=', now()->startOfDay())
                ->orderBy('holiday_date')
                ->limit(8)
                ->get(),
            'legacyHolidays' => $legacyHolidayRows,
            'summary' => [
                'dias_total' => count($days),
                'afastamentos_total' => $afastamentosNoMes->count(),
                'funcionarios_afastados' => $funcionariosAfastados,
                'dias_com_afastamento' => $diasComAfastamento,
                'dias_com_sobreposicao' => count($criticalDays),
                'afastamentos_em_aberto' => $afastamentosEmAberto,
                'feriados_contexto' => $contextHolidays->count(),
                'legacy_funcionarios_total' => $legacySnapshot['summary']['total'] ?? 0,
                'legacy_funcionarios_ativos' => $legacySnapshot['summary']['ativos'] ?? 0,
                'legacy_funcionarios_concorrem' => $legacySnapshot['summary']['concorrem_escala'] ?? 0,
                'legacy_funcionarios_em_afastamento' => $legacySnapshot['summary']['em_afastamento'] ?? 0,
                'legacy_afastamentos_total' => $legacyStats['afastamentos_total'],
                'legacy_afastamentos_em_vigor' => $legacySnapshot['summary']['afastamentos_em_vigor'] ?? 0,
                'legacy_feriados_mes' => $legacyHolidayRows->count(),
                'legacy_dias_com_afastamento' => $legacyStats['dias_com_afastamento'],
                'legacy_dias_com_sobreposicao' => $legacyStats['dias_com_sobreposicao'],
                'legacy_afastamentos_em_aberto' => $legacyStats['afastamentos_em_aberto'],
                'legacy_funcionarios_afastados' => $legacyStats['funcionarios_afastados'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'holiday_id' => ['nullable', 'string', 'exists:rh_holidays,id'],
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', Rule::in(['nacional', 'estadual', 'municipal', 'facultativo'])],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $holiday = $this->upsertHolidayByDate($data);

        return redirect()
            ->route('calendarios.index', [
                'ano' => Carbon::parse($data['holiday_date'])->year,
                'mes' => Carbon::parse($data['holiday_date'])->month,
                'holiday' => $holiday->id,
            ])
            ->with('status', 'Feriado salvo com sucesso.');
    }

    public function toggleHolidayActive(RhHoliday $holiday, Request $request): RedirectResponse
    {
        $holiday->update([
            'is_active' => ! $holiday->is_active,
        ]);

        return redirect()
            ->route('calendarios.index', [
                'ano' => $request->integer('ano', (int) $holiday->holiday_date?->format('Y') ?: now()->year),
                'mes' => $request->integer('mes', (int) $holiday->holiday_date?->format('n') ?: now()->month),
            ])
            ->with('status', 'Status do feriado atualizado.');
    }

    public function syncLegacy(Request $request): RedirectResponse
    {
        $filters = $this->resolveFilters($request);
        $legacyHolidayRows = $this->loadLegacyHolidayRows();

        $imported = 0;
        foreach ($legacyHolidayRows as $holidayRow) {
            if (empty($holidayRow['date']) || empty($holidayRow['descricao'])) {
                continue;
            }

            $this->upsertHolidayByDate([
                'holiday_date' => $holidayRow['date'],
                'name' => trim((string) $holidayRow['descricao']),
                'scope' => $this->normalizeHolidayScope((string) ($holidayRow['tipo'] ?? 'nacional')),
                'notes' => trim(sprintf(
                    'Importado da base legada Python | id=%s | tipo=%s',
                    (string) ($holidayRow['id'] ?? 'N/D'),
                    trim((string) ($holidayRow['tipo'] ?? ''))
                )),
                'is_active' => true,
            ]);

            $imported++;
        }

        return redirect()
            ->route('calendarios.index', $filters)
            ->with('status', $imported > 0
                ? sprintf('%d feriado(s) importado(s) da base legada.', $imported)
                : 'Nenhum feriado encontrado na base legada para importar.');
    }

    private function resolveFilters(Request $request): array
    {
        $data = $request->validate([
            'ano' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'mes' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        return [
            'ano' => (int) ($data['ano'] ?? now()->year),
            'mes' => (int) ($data['mes'] ?? now()->month),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $legacyAbsences
     * @param Collection<int, array<string, mixed>> $legacyHolidays
     * @return array<int, int>
     */
    private function buildAvailableYears(Collection $legacyAbsences, Collection $legacyHolidays): array
    {
        $years = collect();

        foreach ($legacyAbsences as $row) {
            foreach (['data_inicio', 'data_fim'] as $field) {
                $date = $this->parseLegacyDate($row[$field] ?? null);
                if ($date !== null) {
                    $years->push((int) $date->format('Y'));
                }
            }
        }

        foreach ($legacyHolidays as $row) {
            $date = $this->parseLegacyDate($row['date'] ?? null);
            if ($date !== null) {
                $years->push((int) $date->format('Y'));
            }
        }

        $availableYears = $years->filter()->unique()->sort()->values()->all();

        return $availableYears !== [] ? $availableYears : [now()->year];
    }

    /**
     * @param array<int, array<string, mixed>> $days
     * @return array<int, array<string, mixed>|null>
     */
    private function buildCalendarSlots(array $days, Carbon $monthStart): array
    {
        $slots = [];

        for ($i = 1; $i < $monthStart->dayOfWeekIso; $i++) {
            $slots[] = null;
        }

        foreach ($days as $day) {
            $slots[] = $day;
        }

        while (count($slots) % 7 !== 0) {
            $slots[] = null;
        }

        return $slots;
    }

    /**
     * @param Collection<int, RhAfastamento> $afastamentos
     * @param Collection<int, RhHoliday> $holidays
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>}
     */
    private function buildAbsenceCalendar(Carbon $monthStart, Carbon $monthEnd, Collection $afastamentos, Collection $holidays): array
    {
        $holidayMap = $holidays->keyBy(fn (RhHoliday $holiday): string => $holiday->holiday_date?->toDateString() ?? '');
        $dayEntries = [];

        $cursor = $monthStart->copy();
        while ($cursor->lessThanOrEqualTo($monthEnd)) {
            $dateKey = $cursor->toDateString();
            $holiday = $holidayMap->get($dateKey);

            $dayEntries[$dateKey] = [
                'date' => $dateKey,
                'day' => $cursor->day,
                'weekday' => $cursor->locale('pt_BR')->isoFormat('ddd'),
                'is_today' => $cursor->isToday(),
                'is_weekend' => $cursor->isWeekend(),
                'holiday' => $holiday ? [
                    'name' => $holiday->name,
                    'scope' => $holiday->scope,
                ] : null,
                'absences' => [],
                'absence_count' => 0,
                'has_conflict' => false,
            ];

            $cursor->addDay();
        }

        $formattedAbsences = [];

        foreach ($afastamentos as $afastamento) {
            $formatted = $this->formatAbsenceForCalendar($afastamento);
            $formattedAbsences[$formatted['id']] = $formatted;

            $start = Carbon::parse($afastamento->start_date)->startOfDay();
            $end = $afastamento->end_date ? Carbon::parse($afastamento->end_date)->startOfDay() : $monthEnd->copy();

            if ($start->greaterThan($monthEnd) || $end->lessThan($monthStart)) {
                continue;
            }

            $rangeStart = $start->lessThan($monthStart) ? $monthStart->copy() : $start;
            $rangeEnd = $end->greaterThan($monthEnd) ? $monthEnd->copy() : $end;

            foreach (CarbonPeriod::create($rangeStart, '1 day', $rangeEnd) as $date) {
                $dateKey = $date->toDateString();

                if (! isset($dayEntries[$dateKey])) {
                    continue;
                }

                $dayEntries[$dateKey]['absences'][] = $formatted;
            }
        }

        $criticalDays = [];
        $conflictDayMap = [];

        foreach ($dayEntries as $dateKey => &$dayEntry) {
            $dayEntry['absence_count'] = count($dayEntry['absences']);
            $dayEntry['has_conflict'] = $dayEntry['absence_count'] > 1;

            if ($dayEntry['has_conflict']) {
                $criticalDays[] = [
                    'date' => $dateKey,
                    'day' => $dayEntry['day'],
                    'weekday' => $dayEntry['weekday'],
                    'absence_count' => $dayEntry['absence_count'],
                    'names' => collect($dayEntry['absences'])->pluck('funcionario_short')->filter()->values()->all(),
                    'reasons' => collect($dayEntry['absences'])->pluck('reason')->filter()->values()->all(),
                ];

                foreach ($dayEntry['absences'] as $absence) {
                    $conflictDayMap[$absence['id']] = ($conflictDayMap[$absence['id']] ?? 0) + 1;
                }
            }
        }
        unset($dayEntry);

        $absenceRows = [];
        foreach ($formattedAbsences as $absence) {
            $absence['conflict_days'] = $conflictDayMap[$absence['id']] ?? 0;
            $absence['has_conflict'] = $absence['conflict_days'] > 0;
            $absenceRows[] = $absence;
        }

        return [array_values($dayEntries), $criticalDays, $absenceRows];
    }

    /**
     * @param Collection<int, array<string, mixed>> $afastamentos
     * @param array<int, array<string, mixed>> $employees
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<string, int>}
     */
    private function buildLegacyAbsenceCalendar(Collection $afastamentos, array $employees, Carbon $monthStart, Carbon $monthEnd): array
    {
        $employeeMap = collect($employees)->keyBy(fn (array $employee): int => (int) ($employee['legacy_id'] ?? 0));
        $dayEntries = [];

        $cursor = $monthStart->copy();
        while ($cursor->lessThanOrEqualTo($monthEnd)) {
            $dateKey = $cursor->toDateString();
            $dayEntries[$dateKey] = [
                'date' => $dateKey,
                'day' => $cursor->day,
                'weekday' => $cursor->locale('pt_BR')->isoFormat('ddd'),
                'absences' => [],
            ];

            $cursor->addDay();
        }

        $formattedAbsences = [];

        foreach ($afastamentos as $afastamento) {
            $formatted = $this->formatLegacyAbsenceForCalendar($afastamento, $employeeMap);

            if ($formatted === null) {
                continue;
            }

            $formattedAbsences[$formatted['id']] = $formatted;

            $start = $this->parseLegacyDate($afastamento['data_inicio'] ?? null);
            $end = $this->parseLegacyDate($afastamento['data_fim'] ?? null);

            if ($start === null) {
                continue;
            }

            $start = $start->startOfDay();
            $end = $end?->startOfDay() ?? $start->copy();

            if ($start->greaterThan($monthEnd) || $end->lessThan($monthStart)) {
                continue;
            }

            $rangeStart = $start->lessThan($monthStart) ? $monthStart->copy() : $start;
            $rangeEnd = $end->greaterThan($monthEnd) ? $monthEnd->copy() : $end;

            foreach (CarbonPeriod::create($rangeStart, '1 day', $rangeEnd) as $date) {
                $dateKey = $date->toDateString();

                if (! isset($dayEntries[$dateKey])) {
                    continue;
                }

                $dayEntries[$dateKey]['absences'][] = $formatted;
            }
        }

        $criticalDays = [];
        $conflictDayMap = [];
        $daysWithAbsence = 0;

        foreach ($dayEntries as $dateKey => &$dayEntry) {
            $absenceCount = count($dayEntry['absences']);

            if ($absenceCount > 0) {
                $daysWithAbsence++;
            }

            if ($absenceCount > 1) {
                $criticalDays[] = [
                    'date' => $dateKey,
                    'day' => $dayEntry['day'],
                    'weekday' => $dayEntry['weekday'],
                    'absence_count' => $absenceCount,
                    'names' => collect($dayEntry['absences'])->pluck('funcionario_short')->filter()->values()->all(),
                    'reasons' => collect($dayEntry['absences'])->pluck('reason')->filter()->values()->all(),
                ];

                foreach ($dayEntry['absences'] as $absence) {
                    $conflictDayMap[$absence['id']] = ($conflictDayMap[$absence['id']] ?? 0) + 1;
                }
            }
        }
        unset($dayEntry);

        $absenceRows = [];
        foreach ($formattedAbsences as $absence) {
            $absence['conflict_days'] = $conflictDayMap[$absence['id']] ?? 0;
            $absence['has_conflict'] = $absence['conflict_days'] > 0;
            $absenceRows[] = $absence;
        }

        return [
            array_values($criticalDays),
            $absenceRows,
            [
                'afastamentos_total' => count($absenceRows),
                'dias_com_afastamento' => $daysWithAbsence,
                'dias_com_sobreposicao' => count($criticalDays),
                'funcionarios_afastados' => collect($absenceRows)->pluck('funcionario_id')->filter()->unique()->count(),
                'afastamentos_em_aberto' => collect($absenceRows)->filter(fn (array $afastamento): bool => $afastamento['end_date'] === null)->count(),
            ],
        ];
    }

    private function formatAbsenceForCalendar(RhAfastamento $afastamento): array
    {
        $funcionario = $afastamento->funcionario;

        return [
            'id' => $afastamento->id,
            'funcionario_id' => $afastamento->funcionario_id,
            'funcionario_short' => $funcionario?->short_name ?: $funcionario?->name ?: 'Nao informado',
            'funcionario_name' => $funcionario?->name ?: 'Nao informado',
            'matricula' => $funcionario?->matricula ?: 'N/D',
            'cargo' => $funcionario?->cargo?->name ?: 'Sem cargo',
            'sector' => $funcionario?->sector ?: 'Sem setor',
            'reason' => $afastamento->reason,
            'start_date' => $afastamento->start_date?->toDateString(),
            'end_date' => $afastamento->end_date?->toDateString(),
            'start_label' => $afastamento->start_date?->format('d/m/Y'),
            'end_label' => $afastamento->end_date?->format('d/m/Y') ?: 'Em aberto',
            'status_label' => $afastamento->statusLabel(),
            'status_tone' => $afastamento->statusTone(),
            'is_active' => $afastamento->is_active,
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>>|array<int, array<string, mixed>> $employeeMap
     */
    private function formatLegacyAbsenceForCalendar(array $afastamento, Collection|array $employeeMap): ?array
    {
        $legacyId = (int) ($afastamento['id'] ?? 0);
        $funcionarioId = (int) ($afastamento['funcionario_id'] ?? 0);
        $employee = null;

        if ($employeeMap instanceof Collection) {
            $employee = $employeeMap->get($funcionarioId);
        } else {
            $employee = $employeeMap[$funcionarioId] ?? null;
        }

        $startDate = $this->parseLegacyDate($afastamento['data_inicio'] ?? null);
        $endDate = $this->parseLegacyDate($afastamento['data_fim'] ?? null);
        $active = (int) ($afastamento['ativo'] ?? 1) === 1;

        if ($legacyId <= 0 || $funcionarioId <= 0 || $startDate === null) {
            return null;
        }

        $motivo = trim((string) ($afastamento['motivo'] ?? ''));
        $observacoes = trim((string) ($afastamento['observacoes'] ?? ''));
        $details = trim(implode(' | ', array_filter([$motivo, $observacoes])));

        return [
            'id' => $legacyId,
            'funcionario_id' => $funcionarioId,
            'funcionario_short' => trim((string) ($employee['nome_simplificado'] ?? '')) ?: trim((string) ($employee['nome'] ?? '')) ?: 'Nao informado',
            'funcionario_name' => trim((string) ($employee['nome'] ?? '')) ?: 'Nao informado',
            'matricula' => trim((string) ($employee['legacy_key'] ?? sprintf('LEG-%04d', $funcionarioId))),
            'cargo' => trim((string) ($employee['cargo'] ?? '')) ?: 'Sem cargo',
            'sector' => trim((string) ($employee['setor'] ?? '')) ?: 'Sem setor',
            'reason' => trim((string) ($afastamento['tipo'] ?? 'Afastamento')),
            'notes' => $details,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate?->toDateString(),
            'start_label' => $startDate->format('d/m/Y'),
            'end_label' => $endDate?->format('d/m/Y') ?: 'Em aberto',
            'status_label' => $active
                ? ($startDate->greaterThan(now()) ? 'Agendado' : 'Em vigor')
                : 'Inativo',
            'status_tone' => $active ? ($startDate->greaterThan(now()) ? 'warn' : 'good') : 'warn',
            'is_active' => $active,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadLegacyHolidayRows(?int $year = null, ?int $month = null): array
    {
        if (! config('grom_legacy.enabled')) {
            return [];
        }

        $dbPath = (string) config('grom_legacy.analise_db_path');

        if ($dbPath === '' || ! File::exists($dbPath)) {
            return [];
        }

        $legacy = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $legacy->enableExceptions(true);
        $legacy->busyTimeout(5000);

        try {
            if (! $this->legacyTableExists($legacy, 'feriados')) {
                return [];
            }

            $sql = 'SELECT id, data, descricao, tipo FROM feriados';
            $params = [];

            if ($year !== null && $month !== null) {
                $sql .= ' WHERE substr(data, 1, 7) = :ym';
                $params[':ym'] = sprintf('%04d-%02d', $year, $month);
            }

            $sql .= ' ORDER BY data ASC, id ASC';

            $statement = $legacy->prepare($sql);
            foreach ($params as $name => $value) {
                $statement->bindValue($name, $value, SQLITE3_TEXT);
            }

            $result = $statement->execute();
            $rows = [];

            while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
                $date = $this->parseLegacyDate($row['data'] ?? null);
                if ($date === null) {
                    continue;
                }

                $rows[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'date' => $date->toDateString(),
                    'date_label' => $date->format('d/m/Y'),
                    'descricao' => trim((string) ($row['descricao'] ?? '')),
                    'tipo' => trim((string) ($row['tipo'] ?? '')),
                ];
            }

            return $rows;
        } finally {
            $legacy->close();
        }
    }

    private function legacyTableExists(SQLite3 $legacy, string $table): bool
    {
        $statement = $legacy->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
        $statement->bindValue(':table', $table, SQLITE3_TEXT);
        $result = $statement->execute();

        return (bool) $result?->fetchArray(SQLITE3_NUM);
    }

    private function parseLegacyDate(mixed $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '//' || $value === '00/00/0000') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd/m/y', 'Y/m/d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $date;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeHolidayScope(string $tipo): string
    {
        $normalized = (string) str($tipo)->ascii()->lower();

        return match (true) {
            str_contains($normalized, 'estadual') => 'estadual',
            str_contains($normalized, 'municipal') => 'municipal',
            str_contains($normalized, 'facultativo') => 'facultativo',
            default => 'nacional',
        };
    }

    /**
     * @param array{
     *     holiday_id?: string|null,
     *     holiday_date: string,
     *     name: string,
     *     scope: string,
     *     notes?: string|null,
     *     is_active?: bool|int|string|null
     * } $data
     */
    private function upsertHolidayByDate(array $data): RhHoliday
    {
        $holidayDate = Carbon::parse($data['holiday_date'])->toDateString();
        $holiday = null;

        if (! empty($data['holiday_id'])) {
            $holiday = RhHoliday::query()->find($data['holiday_id']);
        }

        if (! $holiday) {
            $holiday = RhHoliday::query()->whereDate('holiday_date', $holidayDate)->first();
        }

        if (! $holiday) {
            $holiday = new RhHoliday();
        }

        $holiday->holiday_date = $holidayDate;
        $holiday->name = trim($data['name']);
        $holiday->scope = trim($data['scope']) ?: 'nacional';
        $holiday->notes = $data['notes'] ?? null;
        $holiday->is_active = (bool) ($data['is_active'] ?? false);
        $holiday->save();

        return $holiday;
    }
}
