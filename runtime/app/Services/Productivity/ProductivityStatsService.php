<?php

namespace App\Services\Productivity;

use App\Models\ProductivityFlagrante;
use App\Models\ProductivityStatMonthly;

class ProductivityStatsService
{
    public function syncFlagrantesForMonth(string $cartorioId, int $referenceYear, int $referenceMonth): void
    {
        $query = ProductivityFlagrante::query()
            ->where('cartorio_id', $cartorioId)
            ->where('reference_year', $referenceYear)
            ->where('reference_month', $referenceMonth)
            ->where('is_active', true);

        $total = (clone $query)->count();
        $ddm = (clone $query)
            ->where('lavrado_unidade', ProductivityFlagrante::LAVRADO_DDM)
            ->count();
        $outras = (clone $query)
            ->where('lavrado_unidade', ProductivityFlagrante::LAVRADO_OUTRAS)
            ->count();

        $monthly = ProductivityStatMonthly::query()->firstOrNew([
            'cartorio_id' => $cartorioId,
            'reference_year' => $referenceYear,
            'reference_month' => $referenceMonth,
        ]);

        $monthly->flagrantes_total = $total;
        $monthly->flagrantes_ddm = $ddm;
        $monthly->flagrantes_outras = $outras;
        $monthly->source_mode = $monthly->exists && $monthly->source_mode === 'MANUAL'
            ? 'MANUAL'
            : 'AUTO';

        $monthly->save();
    }
}
