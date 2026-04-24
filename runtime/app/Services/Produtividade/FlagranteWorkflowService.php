<?php

namespace App\Services\Produtividade;

use App\Enums\ImportItemStatus;
use App\Enums\LavradoUnidade;
use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\ProductivityBoletim;
use App\Models\ProductivityFlagrante;
use App\Models\ProductivityStatMonthly;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FlagranteWorkflowService
{
    public function enqueueManualSuggestion(Cartorio $cartorio, array $data, User $actor): ImportItem
    {
        $this->ensureIdentifiers($data);

        [$referenceYear, $referenceMonth, $dataFato] = $this->resolvePeriod(
            $data['data_fato'] ?? null,
            null,
            null,
        );

        return DB::transaction(function () use ($cartorio, $data, $actor, $referenceYear, $referenceMonth, $dataFato): ImportItem {
            $batch = ImportBatch::query()->create([
                'source_name' => 'MANUAL_WEB',
                'source_period_start' => $dataFato,
                'source_period_end' => $dataFato,
                'imported_by' => $actor->id,
                'imported_at' => now(),
                'total_rows' => 1,
                'notes' => 'Sugestao manual registrada para homologacao da fila de importacao.',
            ]);

            return ImportItem::query()->create([
                'batch_id' => $batch->id,
                'source_process_key' => trim((string) $data['source_process_key']),
                'cartorio_id' => $cartorio->id,
                'cartorio_hint' => sprintf('%03d - %s', $cartorio->number, $cartorio->name),
                'reference_year' => $referenceYear,
                'reference_month' => $referenceMonth,
                'spj' => $this->cleanNullable($data['spj'] ?? null),
                'naturezas' => $this->cleanNullable($data['naturezas'] ?? null),
                'num_ip' => $this->cleanNullable($data['num_ip'] ?? null),
                'num_ipe' => $this->cleanNullable($data['num_ipe'] ?? null),
                'num_cnj' => $this->cleanNullable($data['num_cnj'] ?? null),
                'data_fato' => $dataFato,
                'status_origem' => 'Flagrante',
                'lavrado_unidade' => $this->normalizeLavrado($data['lavrado_unidade'] ?? null),
                'payload' => [
                    'source' => 'manual_web',
                    'notes' => $this->cleanNullable($data['notes'] ?? null),
                ],
                'import_status' => ImportItemStatus::Pending,
            ]);
        });
    }

    public function createManualFlagrante(Cartorio $cartorio, array $data, User $actor): ProductivityFlagrante
    {
        $this->ensureIdentifiers($data);

        [$referenceYear, $referenceMonth, $dataFato] = $this->resolvePeriod(
            $data['data_fato'] ?? null,
            null,
            null,
        );

        return DB::transaction(function () use ($cartorio, $data, $actor, $referenceYear, $referenceMonth, $dataFato): ProductivityFlagrante {
            $existing = $this->findExistingFlagrante(
                $cartorio->id,
                $referenceYear,
                $referenceMonth,
                $data['spj'] ?? null,
                $data['num_ip'] ?? null,
                $data['num_cnj'] ?? null,
            );

            if ($existing) {
                $existing->fill([
                    'spj' => $this->pickText($existing->spj, $data['spj'] ?? null),
                    'naturezas' => $this->mergeSemicolonValues($existing->naturezas, $data['naturezas'] ?? null),
                    'num_ip' => $this->pickText($existing->num_ip, $data['num_ip'] ?? null),
                    'num_ipe' => $this->pickText($existing->num_ipe, $data['num_ipe'] ?? null),
                    'num_cnj' => $this->pickText($existing->num_cnj, $data['num_cnj'] ?? null),
                    'data_fato' => $existing->data_fato ?: $dataFato,
                    'lavrado_unidade' => $existing->lavrado_unidade ?: $this->normalizeLavrado($data['lavrado_unidade'] ?? null),
                    'manually_confirmed' => true,
                    'notes' => $this->pickText($existing->notes, $data['notes'] ?? null),
                    'is_active' => true,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => now(),
                ])->save();

                $this->syncMonthlyStats($cartorio->id, $referenceYear, $referenceMonth);

                return $existing->fresh();
            }

            $flagrante = ProductivityFlagrante::query()->create([
                'cartorio_id' => $cartorio->id,
                'reference_year' => $referenceYear,
                'reference_month' => $referenceMonth,
                'spj' => $this->cleanNullable($data['spj'] ?? null),
                'naturezas' => $this->cleanNullable($data['naturezas'] ?? null),
                'num_ip' => $this->cleanNullable($data['num_ip'] ?? null),
                'num_ipe' => $this->cleanNullable($data['num_ipe'] ?? null),
                'num_cnj' => $this->cleanNullable($data['num_cnj'] ?? null),
                'data_fato' => $dataFato,
                'lavrado_unidade' => $this->normalizeLavrado($data['lavrado_unidade'] ?? null),
                'manually_confirmed' => true,
                'is_active' => true,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'notes' => $this->cleanNullable($data['notes'] ?? null),
            ]);

            $this->syncMonthlyStats($cartorio->id, $referenceYear, $referenceMonth);

            return $flagrante;
        });
    }

    public function confirmImportItem(Cartorio $cartorio, ImportItem $item, User $actor): ProductivityFlagrante
    {
        if ($item->import_status !== ImportItemStatus::Pending) {
            throw new InvalidArgumentException('Apenas sugestoes pendentes podem ser confirmadas.');
        }

        if ($item->cartorio_id && $item->cartorio_id !== $cartorio->id) {
            throw new InvalidArgumentException('A sugestao informada nao pertence ao cartorio selecionado.');
        }

        return DB::transaction(function () use ($cartorio, $item, $actor): ProductivityFlagrante {
            $referenceYear = (int) $item->reference_year;
            $referenceMonth = (int) $item->reference_month;

            $existing = $this->findExistingFlagrante(
                $cartorio->id,
                $referenceYear,
                $referenceMonth,
                $item->spj,
                $item->num_ip,
                $item->num_cnj,
            );

            if ($existing) {
                $existing->fill([
                    'source_item_id' => $existing->source_item_id ?: $item->id,
                    'spj' => $this->pickText($existing->spj, $item->spj),
                    'naturezas' => $this->mergeSemicolonValues($existing->naturezas, $item->naturezas),
                    'num_ip' => $this->pickText($existing->num_ip, $item->num_ip),
                    'num_ipe' => $this->pickText($existing->num_ipe, $item->num_ipe),
                    'num_cnj' => $this->pickText($existing->num_cnj, $item->num_cnj),
                    'data_fato' => $existing->data_fato ?: $item->data_fato,
                    'lavrado_unidade' => $existing->lavrado_unidade ?: $this->normalizeLavrado($item->lavrado_unidade),
                    'notes' => $this->pickText($existing->notes, 'Atualizado automaticamente a partir da fila de importacao.'),
                    'is_active' => true,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => now(),
                ])->save();

                $flagrante = $existing->fresh();
            } else {
                $flagrante = ProductivityFlagrante::query()->create([
                    'cartorio_id' => $cartorio->id,
                    'source_item_id' => $item->id,
                    'reference_year' => $referenceYear,
                    'reference_month' => $referenceMonth,
                    'spj' => $this->cleanNullable($item->spj),
                    'naturezas' => $this->cleanNullable($item->naturezas),
                    'num_ip' => $this->cleanNullable($item->num_ip),
                    'num_ipe' => $this->cleanNullable($item->num_ipe),
                    'num_cnj' => $this->cleanNullable($item->num_cnj),
                    'data_fato' => $item->data_fato,
                    'lavrado_unidade' => $this->normalizeLavrado($item->lavrado_unidade),
                    'manually_confirmed' => false,
                    'is_active' => true,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => now(),
                    'notes' => 'Confirmado manualmente a partir da fila de importacao.',
                ]);
            }

            $item->update([
                'cartorio_id' => $cartorio->id,
                'import_status' => ImportItemStatus::Confirmed,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'rejected_reason' => null,
                'productivity_flagrante_id' => $flagrante->id,
            ]);

            $this->linkBoletimToFlagrante(
                $cartorio->id,
                $flagrante->id,
                $item->spj,
                $item->num_ip,
                $item->num_cnj,
            );

            $this->syncMonthlyStats($cartorio->id, $referenceYear, $referenceMonth);

            return $flagrante;
        });
    }

    public function rejectImportItem(ImportItem $item, User $actor, ?string $reason = null): void
    {
        if ($item->import_status !== ImportItemStatus::Pending) {
            throw new InvalidArgumentException('Apenas sugestoes pendentes podem ser rejeitadas.');
        }

        DB::transaction(function () use ($item, $actor, $reason): void {
            $item->update([
                'import_status' => ImportItemStatus::Rejected,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'rejected_reason' => $this->cleanNullable($reason) ?: 'Rejeitado manualmente na fila do cartorio.',
            ]);
        });
    }

    public function assignImportItemCartorio(ImportItem $item, Cartorio $cartorio, User $actor): ImportItem
    {
        if ($item->import_status !== ImportItemStatus::Pending) {
            throw new InvalidArgumentException('Apenas sugestoes pendentes podem receber designacao manual de cartorio.');
        }

        return DB::transaction(function () use ($item, $cartorio, $actor): ImportItem {
            $assignedAt = now();
            $formattedHint = $this->formatCartorioHint($cartorio);
            $payload = is_array($item->payload) ? $item->payload : [];
            $history = $payload['manual_cartorio_assignments'] ?? [];

            if (! is_array($history)) {
                $history = [];
            }

            $history[] = [
                'assigned_by' => $actor->id,
                'assigned_at' => $assignedAt->toIso8601String(),
                'previous_cartorio_id' => $item->cartorio_id,
                'previous_cartorio_hint' => $item->cartorio_hint,
                'assigned_cartorio_id' => $cartorio->id,
                'assigned_cartorio_hint' => $formattedHint,
            ];

            $payload['manual_assignment'] = end($history);
            $payload['manual_cartorio_assignments'] = array_slice($history, -10);

            $item->update([
                'cartorio_id' => $cartorio->id,
                'cartorio_hint' => $formattedHint,
                'payload' => $payload,
            ]);

            return $item->fresh();
        });
    }

    public function deactivateFlagrante(ProductivityFlagrante $flagrante, User $actor): void
    {
        DB::transaction(function () use ($flagrante, $actor): void {
            $flagrante->update([
                'is_active' => false,
                'notes' => $this->pickText($flagrante->notes, 'Registro inativado manualmente na gestao web.'),
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);

            $this->syncMonthlyStats(
                $flagrante->cartorio_id,
                (int) $flagrante->reference_year,
                (int) $flagrante->reference_month,
            );
        });
    }

    public function syncMonthlyStats(string $cartorioId, int $referenceYear, int $referenceMonth): void
    {
        $active = ProductivityFlagrante::query()
            ->where('cartorio_id', $cartorioId)
            ->where('reference_year', $referenceYear)
            ->where('reference_month', $referenceMonth)
            ->where('is_active', true);

        $total = (clone $active)->count();
        $ddm = (clone $active)->where('lavrado_unidade', LavradoUnidade::Ddm->value)->count();
        $outras = max($total - $ddm, 0);

        $current = ProductivityStatMonthly::query()
            ->where('cartorio_id', $cartorioId)
            ->where('reference_year', $referenceYear)
            ->where('reference_month', $referenceMonth)
            ->first();

        if ($current) {
            $current->update([
                'flagrantes_total' => $total,
                'flagrantes_ddm' => $ddm,
                'flagrantes_outras' => $outras,
                'source_mode' => 'AUTO',
            ]);

            return;
        }

        if ($total <= 0) {
            return;
        }

        ProductivityStatMonthly::query()->create([
            'cartorio_id' => $cartorioId,
            'reference_year' => $referenceYear,
            'reference_month' => $referenceMonth,
            'flagrantes_total' => $total,
            'flagrantes_ddm' => $ddm,
            'flagrantes_outras' => $outras,
            'source_mode' => 'AUTO',
        ]);
    }

    public function refreshLinkedFlagranteFromImportItem(ImportItem $item): ?ProductivityFlagrante
    {
        $flagrante = $item->productivityFlagrante;

        if (! $flagrante) {
            return null;
        }

        return DB::transaction(function () use ($item, $flagrante): ProductivityFlagrante {
            $previousYear = (int) $flagrante->reference_year;
            $previousMonth = (int) $flagrante->reference_month;

            $flagrante->fill([
                'source_item_id' => $flagrante->source_item_id ?: $item->id,
                'reference_year' => (int) ($item->reference_year ?: $flagrante->reference_year),
                'reference_month' => (int) ($item->reference_month ?: $flagrante->reference_month),
                'spj' => $this->pickText($flagrante->spj, $item->spj),
                'naturezas' => $this->mergeSemicolonValues($flagrante->naturezas, $item->naturezas),
                'num_ip' => $this->pickText($flagrante->num_ip, $item->num_ip),
                'num_ipe' => $this->pickText($flagrante->num_ipe, $item->num_ipe),
                'num_cnj' => $this->pickText($flagrante->num_cnj, $item->num_cnj),
                'data_fato' => $flagrante->data_fato ?: $item->data_fato,
                'lavrado_unidade' => $flagrante->lavrado_unidade ?: $this->normalizeLavrado($item->lavrado_unidade),
                'is_active' => true,
            ])->save();

            $this->syncMonthlyStats(
                $flagrante->cartorio_id,
                (int) $flagrante->reference_year,
                (int) $flagrante->reference_month,
            );

            if ($previousYear !== (int) $flagrante->reference_year || $previousMonth !== (int) $flagrante->reference_month) {
                $this->syncMonthlyStats($flagrante->cartorio_id, $previousYear, $previousMonth);
            }

            return $flagrante->fresh();
        });
    }

    private function findExistingFlagrante(
        string $cartorioId,
        int $referenceYear,
        int $referenceMonth,
        ?string $spj,
        ?string $numIp,
        ?string $numCnj,
    ): ?ProductivityFlagrante {
        $spj = $this->cleanNullable($spj);
        $numIp = $this->cleanNullable($numIp);
        $numCnj = $this->cleanNullable($numCnj);

        if (! $spj && ! $numIp && ! $numCnj) {
            return null;
        }

        return ProductivityFlagrante::query()
            ->where('cartorio_id', $cartorioId)
            ->where('reference_year', $referenceYear)
            ->where('reference_month', $referenceMonth)
            ->where('is_active', true)
            ->where(function ($query) use ($spj, $numIp, $numCnj): void {
                if ($spj) {
                    $query->orWhere('spj', $spj);
                }
                if ($numIp) {
                    $query->orWhere('num_ip', $numIp);
                }
                if ($numCnj) {
                    $query->orWhere('num_cnj', $numCnj);
                }
            })
            ->latest('confirmed_at')
            ->first();
    }

    private function linkBoletimToFlagrante(
        string $cartorioId,
        string $flagranteId,
        ?string $spj,
        ?string $numIp,
        ?string $numCnj,
    ): void {
        $spj = $this->cleanNullable($spj);
        $numIp = $this->cleanNullable($numIp);
        $numCnj = $this->cleanNullable($numCnj);

        if (! $spj && ! $numIp && ! $numCnj) {
            return;
        }

        $query = ProductivityBoletim::query()
            ->where('cartorio_id', $cartorioId)
            ->whereNull('productivity_flagrante_id')
            ->where(function ($builder) use ($spj, $numIp, $numCnj): void {
                if ($spj) {
                    $builder->orWhere('spj', $spj);
                }

                if ($numIp) {
                    $builder->orWhere('num_ip', $numIp);
                }

                if ($numCnj) {
                    $builder->orWhere('num_cnj', $numCnj);
                }
            })
            ->latest('id');

        $boletim = $query->first();

        if (! $boletim) {
            return;
        }

        $boletim->update([
            'is_flagrante' => true,
            'productivity_flagrante_id' => $flagranteId,
        ]);
    }

    private function resolvePeriod(?string $dataFato, ?int $referenceYear, ?int $referenceMonth): array
    {
        $dataFato = $this->cleanNullable($dataFato);

        if ($dataFato) {
            $parsed = Carbon::parse($dataFato);

            return [(int) $parsed->format('Y'), (int) $parsed->format('n'), $parsed->toDateString()];
        }

        if ($referenceYear && $referenceMonth) {
            return [$referenceYear, $referenceMonth, null];
        }

        throw new InvalidArgumentException('Data do fato ou periodo de referencia obrigatorio.');
    }

    private function normalizeLavrado(string|LavradoUnidade|null $value): LavradoUnidade
    {
        if ($value instanceof LavradoUnidade) {
            return $value;
        }

        $value = strtoupper(trim((string) $value));

        return $value === LavradoUnidade::Ddm->value
            ? LavradoUnidade::Ddm
            : LavradoUnidade::OutrasUnidades;
    }

    private function ensureIdentifiers(array $data): void
    {
        $identifiers = [
            $this->cleanNullable($data['spj'] ?? null),
            $this->cleanNullable($data['num_ip'] ?? null),
            $this->cleanNullable($data['num_ipe'] ?? null),
            $this->cleanNullable($data['num_cnj'] ?? null),
        ];

        if (! array_filter($identifiers)) {
            throw new InvalidArgumentException('Informe ao menos um identificador: SPJ, IP, IP-e ou CNJ.');
        }
    }

    private function mergeSemicolonValues(?string $current, ?string $incoming): ?string
    {
        $items = [];
        $seen = [];

        foreach ([str_replace('|', ';', (string) $current), str_replace('|', ';', (string) $incoming)] as $value) {
            foreach (explode(';', $value) as $piece) {
                $text = trim($piece);
                $key = mb_strtolower($text);

                if ($text === '' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $items[] = $text;
            }
        }

        return $items === [] ? null : implode('; ', $items);
    }

    private function pickText(?string $current, ?string $incoming): ?string
    {
        return $this->cleanNullable($current) ?: $this->cleanNullable($incoming);
    }

    private function cleanNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function formatCartorioHint(Cartorio $cartorio): string
    {
        return sprintf(
            '%s - %s',
            str_pad((string) $cartorio->number, 3, '0', STR_PAD_LEFT),
            trim((string) $cartorio->name),
        );
    }
}
