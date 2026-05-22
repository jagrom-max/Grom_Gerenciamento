<?php

namespace Database\Seeders;

use App\Models\RhHoliday;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use SQLite3;

class GromRhHolidaySeeder extends Seeder
{
    public function run(): void
    {
        if ($this->legacyAvailable()) {
            $this->importLegacyHolidays();
        }

        foreach ([$this->currentYear(), $this->currentYear() + 1] as $year) {
            foreach ($this->holidayDefinitions($year) as $holiday) {
                $this->upsertHoliday($holiday['date'], [
                    'name' => $holiday['name'],
                    'scope' => $holiday['scope'],
                    'is_active' => true,
                    'notes' => $holiday['notes'],
                ]);
            }
        }
    }

    private function legacyAvailable(): bool
    {
        if (! config('grom_legacy.enabled')) {
            return false;
        }

        $dbPath = (string) config('grom_legacy.analise_db_path');

        return $dbPath !== '' && File::exists($dbPath);
    }

    private function importLegacyHolidays(): void
    {
        $dbPath = (string) config('grom_legacy.analise_db_path');
        $legacy = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $legacy->enableExceptions(true);
        $legacy->busyTimeout(5000);

        try {
            $result = $legacy->query('SELECT id, data, descricao, tipo FROM feriados ORDER BY data ASC, id ASC');

            while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
                $date = $this->parseLegacyDate($row['data'] ?? null);

                if ($date === null) {
                    continue;
                }

                $this->upsertHoliday($date, [
                    'name' => trim((string) ($row['descricao'] ?? 'Feriado legado')),
                    'scope' => $this->normalizeScope((string) ($row['tipo'] ?? '')),
                    'is_active' => true,
                    'notes' => trim(sprintf(
                        'Importado da base legada Python | id=%s | tipo=%s',
                        (string) ($row['id'] ?? 'N/D'),
                        trim((string) ($row['tipo'] ?? ''))
                    )),
                ]);
            }
        } finally {
            $legacy->close();
        }
    }

    /**
     * Registros antigos no SQLite local podem existir com horario anexado
     * ao campo date. O whereDate garante idempotencia mesmo nesse formato.
     *
     * @param array{name: string, scope: string, is_active: bool, notes: string|null} $attributes
     */
    private function upsertHoliday(Carbon $date, array $attributes): void
    {
        $record = RhHoliday::query()
            ->whereDate('holiday_date', $date->toDateString())
            ->first();

        if ($record === null) {
            $record = new RhHoliday();
        }

        $record->fill([
            'holiday_date' => $date->toDateString(),
            ...$attributes,
        ]);

        $record->save();
    }

    private function normalizeScope(string $tipo): string
    {
        $normalized = (string) str($tipo)->ascii()->lower();

        return match (true) {
            str_contains($normalized, 'estadual') => 'estadual',
            str_contains($normalized, 'municipal') => 'municipal',
            str_contains($normalized, 'facultativo') => 'facultativo',
            default => 'nacional',
        };
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

    private function currentYear(): int
    {
        return (int) now()->format('Y');
    }

    /**
     * @return array<int, array{name: string, date: Carbon, scope: string, notes: string|null}>
     */
    private function holidayDefinitions(int $year): array
    {
        $easter = $this->easterSunday($year);

        return [
            ['name' => 'Confraternização Universal', 'date' => Carbon::create($year, 1, 1), 'scope' => 'nacional', 'notes' => null],
            ['name' => 'Paixão de Cristo', 'date' => $easter->copy()->subDays(2), 'scope' => 'nacional', 'notes' => 'Feriado móvel do calendário civil.'],
            ['name' => 'Tiradentes', 'date' => Carbon::create($year, 4, 21), 'scope' => 'nacional', 'notes' => null],
            ['name' => 'Dia do Trabalho', 'date' => Carbon::create($year, 5, 1), 'scope' => 'nacional', 'notes' => null],
            ['name' => 'Corpus Christi', 'date' => $easter->copy()->addDays(60), 'scope' => 'municipal', 'notes' => 'Feriado movel observado no municipio de Rio Claro/SP.'],
            ['name' => 'Aniversário de Rio Claro / São João Batista', 'date' => Carbon::create($year, 6, 24), 'scope' => 'municipal', 'notes' => 'Feriado municipal de Rio Claro/SP.'],
            ['name' => 'Independência do Brasil', 'date' => Carbon::create($year, 9, 7), 'scope' => 'nacional', 'notes' => null],
            ['name' => 'Nossa Senhora Aparecida', 'date' => Carbon::create($year, 10, 12), 'scope' => 'nacional', 'notes' => null],
            ['name' => 'Finados', 'date' => Carbon::create($year, 11, 2), 'scope' => 'nacional', 'notes' => null],
            ['name' => 'Proclamação da República', 'date' => Carbon::create($year, 11, 15), 'scope' => 'nacional', 'notes' => null],
            ['name' => 'Natal', 'date' => Carbon::create($year, 12, 25), 'scope' => 'nacional', 'notes' => null],
        ];
    }

    private function easterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day);
    }
}
