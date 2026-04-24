<?php

namespace App\Support\Produtividade;

use App\Enums\ImportItemStatus;
use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\RhAfastamento;
use App\Models\RhFuncionario;
use App\Models\RhHoliday;
use App\Models\ProductivityStatMonthly;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProdutividadeStatsDashboardData
{
    public function latestAvailablePeriod(): array
    {
        $latest = ProductivityStatMonthly::query()
            ->select('reference_year', 'reference_month')
            ->orderByDesc('reference_year')
            ->orderByDesc('reference_month')
            ->first();

        if ($latest === null) {
            return [
                'year' => (int) now()->year,
                'month' => (int) now()->month,
            ];
        }

        return [
            'year' => (int) $latest->reference_year,
            'month' => (int) $latest->reference_month,
        ];
    }

    public function build(User $user, int $year, int $month, ?string $cartorioId = null): array
    {
        $cartorios = Cartorio::query()
            ->visibleTo($user)
            ->orderBy('number')
            ->get();

        $selectedCartorio = $this->resolveSelectedCartorio($cartorios, $cartorioId);
        $scopeCartorioIds = $selectedCartorio
            ? [$selectedCartorio->id]
            : $cartorios->pluck('id')->all();

        $statsRows = ProductivityStatMonthly::query()
            ->with('cartorio')
            ->where('reference_year', $year)
            ->when($month > 0, fn ($query) => $query->where('reference_month', $month))
            ->when($scopeCartorioIds !== [], fn ($query) => $query->whereIn('cartorio_id', $scopeCartorioIds))
            ->orderBy('reference_month')
            ->orderBy('cartorio_id')
            ->get();
        $statsRowsByCartorio = $statsRows->groupBy('cartorio_id');
        $cartoriosPreview = $cartorios->map(function (Cartorio $cartorio) use ($statsRowsByCartorio, $year, $month): array {
            $rows = $statsRowsByCartorio->get($cartorio->id, collect());

            return [
                'cartorio' => $cartorio,
                'has_stats' => $rows->isNotEmpty(),
                'ip_instaurados' => (int) $rows->sum('ip_instaurados'),
                'ip_relatados' => (int) $rows->sum('ip_relatados'),
                'concluidos' => (int) $rows->sum('concluidos'),
                'registros' => (int) $rows->sum('registros'),
                'ips_andamento' => (int) $rows->sum('ips_andamento'),
                'flagrantes_total' => (int) $rows->sum('flagrantes_total'),
                'flagrantes_ddm' => (int) $rows->sum('flagrantes_ddm'),
                'flagrantes_outras' => (int) $rows->sum('flagrantes_outras'),
                'period_label' => $month > 0 ? Carbon::create($year, $month, 1)->translatedFormat('F \\d\\e Y') : (string) $year,
            ];
        })->values();

        $pendingItems = $this->pendingItemsForScope($user, $scopeCartorioIds, $selectedCartorio !== null);
        $pendingByCartorio = $pendingItems
            ->filter(fn (array $row): bool => filled($row['item']->cartorio_id))
            ->groupBy(fn (array $row): string => (string) $row['item']->cartorio_id)
            ->map(fn (Collection $items): int => $items->count());

        $ranking = $statsRows
            ->groupBy('cartorio_id')
            ->map(function (Collection $rows) use ($pendingByCartorio): array {
                /** @var ProductivityStatMonthly $first */
                $first = $rows->first();
                $cartorio = $first->cartorio;

                return [
                    'cartorio' => $cartorio,
                    'ip_instaurados' => (int) $rows->sum('ip_instaurados'),
                    'ip_relatados' => (int) $rows->sum('ip_relatados'),
                    'concluidos' => (int) $rows->sum('concluidos'),
                    'registros' => (int) $rows->sum('registros'),
                    'ips_andamento' => (int) $rows->sum('ips_andamento'),
                    'flagrantes_total' => (int) $rows->sum('flagrantes_total'),
                    'flagrantes_ddm' => (int) $rows->sum('flagrantes_ddm'),
                    'flagrantes_outras' => (int) $rows->sum('flagrantes_outras'),
                    'pending_items' => (int) ($pendingByCartorio->get((string) $first->cartorio_id) ?? 0),
                    'last_updated_at' => $rows->max('updated_at'),
                ];
            })
            ->sortByDesc(fn (array $row): int => $row['flagrantes_total'])
            ->values();

        $monthlyBreakdown = $this->buildMonthlyBreakdown($year, $scopeCartorioIds);

        $selectedStats = [
            'ip_instaurados' => (int) $statsRows->sum('ip_instaurados'),
            'ip_relatados' => (int) $statsRows->sum('ip_relatados'),
            'cotas' => (int) $statsRows->sum('cotas'),
            'despachos' => (int) $statsRows->sum('despachos'),
            'concluidos' => (int) $statsRows->sum('concluidos'),
            'registros' => (int) $statsRows->sum('registros'),
            'ips_andamento' => (int) $statsRows->sum('ips_andamento'),
            'flagrantes_total' => (int) $statsRows->sum('flagrantes_total'),
            'flagrantes_ddm' => (int) $statsRows->sum('flagrantes_ddm'),
            'flagrantes_outras' => (int) $statsRows->sum('flagrantes_outras'),
        ];

        $pendingSummary = $this->pendingSummaryForScope($user, $scopeCartorioIds, $selectedCartorio !== null);
        $rhFuncionarios = RhFuncionario::query()
            ->with(['cargo', 'afastamentos' => fn ($query) => $query->orderByDesc('start_date')])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(8)
            ->get();
        $rhAfastamentos = RhAfastamento::query()
            ->with(['funcionario.cargo'])
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->limit(8)
            ->get();
        $rhHolidays = RhHoliday::query()
            ->orderBy('holiday_date')
            ->limit(8)
            ->get();

        return [
            'year' => $year,
            'month' => $month,
            'selectedCartorio' => $selectedCartorio,
            'cartorios' => $cartorios,
            'cartoriosPreview' => $cartoriosPreview,
            'ranking' => $ranking,
            'monthlyBreakdown' => $monthlyBreakdown,
            'recentBatches' => ImportBatch::query()
                ->orderByDesc('imported_at')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(),
            'pendingItems' => $pendingItems->sortByDesc(fn (array $row): int => $row['item']->created_at?->timestamp ?? 0)->values(),
            'selectedStats' => $selectedStats,
            'rhFuncionariosPreview' => $rhFuncionarios,
            'rhAfastamentosPreview' => $rhAfastamentos,
            'rhHolidaysPreview' => $rhHolidays,
            'rhSummary' => [
                'funcionarios_total' => RhFuncionario::query()->count(),
                'funcionarios_ativos' => RhFuncionario::query()->where('is_active', true)->count(),
                'funcionarios_concorrem' => RhFuncionario::query()->where('concorre_escala', true)->count(),
                'afastamentos_ativos' => RhAfastamento::query()->where('is_active', true)->count(),
                'afastamentos_em_vigor' => RhAfastamento::query()
                    ->where('is_active', true)
                    ->whereDate('start_date', '<=', now())
                    ->where(function ($query): void {
                        $query->whereNull('end_date')->orWhereDate('end_date', '>=', now());
                    })
                    ->count(),
                'feriados_ativos' => RhHoliday::query()->where('is_active', true)->count(),
                'feriados_proximos' => RhHoliday::query()
                    ->where('is_active', true)
                    ->whereDate('holiday_date', '>=', now())
                    ->count(),
            ],
            'summary' => [
                'cartorios_visiveis' => $cartorios->count(),
                'stats_registros' => $statsRows->count(),
                'pendencias_abertas' => $pendingSummary['open'],
                'pendencias_7d' => $pendingSummary['aged_7'],
                'pendencias_30d' => $pendingSummary['aged_30'],
                'lotes_30d' => ImportBatch::query()
                    ->where('imported_at', '>=', now()->subDays(30))
                    ->count(),
                'lotes_com_erro_30d' => ImportBatch::query()
                    ->where('imported_at', '>=', now()->subDays(30))
                    ->where('error_count', '>', 0)
                    ->count(),
            ],
            'periodLabel' => $this->periodLabel($year, $month),
        ];
    }

    private function resolveSelectedCartorio(Collection $cartorios, ?string $cartorioId): ?Cartorio
    {
        if (blank($cartorioId)) {
            return null;
        }

        return $cartorios->firstWhere('id', $cartorioId);
    }

    private function pendingItemsForScope(User $user, array $scopeCartorioIds, bool $isScopedView): Collection
    {
        return ImportItem::query()
            ->with(['cartorio', 'batch'])
            ->where('import_status', ImportItemStatus::Pending->value)
            ->when(! $user->isSuperAdmin() && $scopeCartorioIds !== [], function ($query) use ($scopeCartorioIds, $isScopedView): void {
                if ($isScopedView) {
                    $query->whereIn('cartorio_id', $scopeCartorioIds);
                } else {
                    $query->where(function ($innerQuery) use ($scopeCartorioIds): void {
                        $innerQuery->whereIn('cartorio_id', $scopeCartorioIds)->orWhereNull('cartorio_id');
                    });
                }
            })
            ->when(! $user->isSuperAdmin(), function ($query) use ($user): void {
                $allowed = collect($user->scopeKeys('lavrado_unidade'))
                    ->filter()
                    ->values()
                    ->all();

                if ($allowed !== []) {
                    $query->whereIn('lavrado_unidade', $allowed);
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('data_fato')
            ->get()
            ->map(function (ImportItem $item): array {
                $createdAt = $item->created_at ?? now();

                return [
                    'item' => $item,
                    'age_days' => (int) $createdAt->diffInDays(now()),
                ];
            })
            ->values();
    }

    private function pendingSummaryForScope(User $user, array $scopeCartorioIds, bool $isScopedView): array
    {
        $query = ImportItem::query()
            ->where('import_status', ImportItemStatus::Pending->value)
            ->when(! $user->isSuperAdmin() && $scopeCartorioIds !== [], function ($query) use ($scopeCartorioIds, $isScopedView): void {
                if ($isScopedView) {
                    $query->whereIn('cartorio_id', $scopeCartorioIds);
                } else {
                    $query->where(function ($innerQuery) use ($scopeCartorioIds): void {
                        $innerQuery->whereIn('cartorio_id', $scopeCartorioIds)->orWhereNull('cartorio_id');
                    });
                }
            })
            ->when(! $user->isSuperAdmin(), function ($query) use ($user): void {
                $allowed = collect($user->scopeKeys('lavrado_unidade'))
                    ->filter()
                    ->values()
                    ->all();

                if ($allowed !== []) {
                    $query->whereIn('lavrado_unidade', $allowed);
                }
            });

        return [
            'open' => (clone $query)->count(),
            'aged_7' => (clone $query)->where('created_at', '<=', now()->subDays(7))->count(),
            'aged_30' => (clone $query)->where('created_at', '<=', now()->subDays(30))->count(),
        ];
    }

    private function periodLabel(int $year, int $month): string
    {
        if ($month > 0) {
            return Carbon::create($year, $month, 1)->translatedFormat('F \\d\\e Y');
        }

        return (string) $year;
    }

    /**
     * Agrupa os totais mensais em uma unica query ao inves de 12 queries separadas.
     * Retorna colecao indexada 1-12 com month, label e flagrantes_total.
     */
    private function buildMonthlyBreakdown(int $year, array $scopeCartorioIds): Collection
    {
        $rows = ProductivityStatMonthly::query()
            ->selectRaw('reference_month, COALESCE(SUM(flagrantes_total), 0) AS flagrantes_total')
            ->where('reference_year', $year)
            ->when($scopeCartorioIds !== [], fn ($query) => $query->whereIn('cartorio_id', $scopeCartorioIds))
            ->groupBy('reference_month')
            ->get()
            ->keyBy('reference_month');

        return collect(range(1, 12))->map(function (int $monthIndex) use ($year, $rows): array {
            return [
                'month' => $monthIndex,
                'label' => Carbon::create($year, $monthIndex, 1)->translatedFormat('F'),
                'flagrantes_total' => (int) ($rows->get($monthIndex)?->flagrantes_total ?? 0),
            ];
        });
    }
}
