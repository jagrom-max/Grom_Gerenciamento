<?php

namespace App\Services\Productivity;

use App\Models\ImportItem;
use App\Models\ProductivityFlagrante;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FlagranteWorkflowService
{
    public function __construct(
        private readonly ProductivityStatsService $statsService,
    ) {
    }

    public function stageSuggestion(array $data, User $actor): ImportItem
    {
        $payload = $this->normalizePayload($data);

        return DB::transaction(function () use ($payload, $actor): ImportItem {
            $item = ImportItem::query()->create([
                'batch_id' => $payload['batch_id'] ?? null,
                'cartorio_id' => $payload['cartorio_id'],
                'source_process_key' => $payload['source_process_key'] ?: $this->buildSourceKey(),
                'cartorio_source_label' => $payload['cartorio_source_label'],
                'spj' => $payload['spj'],
                'naturezas' => $payload['naturezas'],
                'num_ip' => $payload['num_ip'],
                'num_ipe' => $payload['num_ipe'],
                'num_cnj' => $payload['num_cnj'],
                'data_fato' => $payload['data_fato'],
                'status_origem' => $payload['status_origem'],
                'lavrado_unidade' => $payload['lavrado_unidade'],
                'payload' => $payload,
                'import_status' => ImportItem::STATUS_PENDING,
            ]);

            AuditLogger::log(
                moduleCode: 'produtividade',
                eventType: 'flagrantes.stage',
                entityType: 'import_item',
                entityId: $item->id,
                description: 'Sugestao de flagrante enfileirada para confirmacao manual.',
                metadata: [
                    'cartorio_id' => $item->cartorio_id,
                    'source_process_key' => $item->source_process_key,
                ]
            );

            return $item;
        });
    }

    public function createManual(array $data, User $actor): ProductivityFlagrante
    {
        $payload = $this->normalizePayload($data);

        return DB::transaction(function () use ($payload, $actor): ProductivityFlagrante {
            $flagrante = $this->upsertFlagrante($payload, $actor, ProductivityFlagrante::ENTRY_MODE_MANUAL, null);

            AuditLogger::log(
                moduleCode: 'produtividade',
                eventType: 'flagrantes.manual_create',
                entityType: 'productivity_flagrante',
                entityId: $flagrante->id,
                description: 'Flagrante criado manualmente no piloto web.',
                metadata: [
                    'cartorio_id' => $flagrante->cartorio_id,
                    'reference_year' => $flagrante->reference_year,
                    'reference_month' => $flagrante->reference_month,
                ]
            );

            return $flagrante;
        });
    }

    public function confirmImportItem(ImportItem $item, User $actor): ProductivityFlagrante
    {
        if ($item->import_status !== ImportItem::STATUS_PENDING) {
            throw new InvalidArgumentException('Apenas sugestoes pendentes podem ser confirmadas.');
        }

        $payload = $this->normalizePayload([
            'cartorio_id' => $item->cartorio_id,
            'spj' => $item->spj,
            'naturezas' => $item->naturezas,
            'num_ip' => $item->num_ip,
            'num_ipe' => $item->num_ipe,
            'num_cnj' => $item->num_cnj,
            'data_fato' => optional($item->data_fato)->format('Y-m-d'),
            'lavrado_unidade' => $item->lavrado_unidade,
            'notes' => 'Confirmado manualmente a partir da fila de importacao.',
        ]);

        return DB::transaction(function () use ($item, $payload, $actor): ProductivityFlagrante {
            $flagrante = $this->upsertFlagrante($payload, $actor, ProductivityFlagrante::ENTRY_MODE_IMPORT, $item);

            $item->forceFill([
                'import_status' => ImportItem::STATUS_CONFIRMED,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'rejected_reason' => null,
            ])->save();

            AuditLogger::log(
                moduleCode: 'produtividade',
                eventType: 'flagrantes.confirm',
                entityType: 'import_item',
                entityId: $item->id,
                description: 'Sugestao de flagrante confirmada manualmente.',
                metadata: [
                    'cartorio_id' => $flagrante->cartorio_id,
                    'flagrante_id' => $flagrante->id,
                ]
            );

            return $flagrante;
        });
    }

    public function rejectImportItem(ImportItem $item, User $actor, ?string $reason = null): void
    {
        if ($item->import_status !== ImportItem::STATUS_PENDING) {
            throw new InvalidArgumentException('Apenas sugestoes pendentes podem ser rejeitadas.');
        }

        DB::transaction(function () use ($item, $actor, $reason): void {
            $item->forceFill([
                'import_status' => ImportItem::STATUS_REJECTED,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'rejected_reason' => $this->cleanNullable($reason) ?: 'Rejeitado manualmente.',
            ])->save();

            AuditLogger::log(
                moduleCode: 'produtividade',
                eventType: 'flagrantes.reject',
                entityType: 'import_item',
                entityId: $item->id,
                description: 'Sugestao de flagrante rejeitada manualmente.',
                metadata: [
                    'cartorio_id' => $item->cartorio_id,
                ]
            );
        });
    }

    public function deactivateFlagrante(ProductivityFlagrante $flagrante, User $actor): void
    {
        DB::transaction(function () use ($flagrante, $actor): void {
            $flagrante->update([
                'is_active' => false,
                'notes' => $this->pickText($flagrante->notes, 'Flagrante inativado manualmente.'),
            ]);

            $this->statsService->syncFlagrantesForMonth(
                $flagrante->cartorio_id,
                (int) $flagrante->reference_year,
                (int) $flagrante->reference_month,
            );

            AuditLogger::log(
                moduleCode: 'produtividade',
                eventType: 'flagrantes.deactivate',
                entityType: 'productivity_flagrante',
                entityId: $flagrante->id,
                description: 'Flagrante inativado no piloto web.',
                metadata: [
                    'cartorio_id' => $flagrante->cartorio_id,
                ]
            );
        });
    }

    private function upsertFlagrante(
        array $payload,
        User $actor,
        string $entryMode,
        ?ImportItem $sourceItem,
    ): ProductivityFlagrante {
        $referenceYear = (int) Carbon::parse($payload['data_fato'])->format('Y');
        $referenceMonth = (int) Carbon::parse($payload['data_fato'])->format('n');

        $existing = $this->findExistingFlagrante(
            cartorioId: $payload['cartorio_id'],
            referenceYear: $referenceYear,
            referenceMonth: $referenceMonth,
            spj: $payload['spj'],
            numIp: $payload['num_ip'],
            numCnj: $payload['num_cnj'],
        );

        if ($existing) {
            $existing->fill([
                'source_item_id' => $existing->source_item_id ?: $sourceItem?->id,
                'spj' => $this->pickText($existing->spj, $payload['spj']),
                'naturezas' => $this->mergeSemicolonValues($existing->naturezas, $payload['naturezas']),
                'num_ip' => $this->pickText($existing->num_ip, $payload['num_ip']),
                'num_ipe' => $this->pickText($existing->num_ipe, $payload['num_ipe']),
                'num_cnj' => $this->pickText($existing->num_cnj, $payload['num_cnj']),
                'data_fato' => $this->pickText(optional($existing->data_fato)->format('Y-m-d'), $payload['data_fato']),
                'lavrado_unidade' => $this->pickText($existing->lavrado_unidade, $payload['lavrado_unidade']),
                'notes' => $this->pickText($existing->notes, $payload['notes']),
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'is_active' => true,
            ])->save();

            $this->statsService->syncFlagrantesForMonth($existing->cartorio_id, $referenceYear, $referenceMonth);

            return $existing->refresh();
        }

        $flagrante = ProductivityFlagrante::query()->create([
            'cartorio_id' => $payload['cartorio_id'],
            'source_item_id' => $sourceItem?->id,
            'reference_year' => $referenceYear,
            'reference_month' => $referenceMonth,
            'spj' => $payload['spj'],
            'naturezas' => $payload['naturezas'],
            'num_ip' => $payload['num_ip'],
            'num_ipe' => $payload['num_ipe'],
            'num_cnj' => $payload['num_cnj'],
            'data_fato' => $payload['data_fato'],
            'lavrado_unidade' => $payload['lavrado_unidade'],
            'entry_mode' => $entryMode,
            'is_active' => true,
            'confirmed_by' => $actor->id,
            'confirmed_at' => now(),
            'notes' => $payload['notes'],
        ]);

        $this->statsService->syncFlagrantesForMonth($flagrante->cartorio_id, $referenceYear, $referenceMonth);

        return $flagrante;
    }

    private function normalizePayload(array $data): array
    {
        $cartorioId = (string) ($data['cartorio_id'] ?? '');
        $dataFato = (string) ($data['data_fato'] ?? '');

        if ($cartorioId === '' || $dataFato === '') {
            throw new InvalidArgumentException('Cartorio e data do fato sao obrigatorios.');
        }

        $normalized = [
            'batch_id' => $data['batch_id'] ?? null,
            'cartorio_id' => $cartorioId,
            'cartorio_source_label' => $this->cleanNullable($data['cartorio_source_label'] ?? null),
            'source_process_key' => $this->cleanNullable($data['source_process_key'] ?? null),
            'spj' => $this->cleanNullable($data['spj'] ?? null),
            'naturezas' => $this->cleanNullable($data['naturezas'] ?? null),
            'num_ip' => $this->cleanNullable($data['num_ip'] ?? null),
            'num_ipe' => $this->cleanNullable($data['num_ipe'] ?? null),
            'num_cnj' => $this->cleanNullable($data['num_cnj'] ?? null),
            'data_fato' => Carbon::parse($dataFato)->format('Y-m-d'),
            'lavrado_unidade' => $this->normalizeLavradoUnidade($data['lavrado_unidade'] ?? null),
            'status_origem' => $this->cleanNullable($data['status_origem'] ?? null) ?: 'Flagrante',
            'notes' => $this->cleanNullable($data['notes'] ?? null),
        ];

        if (! $normalized['spj'] && ! $normalized['num_ip'] && ! $normalized['num_ipe'] && ! $normalized['num_cnj']) {
            throw new InvalidArgumentException('Informe ao menos um identificador: SPJ, IP, IP-e ou CNJ.');
        }

        return $normalized;
    }

    private function normalizeLavradoUnidade(?string $value): string
    {
        $value = Str::upper(Str::ascii((string) $value));

        if (Str::contains($value, ['DDM', 'ELETR', 'ONLINE'])) {
            return ProductivityFlagrante::LAVRADO_DDM;
        }

        return ProductivityFlagrante::LAVRADO_OUTRAS;
    }

    private function findExistingFlagrante(
        string $cartorioId,
        int $referenceYear,
        int $referenceMonth,
        ?string $spj,
        ?string $numIp,
        ?string $numCnj,
    ): ?ProductivityFlagrante {
        $query = ProductivityFlagrante::query()
            ->where('cartorio_id', $cartorioId)
            ->where('reference_year', $referenceYear)
            ->where('reference_month', $referenceMonth)
            ->where('is_active', true);

        $spj = $this->cleanNullable($spj);
        $numIp = $this->cleanNullable($numIp);
        $numCnj = $this->cleanNullable($numCnj);

        if (! $spj && ! $numIp && ! $numCnj) {
            return null;
        }

        return $query->where(function ($builder) use ($spj, $numIp, $numCnj): void {
            if ($spj) {
                $builder->orWhere('spj', $spj);
            }
            if ($numIp) {
                $builder->orWhere('num_ip', $numIp);
            }
            if ($numCnj) {
                $builder->orWhere('num_cnj', $numCnj);
            }
        })->latest('created_at')->first();
    }

    private function buildSourceKey(): string
    {
        return 'FG-'.Str::ulid();
    }

    private function cleanNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function pickText(?string $current, ?string $incoming): ?string
    {
        return $this->cleanNullable($current) ?: $this->cleanNullable($incoming);
    }

    private function mergeSemicolonValues(?string $current, ?string $incoming): ?string
    {
        $items = [];
        $seen = [];

        foreach ([$current, $incoming] as $value) {
            foreach (preg_split('/[;|]+/', (string) $value) ?: [] as $part) {
                $part = trim($part);
                $key = Str::lower($part);
                if ($part !== '' && ! isset($seen[$key])) {
                    $seen[$key] = true;
                    $items[] = $part;
                }
            }
        }

        return $items === [] ? null : implode('; ', $items);
    }
}
