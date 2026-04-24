<?php

namespace App\Http\Controllers\Analise;

use App\Enums\ImportItemStatus;
use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AnaliseBatchController extends Controller
{
    public function show(Request $request, ImportBatch $batch): View
    {
        $user = $request->user();
        $visibleCartorioIds = $user?->isSuperAdmin() ? null : $user?->scopeKeys('cartorio');
        $hasCartorioScope = $visibleCartorioIds instanceof Collection && $visibleCartorioIds->isNotEmpty();

        $items = $batch->items()
            ->with('cartorio')
            ->when($hasCartorioScope, fn (Builder $query) => $this->applyVisibleCartorioScope($query, $visibleCartorioIds))
            ->orderByDesc('data_fato')
            ->orderByDesc('created_at')
            ->get();

        abort_if($hasCartorioScope && $items->isEmpty(), 404);

        return view('analise.batches.show', [
            'batch' => $batch,
            'items' => $items,
            'scopeNotice' => $hasCartorioScope
                ? 'Este lote esta filtrado pelo escopo de cartorios vinculado ao seu usuario.'
                : null,
            'summary' => [
                'total' => $items->count(),
                'pending' => $items->where('import_status', ImportItemStatus::Pending->value)->count(),
                'confirmed' => $items->where('import_status', ImportItemStatus::Confirmed->value)->count(),
                'rejected' => $items->where('import_status', ImportItemStatus::Rejected->value)->count(),
                'without_cartorio' => $items->whereNull('cartorio_id')->count(),
                'without_spj' => $items->filter(fn ($item) => blank($item->spj))->count(),
                'without_num_ip' => $items->filter(fn ($item) => blank($item->num_ip))->count(),
                'without_num_cnj' => $items->filter(fn ($item) => blank($item->num_cnj))->count(),
                'without_lavrado_unidade' => $items->filter(fn ($item) => blank($item->lavrado_unidade?->value ?? null))->count(),
                'complete' => $items->filter(fn ($item) => $item->cartorio_id
                    && filled($item->spj)
                    && filled($item->num_ip)
                    && filled($item->num_cnj)
                    && filled($item->lavrado_unidade?->value ?? null))->count(),
            ],
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
}
