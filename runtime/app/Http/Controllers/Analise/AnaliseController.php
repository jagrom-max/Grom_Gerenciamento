<?php

namespace App\Http\Controllers\Analise;

use App\Enums\ImportItemStatus;
use App\Http\Controllers\Controller;
use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\ProductivityFlagrante;
use App\Services\Analise\LegacyAnaliseReader;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnaliseController extends Controller
{
    public function __invoke(Request $request, LegacyAnaliseReader $legado): View
    {
        $user = $request->user();
        $visibleCartorioIds = $user?->isSuperAdmin() ? null : $user?->scopeKeys('cartorio');
        $hasCartorioScope = $visibleCartorioIds instanceof Collection && $visibleCartorioIds->isNotEmpty();

        $pendingQuery = $this->applyVisibleCartorioScope(
            ImportItem::query()->where('import_status', ImportItemStatus::Pending->value),
            $visibleCartorioIds,
        );
        $confirmedQuery = $this->applyVisibleCartorioScope(
            ImportItem::query()->where('import_status', ImportItemStatus::Confirmed->value),
            $visibleCartorioIds,
        );

        $qualitySnapshot = (clone $pendingQuery)
            ->selectRaw(
                "
                    COUNT(*) AS total,
                    SUM(CASE WHEN cartorio_id IS NULL THEN 1 ELSE 0 END) AS sem_cartorio,
                    SUM(CASE WHEN trim(coalesce(spj, '')) = '' THEN 1 ELSE 0 END) AS sem_spj,
                    SUM(CASE WHEN trim(coalesce(num_ip, '')) = '' THEN 1 ELSE 0 END) AS sem_num_ip,
                    SUM(CASE WHEN trim(coalesce(num_cnj, '')) = '' THEN 1 ELSE 0 END) AS sem_num_cnj,
                    SUM(CASE WHEN trim(coalesce(lavrado_unidade, '')) = '' THEN 1 ELSE 0 END) AS sem_lavrado_unidade,
                    SUM(
                        CASE
                            WHEN cartorio_id IS NOT NULL
                                AND trim(coalesce(spj, '')) <> ''
                                AND trim(coalesce(num_ip, '')) <> ''
                                AND trim(coalesce(num_cnj, '')) <> ''
                                AND trim(coalesce(lavrado_unidade, '')) <> ''
                            THEN 1
                            ELSE 0
                        END
                    ) AS completos
                ",
            )
            ->first();

        $now = now();

        $ageBuckets = [
            [
                'label' => '0 a 2 dias',
                'count' => (clone $pendingQuery)->where('created_at', '>=', $now->copy()->subDays(2)->startOfDay())->count(),
            ],
            [
                'label' => '3 a 7 dias',
                'count' => (clone $pendingQuery)
                    ->where('created_at', '>=', $now->copy()->subDays(7)->startOfDay())
                    ->where('created_at', '<', $now->copy()->subDays(2)->startOfDay())
                    ->count(),
            ],
            [
                'label' => '8 a 15 dias',
                'count' => (clone $pendingQuery)
                    ->where('created_at', '>=', $now->copy()->subDays(15)->startOfDay())
                    ->where('created_at', '<', $now->copy()->subDays(7)->startOfDay())
                    ->count(),
            ],
            [
                'label' => '16+ dias',
                'count' => (clone $pendingQuery)->where('created_at', '<', $now->copy()->subDays(15)->startOfDay())->count(),
            ],
        ];

        $visibleBatchesQuery = $this->visibleImportBatchesQuery($visibleCartorioIds);

        // ── Dados do banco PHP — analise_bos ──────────────────────────────────
        $phpBoStats = DB::table('analise_bos')
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes,
                SUM(CASE WHEN ato_infracional = 1 THEN 1 ELSE 0 END) AS atos_infracionais,
                SUM(CASE WHEN trim(coalesce(mpu_numero,'')) != '' THEN 1 ELSE 0 END) AS com_mpu,
                SUM(CASE WHEN trim(coalesce(num_ip,'')) != '' THEN 1 ELSE 0 END) AS com_ip
            ")
            ->first();

        $phpBoNaturezas = DB::table('analise_bo_naturezas')
            ->whereNotNull('natureza_label')
            ->where('natureza_label', '!=', '')
            ->selectRaw("
                natureza_label,
                COUNT(*) AS total,
                SUM(CASE WHEN tentado_consumado = 'T' THEN 1 ELSE 0 END) AS tentado,
                SUM(CASE WHEN tentado_consumado = 'C' THEN 1 ELSE 0 END) AS consumado
            ")
            ->groupBy('natureza_label')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        $phpBoAreas = DB::table('analise_bos')
            ->whereNotNull('area_fato')
            ->where('area_fato', '!=', '')
            ->selectRaw("
                area_fato AS area,
                COUNT(*) AS total,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes
            ")
            ->groupBy('area_fato')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        // ── Dados do banco legado (somente leitura, conexão por demanda) ──────
        $legadoStats = $legado->sumario();
        $legadoNaturezas = $legado->topNaturezas(12);
        $legadoAreas = $legado->porArea();

        return view('analise.index', [
            'scopeNotice' => $hasCartorioScope
                ? 'Visao restrita aos cartorios vinculados ao seu usuario.'
                : null,
            'metrics' => [
                'lotes_total' => (clone $visibleBatchesQuery)->count(),
                'itens_pendentes' => (clone $pendingQuery)->count(),
                'itens_sem_cartorio' => (clone $pendingQuery)->whereNull('cartorio_id')->count(),
                'itens_sem_spj' => (clone $pendingQuery)->whereRaw("trim(coalesce(spj, '')) = ''")->count(),
                'itens_sem_num_ip' => (clone $pendingQuery)->whereRaw("trim(coalesce(num_ip, '')) = ''")->count(),
                'itens_sem_num_cnj' => (clone $pendingQuery)->whereRaw("trim(coalesce(num_cnj, '')) = ''")->count(),
                'itens_sem_lavrado_unidade' => (clone $pendingQuery)->whereRaw("trim(coalesce(lavrado_unidade, '')) = ''")->count(),
                'itens_pendentes_completos' => (int) ($qualitySnapshot->completos ?? 0),
                'itens_confirmados' => $confirmedQuery->count(),
                'flagrantes_ativos' => ProductivityFlagrante::query()->where('is_active', true)->count(),
            ],
            'qualitySnapshot' => [
                'total' => (int) ($qualitySnapshot->total ?? 0),
                'sem_cartorio' => (int) ($qualitySnapshot->sem_cartorio ?? 0),
                'sem_spj' => (int) ($qualitySnapshot->sem_spj ?? 0),
                'sem_num_ip' => (int) ($qualitySnapshot->sem_num_ip ?? 0),
                'sem_num_cnj' => (int) ($qualitySnapshot->sem_num_cnj ?? 0),
                'sem_lavrado_unidade' => (int) ($qualitySnapshot->sem_lavrado_unidade ?? 0),
                'completos' => (int) ($qualitySnapshot->completos ?? 0),
            ],
            'ageBuckets' => $ageBuckets,
            'sourceBreakdown' => (clone $visibleBatchesQuery)
                ->selectRaw("
                    COALESCE(NULLIF(trim(source_type), ''), 'Nao informado') AS source_type_label,
                    COUNT(*) AS batches_total,
                    COALESCE(SUM(total_rows), 0) AS total_rows,
                    COALESCE(SUM(rows_staged), 0) AS rows_staged,
                    COALESCE(SUM(rows_updated), 0) AS rows_updated,
                    COALESCE(SUM(rows_skipped), 0) AS rows_skipped,
                    COALESCE(SUM(error_count), 0) AS error_count
                ")
                ->groupByRaw("COALESCE(NULLIF(trim(source_type), ''), 'Nao informado')")
                ->orderByDesc('batches_total')
                ->get(),
            'statusOriginBreakdown' => (clone $pendingQuery)
                ->selectRaw("COALESCE(NULLIF(trim(status_origem), ''), 'Nao informado') AS status_origem_label, COUNT(*) AS total")
                ->groupByRaw("COALESCE(NULLIF(trim(status_origem), ''), 'Nao informado')")
                ->orderByDesc('total')
                ->limit(8)
                ->get(),
            'legadoStats'     => $legadoStats,
            'legadoNaturezas' => $legadoNaturezas,
            'legadoAreas'     => $legadoAreas,
            'phpBoStats'      => $phpBoStats,
            'phpBoNaturezas'  => $phpBoNaturezas,
            'phpBoAreas'      => $phpBoAreas,
            'cartorioBreakdown' => Cartorio::query()
                ->visibleTo($user)
                ->withCount([
                    'importItems as import_items_total_count',
                    'importItems as pending_import_items_count' => fn (Builder $query) => $query->where('import_status', ImportItemStatus::Pending->value),
                    'importItems as confirmed_import_items_count' => fn (Builder $query) => $query->where('import_status', ImportItemStatus::Confirmed->value),
                    'importItems as rejected_import_items_count' => fn (Builder $query) => $query->where('import_status', ImportItemStatus::Rejected->value),
                ])
                ->orderByDesc('pending_import_items_count')
                ->orderBy('name')
                ->limit(6)
                ->get(),
            'recentBatches' => (clone $visibleBatchesQuery)
                ->withCount([
                    'items as items_count' => fn (Builder $query) => $this->applyVisibleCartorioScope($query, $visibleCartorioIds),
                    'items as pending_items_count' => fn (Builder $query) => $this->applyVisibleCartorioScope($query, $visibleCartorioIds)
                        ->where('import_status', ImportItemStatus::Pending->value),
                    'items as confirmed_items_count' => fn (Builder $query) => $this->applyVisibleCartorioScope($query, $visibleCartorioIds)
                        ->where('import_status', ImportItemStatus::Confirmed->value),
                    'items as rejected_items_count' => fn (Builder $query) => $this->applyVisibleCartorioScope($query, $visibleCartorioIds)
                        ->where('import_status', ImportItemStatus::Rejected->value),
                    'items as without_cartorio_items_count' => fn (Builder $query) => $this->applyVisibleCartorioScope($query, $visibleCartorioIds)
                        ->whereNull('cartorio_id'),
                    'items as without_spj_items_count' => fn (Builder $query) => $this->applyVisibleCartorioScope($query, $visibleCartorioIds)
                        ->whereRaw("trim(coalesce(spj, '')) = ''"),
                ])
                ->orderByDesc('imported_at')
                ->orderByDesc('created_at')
                ->limit(6)
                ->get(),
            'recentPendingItems' => ImportItem::query()
                ->with(['batch', 'cartorio'])
                ->where('import_status', ImportItemStatus::Pending->value)
                ->when($hasCartorioScope, fn (Builder $query) => $this->applyVisibleCartorioScope($query, $visibleCartorioIds))
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(),
        ]);
    }

    private function applyVisibleCartorioScope(Builder $query, ?Collection $visibleCartorioIds): Builder
    {
        if ($visibleCartorioIds === null || $visibleCartorioIds->isEmpty()) {
            return $query;
        }

        return $query->where(function (Builder $nestedQuery) use ($visibleCartorioIds): void {
            $nestedQuery
                ->whereNull('cartorio_id')
                ->orWhereIn('cartorio_id', $visibleCartorioIds->all());
        });
    }

    private function visibleImportBatchesQuery(?Collection $visibleCartorioIds): Builder
    {
        $query = ImportBatch::query();

        if ($visibleCartorioIds === null || $visibleCartorioIds->isEmpty()) {
            return $query;
        }

        return $query->whereHas('items', fn (Builder $items) => $this->applyVisibleCartorioScope($items, $visibleCartorioIds));
    }
}
