<?php

namespace App\Services\Rh;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SQLite3;

class LegacyFuncionariosReader
{
    public function snapshot(): array
    {
        if (! config('grom_legacy.enabled')) {
            return $this->emptySnapshot('A leitura da base legada de funcionarios esta desabilitada neste ambiente.');
        }

        $dbPath = (string) config('grom_legacy.analise_db_path');

        if ($dbPath === '' || ! File::exists($dbPath)) {
            return $this->emptySnapshot('Base legada nao encontrada no caminho configurado.');
        }

        $legacy = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $legacy->enableExceptions(true);
        $legacy->busyTimeout(5000);

        try {
            $cargoRows = $this->loadRows($legacy, 'SELECT id, cargo, setor, categoria, ativo, observacoes FROM cargos ORDER BY id');
            $funcionarioRows = $this->loadRows($legacy, 'SELECT * FROM funcionarios ORDER BY ativo DESC, nome ASC');
            $afastamentoRows = $this->loadRows($legacy, 'SELECT * FROM afastamentos ORDER BY data_inicio ASC, id ASC');

            $cargoMap = [];
            foreach ($cargoRows as $row) {
                $cargoMap[$this->normalizeKey((string) ($row['cargo'] ?? ''))] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'cargo' => trim((string) ($row['cargo'] ?? '')),
                    'setor' => trim((string) ($row['setor'] ?? '')),
                    'categoria' => trim((string) ($row['categoria'] ?? '')),
                    'ativo' => (int) ($row['ativo'] ?? 0) === 1,
                    'observacoes' => trim((string) ($row['observacoes'] ?? '')),
                ];
            }

            $afastamentosMap = $this->buildCurrentAfastamentoMap($afastamentoRows);
            $employees = [];

            foreach ($funcionarioRows as $row) {
                $legacyId = (int) ($row['id'] ?? 0);
                $cargoKey = $this->normalizeKey((string) ($row['cargo'] ?? ''));
                $cargo = $cargoMap[$cargoKey] ?? null;
                $currentAfastamento = $afastamentosMap[$legacyId] ?? null;

                $employees[] = [
                    'legacy_id' => $legacyId,
                    'legacy_key' => sprintf('LEG-%04d', $legacyId),
                    'nome' => trim((string) ($row['nome'] ?? '')),
                    'nome_simplificado' => trim((string) ($row['nome_simplificado'] ?? '')),
                    'cargo' => trim((string) ($row['cargo'] ?? '')),
                    'setor' => trim((string) ($row['setor'] ?? '')),
                    'telefone' => trim((string) ($row['telefone'] ?? '')),
                    'rg' => trim((string) ($row['rg'] ?? '')),
                    'cpf' => trim((string) ($row['cpf'] ?? '')),
                    'data_aniversario' => $this->parseLegacyDate($row['data_aniversario'] ?? null),
                    'data_designacao' => $this->parseLegacyDate($row['data_designacao'] ?? null),
                    'data_remocao' => $this->parseLegacyDate($row['data_remocao'] ?? null),
                    'data_afastamento' => $this->parseLegacyDate($row['data_afastamento'] ?? null),
                    'concorre_escala' => (int) ($row['concorre_escala'] ?? 0) === 1,
                    'ativo' => (int) ($row['ativo'] ?? 0) === 1,
                    'observacoes' => trim((string) ($row['observacoes'] ?? '')),
                    'cargo_legacy_id' => $cargo['id'] ?? null,
                    'cargo_categoria' => $cargo['categoria'] ?? null,
                    'cargo_ativo' => $cargo['ativo'] ?? null,
                    'current_afastamento' => $currentAfastamento,
                ];
            }

            return [
                'available' => true,
                'source_name' => basename($dbPath),
                'cargos' => $cargoRows,
                'employees' => $employees,
                'afastamentos' => $afastamentoRows,
                'summary' => [
                    'total' => count($employees),
                    'ativos' => collect($employees)->where('ativo', true)->count(),
                    'concorrem_escala' => collect($employees)->where('concorre_escala', true)->count(),
                    'em_afastamento' => collect($employees)->whereNotNull('current_afastamento')->count(),
                    'cargos_total' => count($cargoRows),
                    'afastamentos_total' => count($afastamentoRows),
                    'afastamentos_em_vigor' => count($afastamentosMap),
                ],
                'warnings' => [],
            ];
        } finally {
            $legacy->close();
        }
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

    private function parseLegacyDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '//' || $value === '00/00/0000') {
            return null;
        }

        foreach (['d/m/Y', 'd/m/y', 'Y-m-d'] as $format) {
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

    private function normalizeKey(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-');
    }

    private function emptySnapshot(string $warning): array
    {
        return [
            'available' => false,
            'source_name' => null,
            'cargos' => [],
            'employees' => [],
            'afastamentos' => [],
            'summary' => [
                'total' => 0,
                'ativos' => 0,
                'concorrem_escala' => 0,
                'em_afastamento' => 0,
                'cargos_total' => 0,
                'afastamentos_total' => 0,
                'afastamentos_em_vigor' => 0,
            ],
            'warnings' => [$warning],
        ];
    }
}
