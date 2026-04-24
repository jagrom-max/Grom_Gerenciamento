<?php

namespace App\Services\Rh;

use App\Models\RhAfastamento;
use App\Models\RhCargo;
use App\Models\RhFuncionario;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SQLite3;

class LegacyFuncionariosSyncService
{
    public function sync(): array
    {
        if (! config('grom_legacy.enabled')) {
            return $this->emptyResult('A sincronizacao legada de funcionarios esta desabilitada neste ambiente.');
        }

        $dbPath = (string) config('grom_legacy.analise_db_path');

        if ($dbPath === '' || ! File::exists($dbPath)) {
            return $this->emptyResult('Base legada nao encontrada no caminho configurado.');
        }

        $legacy = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $legacy->enableExceptions(true);
        $legacy->busyTimeout(5000);

        try {
            $cargoRows = $this->loadRows($legacy, 'SELECT id, cargo, setor, categoria, ativo, observacoes FROM cargos ORDER BY id');
            $funcionarioRows = $this->loadRows($legacy, 'SELECT * FROM funcionarios ORDER BY ativo DESC, nome ASC');
            $afastamentoRows = $this->loadRows($legacy, 'SELECT * FROM afastamentos ORDER BY data_inicio ASC, id ASC');

            $cargoMap = [];
            $cargoCreated = 0;
            $cargoUpdated = 0;

            foreach ($cargoRows as $row) {
                $legacyId = (int) ($row['id'] ?? 0);
                $code = sprintf('LEG-%03d', $legacyId);
                $payload = [
                    'name' => trim((string) ($row['cargo'] ?? '')) ?: 'Cargo legado',
                    'description' => $this->buildCargoDescription($row),
                    'is_active' => (int) ($row['ativo'] ?? 0) === 1,
                ];

                $cargo = RhCargo::query()->updateOrCreate(['code' => $code], $payload);
                $cargoMap[$this->normalizeKey((string) ($row['cargo'] ?? ''))] = $cargo;

                if ($cargo->wasRecentlyCreated) {
                    $cargoCreated++;
                } else {
                    $cargoUpdated++;
                }
            }

            $funcionarioMap = [];
            $funcionarioCreated = 0;
            $funcionarioUpdated = 0;

            foreach ($funcionarioRows as $row) {
                $legacyId = (int) ($row['id'] ?? 0);
                $legacyCargoKey = $this->normalizeKey((string) ($row['cargo'] ?? ''));
                $cargo = $cargoMap[$legacyCargoKey] ?? $this->resolveFallbackCargo($row, $cargoMap);

                if ($cargo === null) {
                    continue;
                }

                $matricula = sprintf('LEG-%04d', $legacyId);
                $shortName = trim((string) ($row['nome_simplificado'] ?? ''));
                $dataDesignacao = $this->parseLegacyDate($row['data_designacao'] ?? null);
                $dataAdmissao = $this->parseLegacyDate($row['data_admissao'] ?? null);
                $dataRemocao = $this->parseLegacyDate($row['data_remocao'] ?? null);
                $dataNascimento = $this->parseLegacyDate($row['data_aniversario'] ?? null);
                $admissionDate = $dataDesignacao ?? $dataAdmissao ?? Carbon::today()->toDateString();

                $payload = [
                    'legacy_id' => $legacyId,
                    'name' => $this->preferredName($row['nome'] ?? null, $row['nome_simplificado'] ?? null),
                    'short_name' => $shortName !== '' ? $shortName : null,
                    'email' => null,
                    'cargo_id' => $cargo->id,
                    'sector' => $this->cleanNullable($row['setor'] ?? null),
                    'phone' => $this->cleanNullable($row['telefone'] ?? null),
                    'rg' => $this->cleanNullable($row['rg'] ?? null),
                    'cpf' => $this->cleanNullable($row['cpf'] ?? null),
                    'birth_date' => $dataNascimento,
                    'admission_date' => $admissionDate,
                    'designation_date' => $dataDesignacao,
                    'departure_date' => $dataRemocao,
                    'removal_date' => $dataRemocao,
                    'concorre_escala' => (int) ($row['concorre_escala'] ?? 0) === 1,
                    'is_active' => (int) ($row['ativo'] ?? 0) === 1,
                    'notes' => $this->buildFuncionarioNotes($row),
                ];

                $funcionario = RhFuncionario::query()->updateOrCreate(['matricula' => $matricula], $payload);
                $funcionarioMap[$legacyId] = $funcionario;

                if ($funcionario->wasRecentlyCreated) {
                    $funcionarioCreated++;
                } else {
                    $funcionarioUpdated++;
                }
            }

            $afastamentoCreated = 0;
            $afastamentoUpdated = 0;

            foreach ($afastamentoRows as $row) {
                $legacyFuncionarioId = (int) ($row['funcionario_id'] ?? 0);
                $funcionario = $funcionarioMap[$legacyFuncionarioId] ?? null;

                if ($funcionario === null) {
                    continue;
                }

                $startDate = $this->parseLegacyDate($row['data_inicio'] ?? null);
                $endDate = $this->parseLegacyDate($row['data_fim'] ?? null);

                if ($startDate === null) {
                    continue;
                }

                $reason = trim((string) ($row['tipo'] ?? 'Afastamento'));
                $payload = [
                    'funcionario_id' => $funcionario->id,
                    'reason' => $reason,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_active' => (int) ($row['ativo'] ?? 0) === 1,
                    'notes' => $this->buildAfastamentoNotes($row),
                ];

                $afastamento = RhAfastamento::query()->updateOrCreate(
                    [
                        'funcionario_id' => $funcionario->id,
                        'reason' => $reason,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                    $payload,
                );

                if ($afastamento->wasRecentlyCreated) {
                    $afastamentoCreated++;
                } else {
                    $afastamentoUpdated++;
                }
            }

            return [
                'synced' => true,
                'source_name' => basename($dbPath),
                'cargos' => [
                    'total' => count($cargoRows),
                    'created' => $cargoCreated,
                    'updated' => $cargoUpdated,
                ],
                'funcionarios' => [
                    'total' => count($funcionarioRows),
                    'created' => $funcionarioCreated,
                    'updated' => $funcionarioUpdated,
                ],
                'afastamentos' => [
                    'total' => count($afastamentoRows),
                    'created' => $afastamentoCreated,
                    'updated' => $afastamentoUpdated,
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

    private function resolveFallbackCargo(array $row, array $cargoMap): ?RhCargo
    {
        $cargoName = $this->normalizeKey((string) ($row['cargo'] ?? ''));

        if ($cargoName !== '' && isset($cargoMap[$cargoName])) {
            return $cargoMap[$cargoName];
        }

        $legacyCode = sprintf('LEG-%03d', (int) ($row['id'] ?? 0));

        return RhCargo::query()->updateOrCreate(
            ['code' => $legacyCode],
            [
                'name' => trim((string) ($row['cargo'] ?? 'Cargo legado')) ?: 'Cargo legado',
                'description' => 'Cargo criado a partir da base legada Python.',
                'is_active' => (int) ($row['ativo'] ?? 0) === 1,
            ],
        );
    }

    private function buildCargoDescription(array $row): ?string
    {
        $parts = [
            'Origem Python',
            'id=' . ($row['id'] ?? 'N/D'),
            'setor=' . trim((string) ($row['setor'] ?? '')),
            'categoria=' . trim((string) ($row['categoria'] ?? '')),
        ];

        $observacoes = trim((string) ($row['observacoes'] ?? ''));

        if ($observacoes !== '') {
            $parts[] = 'obs=' . $observacoes;
        }

        return implode(' | ', $parts);
    }

    private function buildFuncionarioNotes(array $row): string
    {
        $parts = [
            'Origem Python',
            'id=' . ($row['id'] ?? 'N/D'),
            'nome=' . trim((string) ($row['nome'] ?? '')),
            'cargo=' . trim((string) ($row['cargo'] ?? '')),
            'setor=' . trim((string) ($row['setor'] ?? '')),
            'concorre_escala=' . (int) ($row['concorre_escala'] ?? 0),
        ];

        foreach (['data_aniversario', 'data_designacao', 'data_remocao', 'data_afastamento'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value !== '' && $value !== '//') {
                $parts[] = $field . '=' . $value;
            }
        }

        $observacoes = trim((string) ($row['observacoes'] ?? ''));

        if ($observacoes !== '') {
            $parts[] = 'obs=' . $observacoes;
        }

        return implode(' | ', $parts);
    }

    private function buildAfastamentoNotes(array $row): string
    {
        $parts = [
            'Origem Python',
            'id=' . ($row['id'] ?? 'N/D'),
            'tipo=' . trim((string) ($row['tipo'] ?? '')),
        ];

        $motivo = trim((string) ($row['motivo'] ?? ''));
        $observacoes = trim((string) ($row['observacoes'] ?? ''));

        if ($motivo !== '') {
            $parts[] = 'motivo=' . $motivo;
        }

        if ($observacoes !== '') {
            $parts[] = 'obs=' . $observacoes;
        }

        return implode(' | ', $parts);
    }

    private function preferredName(?string $fullName, ?string $shortName): string
    {
        $fullName = trim((string) $fullName);
        $shortName = trim((string) $shortName);

        return $fullName !== '' ? $fullName : ($shortName !== '' ? $shortName : 'Sem nome');
    }

    private function parseLegacyDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '//' || $value === '00/00/0000') {
            return null;
        }

        foreach (['d/m/Y', 'd/m/y', 'Y-m-d', 'd/m'] as $format) {
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

    private function emptyResult(string $warning): array
    {
        return [
            'synced' => false,
            'source_name' => null,
            'cargos' => ['total' => 0, 'created' => 0, 'updated' => 0],
            'funcionarios' => ['total' => 0, 'created' => 0, 'updated' => 0],
            'afastamentos' => ['total' => 0, 'created' => 0, 'updated' => 0],
            'warnings' => [$warning],
        ];
    }

    private function cleanNullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
