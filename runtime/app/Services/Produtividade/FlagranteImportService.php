<?php

namespace App\Services\Produtividade;

use App\Enums\ImportItemStatus;
use App\Enums\LavradoUnidade;
use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\ProductivityBoletim;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SimpleXMLElement;
use ZipArchive;

class FlagranteImportService
{
    private const HEADER_ALIASES = [
        'source_process_key' => ['SOURCEPROCESSKEY', 'CHAVEPROCESSO', 'IDPROCESSO'],
        'spj' => ['SPJ', 'SPJREF', 'NSPJ', 'NUMEROSPJ'],
        'naturezas' => ['NATUREZAS', 'NATUREZA', 'NATUREZASPROCESSUAIS'],
        'data_fato' => ['DATAFATO', 'DATAOCORRENCIA', 'DATAOCORRENCIAFATO', 'DATA'],
        'reference_year' => ['ANO', 'ANOREFERENCIA'],
        'reference_month' => ['MES', 'MESREFERENCIA'],
        'status' => ['STATUS', 'STATUSORIGEM', 'SITUACAO'],
        'flagrante' => ['FLAGRANTE'],
        'num_ip' => ['NUMIP', 'NIP', 'NUMEROIP', 'IP'],
        'num_ipe' => ['NUMIPE', 'NIPE', 'NUMEROIPE', 'IPE'],
        'num_cnj' => ['NUMCNJ', 'NCNJ', 'CNJ', 'NUMEROCNJ'],
        'cartorio_designado' => ['CARTORIODESIGNADO', 'CARTORIO', 'CARTORIOLABEL', 'CARTORIOIP'],
        'lavrado_unidade' => ['LAVRADOUNIDADE', 'LAVRADO', 'UNIDADELAVRADORA', 'UNIDADE'],
        'mpu_numero' => ['MPUNUMERO', 'MPU'],
        'mpu_decisao' => ['MPUDECISAO', 'SITUACAOMPU', 'MPUSITUACAO', 'MPUDEFERIMENTO', 'MPUDEFERIDA'],
        'despacho_fundamentado' => ['DESPACHOFUNDAMENTADO', 'DESPACHADOFUNDAMENTADO', 'BOLETIMDESPACHADOFUNDAMENTADO'],
        'encaminhado_outra_unidade' => ['ENCAMINHADOOUTRAUNIDADE', 'ENCAMINHADOPARA', 'REMETIDOOUTRAUNIDADE', 'ENCAMINHADO'],
        'encaminhado_para_unidade' => ['ENCAMINHADOPARAUNIDADE', 'UNIDADEDESTINATARIA', 'DESTINOUNIDADE'],
    ];

    public function importUploadedFile(
        UploadedFile $file,
        User $actor,
        ?Cartorio $fallbackCartorio = null,
        array $constraints = [],
    ): array
    {
        $parsed = $this->parseUploadedFile($file);
        return $this->importStructuredRows($parsed['rows'], $actor, [
            'source_name' => trim($file->getClientOriginalName()) ?: 'consolidacao_externa',
            'source_type' => $parsed['source_type'],
            'source_hash' => hash_file('sha256', (string) $file->getRealPath()) ?: null,
            'sheet_name' => $parsed['sheet_name'],
            'header_row' => $parsed['header_row'],
            'total_rows' => count($parsed['rows']),
            ...$constraints,
        ], $fallbackCartorio);
    }

    public function importStructuredRows(array $rows, User $actor, array $sourceMeta, ?Cartorio $fallbackCartorio = null): array
    {
        $cartorios = Cartorio::query()->orderBy('number')->get();
        $allowedCartorioIds = array_values(array_filter(array_map(
            static fn (mixed $value): string => (string) $value,
            $sourceMeta['allowed_cartorio_ids'] ?? [],
        )));
        $allowedLavradoUnidades = array_values(array_filter(array_map(
            static fn (mixed $value): string => (string) $value,
            $sourceMeta['allowed_lavrado_unidades'] ?? [],
        )));

        $rowsStaged = 0;
        $rowsUpdated = 0;
        $rowsSkipped = 0;
        $errorCount = 0;
        $bosTotal = 0;
        $periodStart = null;
        $periodEnd = null;
        $unresolvedHints = [];
        $preparedRows = [];

        foreach (array_values($rows) as $lineNumber => $row) {
            try {
                $normalized = $this->normalizeRow($row, $lineNumber, $cartorios, $fallbackCartorio);
            } catch (InvalidArgumentException) {
                $errorCount++;
                continue;
            }

            if (! $normalized['reference_year'] || ! $normalized['reference_month']) {
                $rowsSkipped++;
                continue;
            }

            if (! $normalized['spj'] && ! $normalized['data_fato']) {
                $rowsSkipped++;
                continue;
            }

            if ($allowedCartorioIds !== [] && $normalized['cartorio_id'] && ! in_array((string) $normalized['cartorio_id'], $allowedCartorioIds, true)) {
                $rowsSkipped++;
                continue;
            }

            if ($allowedLavradoUnidades !== [] && $normalized['cartorio_id']) {
                $unidadeValue = $normalized['lavrado_unidade'] instanceof LavradoUnidade
                    ? $normalized['lavrado_unidade']->value
                    : null;

                if ($unidadeValue !== null && ! in_array($unidadeValue, $allowedLavradoUnidades, true)) {
                    $rowsSkipped++;
                    continue;
                }
            }

            if (! $normalized['cartorio_id'] && $normalized['cartorio_hint']) {
                $unresolvedHints[] = $normalized['cartorio_hint'];
            }

            if (! $normalized['is_flagrante']) {
                $rowsSkipped++;
            }

            if ($normalized['data_fato']) {
                $periodStart = $periodStart
                    ? min($periodStart, $normalized['data_fato'])
                    : $normalized['data_fato'];
                $periodEnd = $periodEnd
                    ? max($periodEnd, $normalized['data_fato'])
                    : $normalized['data_fato'];
            }

            $preparedRows[] = $normalized;
        }

        $batch = DB::transaction(function () use (
            $actor,
            $sourceMeta,
            $preparedRows,
            $periodStart,
            $periodEnd,
            $rowsSkipped,
            $errorCount,
            &$rowsStaged,
            &$rowsUpdated,
            &$bosTotal,
            $unresolvedHints,
        ): ImportBatch {
            $notes = trim(implode(' ', array_filter([
                $this->cleanNullable($sourceMeta['notes_prefix'] ?? null),
                $this->buildBatchNotes($unresolvedHints),
            ])));

            $batch = ImportBatch::query()->create([
                'source_name' => $sourceMeta['source_name'] ?? 'consolidacao_externa',
                'source_type' => $sourceMeta['source_type'] ?? 'EXTERNAL',
                'source_hash' => $sourceMeta['source_hash'] ?? null,
                'sheet_name' => $sourceMeta['sheet_name'] ?? null,
                'header_row' => $sourceMeta['header_row'] ?? null,
                'source_period_start' => $periodStart,
                'source_period_end' => $periodEnd,
                'imported_by' => $actor->id,
                'imported_at' => now(),
                'processed_at' => now(),
                'total_rows' => (int) ($sourceMeta['total_rows'] ?? count($preparedRows)),
                'rows_staged' => 0,
                'rows_updated' => 0,
                'rows_skipped' => $rowsSkipped,
                'error_count' => $errorCount,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            foreach ($preparedRows as $row) {
                $boletim = null;

                if ($row['cartorio_id']) {
                    $boletim = $this->upsertBoletim($row, $batch, $actor);
                    $bosTotal++;
                }

                if (! $row['is_flagrante']) {
                    continue;
                }

                $rowsUpdated += $this->supersedePendingItems($row['source_process_key'], $actor, $batch->id);

                $importItem = ImportItem::query()->create([
                    'batch_id' => $batch->id,
                    'source_process_key' => $row['source_process_key'],
                    'cartorio_id' => $row['cartorio_id'],
                    'cartorio_hint' => $row['cartorio_hint'],
                    'reference_year' => $row['reference_year'],
                    'reference_month' => $row['reference_month'],
                    'spj' => $row['spj'],
                    'naturezas' => $row['naturezas'],
                    'num_ip' => $row['num_ip'],
                    'num_ipe' => $row['num_ipe'],
                    'num_cnj' => $row['num_cnj'],
                    'data_fato' => $row['data_fato'],
                    'status_origem' => 'Flagrante',
                    'lavrado_unidade' => $row['lavrado_unidade'],
                    'payload' => $row['payload'],
                    'import_status' => ImportItemStatus::Pending,
                ]);

                if ($boletim) {
                    $boletim->update([
                        'notes' => $this->pickText($boletim->notes, sprintf('Import item pendente vinculado: %s', $importItem->id)),
                    ]);
                }

                $rowsStaged++;
            }

            $batch->update([
                'rows_staged' => $rowsStaged,
                'rows_updated' => $rowsUpdated,
                'rows_skipped' => $rowsSkipped,
                'error_count' => $errorCount,
            ]);

            return $batch->fresh();
        });

        $lavradoRaw = array_key_exists('lavrado_unidade', $row)
            ? (string) $row['lavrado_unidade']
            : null;

        return [
            'batch' => $batch,
            'summary' => [
                'bos_total' => $bosTotal,
                'rows_staged' => $rowsStaged,
                'rows_updated' => $rowsUpdated,
                'rows_skipped' => $rowsSkipped,
                'error_count' => $errorCount,
            ],
        ];
    }

    private function parseUploadedFile(UploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());

        return match ($extension) {
            'csv', 'txt' => $this->parseCsvFile($file),
            'xlsx' => $this->parseXlsxFile($file),
            default => throw new InvalidArgumentException('Formato nao suportado. Use CSV, TXT ou XLSX.'),
        };
    }

    private function parseCsvFile(UploadedFile $file): array
    {
        $handle = fopen((string) $file->getRealPath(), 'rb');

        if (! $handle) {
            throw new InvalidArgumentException('Nao foi possivel abrir o arquivo informado.');
        }

        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = $this->detectDelimiter($this->stripBom((string) $firstLine));
        $header = fgetcsv($handle, 0, $delimiter);

        if (! is_array($header) || $header === []) {
            fclose($handle);
            throw new InvalidArgumentException('Cabecalho CSV ausente ou invalido.');
        }

        $mappedHeader = array_map(fn ($value) => $this->resolveHeaderAlias($this->stripBom((string) $value)), $header);
        if (! array_filter($mappedHeader)) {
            fclose($handle);
            throw new InvalidArgumentException('Nenhuma coluna reconhecida na consolidacao.');
        }

        $rows = [];
        try {
            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($data === [null] || $this->rowIsBlank($data)) {
                    continue;
                }

                $row = [];
                foreach ($mappedHeader as $index => $field) {
                    if (! $field) {
                        continue;
                    }
                    $row[$field] = $data[$index] ?? null;
                }

                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return [
            'source_type' => 'CSV',
            'sheet_name' => null,
            'header_row' => 1,
            'rows' => $rows,
        ];
    }

    private function parseXlsxFile(UploadedFile $file): array
    {
        $zip = new ZipArchive();
        if ($zip->open((string) $file->getRealPath()) !== true) {
            throw new InvalidArgumentException('Nao foi possivel abrir a planilha XLSX.');
        }

        try {
            $sharedStrings = $this->parseSharedStrings($zip);
            $dateStyles = $this->parseDateStyleIndexes($zip);
            [$sheetName, $sheetPath] = $this->resolveFirstWorksheet($zip);
            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                throw new InvalidArgumentException('Planilha XLSX sem aba legivel.');
            }

            $sheet = $this->safeSimpleXml($sheetXml);
            if (! $sheet instanceof SimpleXMLElement || ! isset($sheet->sheetData)) {
                throw new InvalidArgumentException('Conteudo XLSX invalido para importacao.');
            }

            $rows = [];
            $header = null;
            $mappedHeader = [];

            foreach ($sheet->sheetData->row as $rowNode) {
                $values = [];
                foreach ($rowNode->c as $cell) {
                    $index = $this->columnIndexFromReference((string) $cell['r']);
                    $values[$index] = $this->extractCellValue($cell, $sharedStrings, $dateStyles);
                }

                if ($values === []) {
                    continue;
                }

                ksort($values);
                $dense = $this->densifyRow($values);

                if ($header === null) {
                    $header = $dense;
                    $mappedHeader = array_map(fn ($value) => $this->resolveHeaderAlias($this->stripBom((string) $value)), $header);

                    if (! array_filter($mappedHeader)) {
                        throw new InvalidArgumentException('Nenhuma coluna reconhecida na planilha XLSX.');
                    }

                    continue;
                }

                if ($this->rowIsBlank($dense)) {
                    continue;
                }

                $row = [];
                foreach ($mappedHeader as $index => $field) {
                    if (! $field) {
                        continue;
                    }
                    $row[$field] = $dense[$index] ?? null;
                }

                $rows[] = $row;
            }
        } finally {
            $zip->close();
        }

        return [
            'source_type' => 'XLSX',
            'sheet_name' => $sheetName,
            'header_row' => 1,
            'rows' => $rows,
        ];
    }

    private function normalizeRow(array $row, int $lineNumber, Collection $cartorios, ?Cartorio $fallbackCartorio = null): array
    {
        $spj = $this->cleanNullable($row['spj'] ?? null);
        $numIp = $this->cleanNullable($row['num_ip'] ?? null);
        $numIpe = $this->cleanNullable($row['num_ipe'] ?? null);
        $numCnj = $this->cleanNullable($row['num_cnj'] ?? null);
        $cartorioHint = $this->cleanNullable($row['cartorio_designado'] ?? null);
        $dataFato = $this->normalizeDate($row['data_fato'] ?? null);
        $referenceYear = $this->normalizeInteger($row['reference_year'] ?? null) ?: ($dataFato ? (int) substr($dataFato, 0, 4) : null);
        $referenceMonth = $this->normalizeInteger($row['reference_month'] ?? null) ?: ($dataFato ? (int) substr($dataFato, 5, 2) : null);
        $cartorio = $this->resolveCartorio($cartorioHint, $cartorios) ?: $fallbackCartorio;
        $lavradoRaw = array_key_exists('lavrado_unidade', $row)
            ? (string) $row['lavrado_unidade']
            : null;

        return [
            'source_process_key' => $this->buildSourceProcessKey(
                $this->cleanNullable($row['source_process_key'] ?? null),
                $spj,
                $numIp,
                $numCnj,
                $lineNumber,
            ),
            'cartorio_id' => $cartorio?->id,
            'cartorio_hint' => $cartorioHint,
            'reference_year' => $referenceYear,
            'reference_month' => $referenceMonth,
            'spj' => $spj,
            'naturezas' => $this->cleanNullable($row['naturezas'] ?? null),
            'num_ip' => $numIp,
            'num_ipe' => $numIpe,
            'num_cnj' => $numCnj,
            'mpu_numero' => $this->cleanNullable($row['mpu_numero'] ?? null),
            'data_fato' => $dataFato,
            'lavrado_unidade' => $lavradoRaw !== null ? LavradoUnidade::fromLegacy($lavradoRaw) : null,
            'is_flagrante' => $this->detectFlagrante(
                $this->cleanNullable($row['status'] ?? null),
                $row['flagrante'] ?? null,
            ),
            'mpu_decisao' => $this->normalizeMpuDecisao($row['mpu_decisao'] ?? null),
            'despacho_fundamentado' => $this->normalizeBooleanLike($row['despacho_fundamentado'] ?? null),
            'encaminhado_outra_unidade' => $this->normalizeBooleanLike($row['encaminhado_outra_unidade'] ?? null),
            'encaminhado_para_unidade' => $this->cleanNullable($row['encaminhado_para_unidade'] ?? null),
            'payload' => [
                'source_line' => $lineNumber,
                'raw' => $row,
            ],
        ];
    }

    private function supersedePendingItems(string $sourceProcessKey, User $actor, string $batchId): int
    {
        $pendingItems = ImportItem::query()
            ->where('source_process_key', $sourceProcessKey)
            ->where('import_status', ImportItemStatus::Pending->value)
            ->get();

        foreach ($pendingItems as $item) {
            $item->update([
                'import_status' => ImportItemStatus::Rejected,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'rejected_reason' => sprintf('Substituido por consolidacao mais recente no lote %s.', $batchId),
            ]);
        }

        return $pendingItems->count();
    }

    private function detectFlagrante(?string $status, mixed $flagrante): bool
    {
        $statusFolded = $this->normalizeToken((string) $status);
        if (str_contains($statusFolded, 'FLAGRANTE')) {
            return true;
        }

        return in_array($this->normalizeToken((string) $flagrante), ['1', 'S', 'SIM', 'TRUE', 'VERDADEIRO', 'X'], true);
    }

    private function upsertBoletim(array $row, ImportBatch $batch, User $actor): ProductivityBoletim
    {
        $attributes = [
            'cartorio_id' => $row['cartorio_id'],
            'import_batch_id' => $batch->id,
            'reference_year' => $row['reference_year'],
            'reference_month' => $row['reference_month'],
            'data_fato' => $row['data_fato'],
            'spj' => $row['spj'],
            'naturezas' => $row['naturezas'],
            'lavrado_unidade' => $row['lavrado_unidade'] ?? LavradoUnidade::OutrasUnidades,
            'is_flagrante' => (bool) $row['is_flagrante'],
            'mpu_numero' => $row['mpu_numero'],
            'mpu_decisao' => $row['mpu_decisao'],
            'despacho_fundamentado' => (bool) ($row['despacho_fundamentado'] ?? false),
            'encaminhado_outra_unidade' => (bool) ($row['encaminhado_outra_unidade'] ?? false),
            'encaminhado_para_unidade' => $row['encaminhado_para_unidade'] ?? null,
            'num_ip' => $row['num_ip'],
            'num_ipe' => $row['num_ipe'],
            'num_cnj' => $row['num_cnj'],
            'is_active' => true,
            'imported_by' => $actor->id,
            'notes' => null,
        ];

        if ($row['spj']) {
            $existing = $this->findExistingBoletimBySpj($row['spj'], $row['cartorio_id']);

            if ($existing) {
                $existing->update($this->buildMergedBoletimAttributes($existing, $attributes));

                $this->deactivateDuplicateBoletinsBySpj($existing, $row['spj'], $batch, $actor);

                return $existing->fresh();
            }

            return ProductivityBoletim::query()->create($attributes);
        }

        $existingByFallback = $this->findExistingBoletimWithoutSpj($row);
        if ($existingByFallback) {
            $existingByFallback->update($this->buildMergedBoletimAttributes($existingByFallback, $attributes));

            return $existingByFallback->fresh();
        }

        return ProductivityBoletim::query()->create($attributes);
    }

    private function buildMergedBoletimAttributes(ProductivityBoletim $existing, array $incoming): array
    {
        return [
            'cartorio_id' => $incoming['cartorio_id'] ?? $existing->cartorio_id,
            'import_batch_id' => $incoming['import_batch_id'] ?? $existing->import_batch_id,
            'reference_year' => $incoming['reference_year'] ?? $existing->reference_year,
            'reference_month' => $incoming['reference_month'] ?? $existing->reference_month,
            'data_fato' => $incoming['data_fato'] ?? $existing->data_fato,
            'spj' => $this->preferIncomingText($incoming['spj'] ?? null, $existing->spj),
            'naturezas' => $this->preferIncomingText($incoming['naturezas'] ?? null, $existing->naturezas),
            'lavrado_unidade' => $incoming['lavrado_unidade'] ?? $existing->lavrado_unidade,
            'is_flagrante' => (bool) ($incoming['is_flagrante'] ?? $existing->is_flagrante),
            'mpu_numero' => $this->preferIncomingText($incoming['mpu_numero'] ?? null, $existing->mpu_numero),
            'mpu_decisao' => $this->preferIncomingText($incoming['mpu_decisao'] ?? null, $existing->mpu_decisao),
            'despacho_fundamentado' => (bool) (($existing->despacho_fundamentado ?? false) || ($incoming['despacho_fundamentado'] ?? false)),
            'encaminhado_outra_unidade' => (bool) (($existing->encaminhado_outra_unidade ?? false) || ($incoming['encaminhado_outra_unidade'] ?? false)),
            'encaminhado_para_unidade' => $this->preferIncomingText($incoming['encaminhado_para_unidade'] ?? null, $existing->encaminhado_para_unidade),
            'num_ip' => $this->preferIncomingText($incoming['num_ip'] ?? null, $existing->num_ip),
            'num_ipe' => $this->preferIncomingText($incoming['num_ipe'] ?? null, $existing->num_ipe),
            'num_cnj' => $this->preferIncomingText($incoming['num_cnj'] ?? null, $existing->num_cnj),
            'is_active' => true,
            'imported_by' => $incoming['imported_by'] ?? $existing->imported_by,
        ];
    }

    private function preferIncomingText(mixed $incoming, mixed $current): ?string
    {
        $incomingText = $this->cleanNullable($incoming);
        if ($incomingText !== null) {
            return $incomingText;
        }

        return $this->cleanNullable($current);
    }

    private function normalizeMpuDecisao(mixed $value): ?string
    {
        $normalized = $this->normalizeToken((string) $value);

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, 'INDEFER')) {
            return 'INDEFERIDA';
        }

        if (str_contains($normalized, 'DEFER')) {
            return 'DEFERIDA';
        }

        if (in_array($normalized, ['1', 'S', 'SIM', 'TRUE', 'VERDADEIRO', 'X'], true)) {
            return 'DEFERIDA';
        }

        if (in_array($normalized, ['0', 'N', 'NAO', 'FALSE', 'FALSO'], true)) {
            return 'INDEFERIDA';
        }

        return null;
    }

    private function normalizeBooleanLike(mixed $value): bool
    {
        $normalized = $this->normalizeToken((string) $value);

        return in_array($normalized, ['1', 'S', 'SIM', 'TRUE', 'VERDADEIRO', 'X'], true);
    }

    private function findExistingBoletimBySpj(string $spj, ?string $targetCartorioId): ?ProductivityBoletim
    {
        $normalizedSpj = Str::lower(trim($spj));

        if ($targetCartorioId !== null) {
            $sameCartorio = ProductivityBoletim::query()
                ->where('is_active', true)
                ->where('cartorio_id', $targetCartorioId)
                ->whereRaw('lower(spj) = ?', [$normalizedSpj])
                ->latest('updated_at')
                ->first();

            if ($sameCartorio) {
                return $sameCartorio;
            }
        }

        return ProductivityBoletim::query()
            ->where('is_active', true)
            ->whereRaw('lower(spj) = ?', [$normalizedSpj])
            ->latest('updated_at')
            ->first();
    }

    private function findExistingBoletimWithoutSpj(array $row): ?ProductivityBoletim
    {
        if (! empty($row['num_cnj'])) {
            $match = ProductivityBoletim::query()
                ->where('is_active', true)
                ->where('num_cnj', $row['num_cnj'])
                ->latest('updated_at')
                ->first();

            if ($match) {
                return $match;
            }
        }

        if (! empty($row['num_ip']) && ! empty($row['data_fato'])) {
            $match = ProductivityBoletim::query()
                ->where('is_active', true)
                ->where('num_ip', $row['num_ip'])
                ->whereDate('data_fato', $row['data_fato'])
                ->latest('updated_at')
                ->first();

            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private function deactivateDuplicateBoletinsBySpj(ProductivityBoletim $canonical, string $spj, ImportBatch $batch, User $actor): void
    {
        $normalizedSpj = Str::lower(trim($spj));

        $duplicates = ProductivityBoletim::query()
            ->where('is_active', true)
            ->whereKeyNot($canonical->id)
            ->whereRaw('lower(spj) = ?', [$normalizedSpj])
            ->get();

        foreach ($duplicates as $duplicate) {
            $duplicate->update([
                'is_active' => false,
                'import_batch_id' => $batch->id,
                'imported_by' => $actor->id,
                'notes' => $this->pickText(
                    $duplicate->notes,
                    sprintf('Substituido por consolidacao mais recente no lote %s.', $batch->id),
                ),
            ]);
        }
    }

    private function pickText(?string $current, ?string $incoming): ?string
    {
        $current = $this->cleanNullable($current);
        if ($current) {
            return $current;
        }

        return $this->cleanNullable($incoming);
    }

    private function resolveCartorio(?string $label, Collection $cartorios): ?Cartorio
    {
        $text = $this->cleanNullable($label);
        if (! $text) {
            return null;
        }

        $number = $this->extractFirstNumber($text);
        if ($number !== null) {
            $match = $cartorios->first(fn (Cartorio $cartorio) => (int) $cartorio->number === $number);
            if ($match) {
                return $match;
            }
        }

        $folded = $this->normalizeToken($text);
        foreach ($cartorios as $cartorio) {
            foreach ([$cartorio->code, $cartorio->name, $cartorio->designacao] as $candidate) {
                $candidateFolded = $this->normalizeToken((string) $candidate);
                if ($candidateFolded !== '' && ($candidateFolded === $folded || str_contains($folded, $candidateFolded) || str_contains($candidateFolded, $folded))) {
                    return $cartorio;
                }
            }
        }

        return null;
    }

    private function buildSourceProcessKey(?string $sourceKey, ?string $spj, ?string $numIp, ?string $numCnj, int $lineNumber): string
    {
        return $sourceKey
            ?: $spj
            ?: ($numIp ? 'IP#'.$numIp : null)
            ?: ($numCnj ? 'CNJ#'.$numCnj : null)
            ?: 'ROW#'.$lineNumber;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $text = $this->cleanNullable($value);
        if (! $text) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $text)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($text)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeInteger(mixed $value): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : (int) $digits;
    }

    private function parseSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $shared = $this->safeSimpleXml($xml);
        if (! $shared instanceof SimpleXMLElement) {
            return [];
        }

        $values = [];
        foreach ($shared->si as $item) {
            if (isset($item->t)) {
                $values[] = (string) $item->t;
                continue;
            }

            $text = '';
            foreach ($item->r as $run) {
                $text .= (string) ($run->t ?? '');
            }
            $values[] = $text;
        }

        return $values;
    }

    private function parseDateStyleIndexes(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');
        if ($xml === false) {
            return [];
        }

        $styles = $this->safeSimpleXml($xml);
        if (! $styles instanceof SimpleXMLElement) {
            return [];
        }

        $customDateFormats = [];
        if (isset($styles->numFmts->numFmt)) {
            foreach ($styles->numFmts->numFmt as $numFmt) {
                $id = (int) $numFmt['numFmtId'];
                $code = Str::lower((string) $numFmt['formatCode']);
                if (preg_match('/[dmyhs]/', $code)) {
                    $customDateFormats[$id] = true;
                }
            }
        }

        $dateStyles = [];
        if (isset($styles->cellXfs->xf)) {
            foreach ($styles->cellXfs->xf as $index => $xf) {
                $numFmtId = (int) $xf['numFmtId'];
                if ($this->isBuiltinDateFormat($numFmtId) || isset($customDateFormats[$numFmtId])) {
                    $dateStyles[(int) $index] = true;
                }
            }
        }

        return $dateStyles;
    }

    private function resolveFirstWorksheet(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            throw new InvalidArgumentException('Estrutura XLSX incompleta para importacao.');
        }

        $workbook = $this->safeSimpleXml($workbookXml);
        $rels = $this->safeSimpleXml($relsXml);

        if (! $workbook instanceof SimpleXMLElement || ! $rels instanceof SimpleXMLElement) {
            throw new InvalidArgumentException('Estrutura XLSX invalida para importacao.');
        }

        $workbook->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rels->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $sheet = $workbook->xpath('//main:sheets/main:sheet[1]');
        if (! is_array($sheet) || ! isset($sheet[0])) {
            throw new InvalidArgumentException('Planilha XLSX sem abas.');
        }

        $sheetNode = $sheet[0];
        $sheetName = (string) $sheetNode['name'];
        $relationshipId = (string) $sheetNode->attributes('r', true)['id'];

        // Validar relationshipId antes de usar em XPath para evitar injecao
        if (! preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $relationshipId)) {
            throw new InvalidArgumentException('Identificador de relacionamento XLSX invalido.');
        }

        $matches = $rels->xpath("//rel:Relationship[@Id='" . $relationshipId . "']");
        if (! is_array($matches) || ! isset($matches[0])) {
            throw new InvalidArgumentException('Relacionamento da aba XLSX nao encontrado.');
        }

        $target = (string) $matches[0]['Target'];
        $target = str_starts_with($target, '/')
            ? ltrim($target, '/')
            : 'xl/'.ltrim($target, '/');

        return [$sheetName, $target];
    }

    private function extractCellValue(SimpleXMLElement $cell, array $sharedStrings, array $dateStyles): string
    {
        $type = (string) $cell['t'];
        $styleIndex = isset($cell['s']) ? (int) $cell['s'] : null;

        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }

        $value = (string) ($cell->v ?? '');

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        if ($type === 'b') {
            return $value === '1' ? '1' : '0';
        }

        if ($styleIndex !== null && isset($dateStyles[$styleIndex]) && is_numeric($value)) {
            return $this->excelSerialToDate((float) $value);
        }

        return $value;
    }

    private function excelSerialToDate(float $value): string
    {
        $base = Carbon::create(1899, 12, 30, 0, 0, 0, 'UTC');

        return $base->copy()->addDays((int) floor($value))->toDateString();
    }

    private function densifyRow(array $values): array
    {
        $dense = [];
        $lastIndex = (int) array_key_last($values);

        for ($index = 0; $index <= $lastIndex; $index++) {
            $dense[$index] = $values[$index] ?? null;
        }

        return $dense;
    }

    private function columnIndexFromReference(string $reference): int
    {
        preg_match('/^[A-Z]+/', strtoupper($reference), $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }

    private function resolveHeaderAlias(string $value): ?string
    {
        $normalized = $this->normalizeToken($value);

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $field;
            }
        }

        return null;
    }

    private function detectDelimiter(string $line): string
    {
        $scores = [
            ';' => substr_count($line, ';'),
            ',' => substr_count($line, ','),
            "\t" => substr_count($line, "\t"),
        ];

        arsort($scores);

        return (string) array_key_first($scores);
    }

    private function rowIsBlank(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cleanNullable($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    private function normalizeToken(string $value): string
    {
        $ascii = Str::upper(Str::ascii($value));
        $ascii = preg_replace('/[^A-Z0-9]+/', '', $ascii) ?? '';

        return trim($ascii);
    }

    private function extractFirstNumber(string $value): ?int
    {
        preg_match('/\d+/', $value, $matches);

        return isset($matches[0]) ? (int) $matches[0] : null;
    }

    private function cleanNullable(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function isBuiltinDateFormat(int $numFmtId): bool
    {
        return in_array($numFmtId, [14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47], true);
    }

    /**
     * Carrega XML de forma segura: bloqueia entidades externas (XXE) e
     * suprime erros internos para nao vazar informacoes de parsing.
     */
    private function safeSimpleXml(string $xml): SimpleXMLElement|false
    {
        $previous = libxml_use_internal_errors(true);
        $result = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $result;
    }

    private function buildBatchNotes(array $unresolvedHints): ?string
    {
        $unique = array_values(array_unique(array_filter(array_map(fn ($value) => trim((string) $value), $unresolvedHints))));
        if ($unique === []) {
            return null;
        }

        return 'Cartorios nao mapeados nesta importacao: '.implode('; ', array_slice($unique, 0, 8));
    }
}
