<?php

namespace App\Http\Controllers\Analise;

use App\Enums\ImportItemStatus;
use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class AnaliseExportController extends Controller
{
    public function pending(Request $request): Response
    {
        $visibleCartorioIds = $this->visibleCartorioIds($request);

        $items = ImportItem::query()
            ->with(['batch', 'cartorio'])
            ->where('import_status', ImportItemStatus::Pending->value)
            ->when($visibleCartorioIds instanceof Collection && $visibleCartorioIds->isNotEmpty(), fn (Builder $query) => $this->applyVisibleCartorioScope($query, $visibleCartorioIds))
            ->orderByDesc('created_at')
            ->get();

        return $this->csvResponse(
            sprintf('analise-pendencias-%s.csv', now()->format('Ymd-His')),
            'Pendencias da Analise de Dados',
            [
                ['batch', 'source', 'source_type', 'source_process_key', 'spj', 'reference_year', 'reference_month', 'status_origem', 'cartorio', 'cartorio_hint', 'data_fato', 'lavrado_unidade', 'status', 'payload_source', 'payload_kind', 'num_ip', 'num_ipe', 'num_cnj', 'confirmed_at', 'rejected_reason'],
            ],
            $items->map(fn (ImportItem $item): array => array_merge(
                [
                    optional($item->batch)->id,
                    optional($item->batch)->source_name,
                    optional($item->batch)->source_type,
                ],
                $this->itemToCsvRow($item),
            ))->all(),
        );
    }

    public function batch(Request $request, ImportBatch $batch): Response
    {
        $visibleCartorioIds = $this->visibleCartorioIds($request);
        $items = $batch->items()
            ->with('cartorio')
            ->when($visibleCartorioIds instanceof Collection && $visibleCartorioIds->isNotEmpty(), fn (Builder $query) => $this->applyVisibleCartorioScope($query, $visibleCartorioIds))
            ->orderByDesc('data_fato')
            ->orderByDesc('created_at')
            ->get();

        abort_if($visibleCartorioIds instanceof Collection && $visibleCartorioIds->isNotEmpty() && $items->isEmpty(), 404);

        return $this->csvResponse(
            sprintf('lote-%s-%s.csv', $batch->id, now()->format('Ymd-His')),
            sprintf('Lote %s', $batch->source_name),
            [
                ['batch', 'source', 'source_type', 'sheet_name', 'header_row', 'source_process_key', 'spj', 'reference_year', 'reference_month', 'status_origem', 'cartorio', 'cartorio_hint', 'data_fato', 'lavrado_unidade', 'status', 'payload_source', 'payload_kind', 'num_ip', 'num_ipe', 'num_cnj', 'confirmed_at', 'rejected_reason'],
            ],
            $items->map(fn (ImportItem $item): array => array_merge(
                [$batch->id, $batch->source_name, $batch->source_type, $batch->sheet_name, $batch->header_row],
                $this->itemToCsvRow($item),
            ))->all(),
        );
    }

    private function itemToCsvRow(ImportItem $item): array
    {
        return [
            $item->source_process_key,
            $item->spj ?: $item->source_process_key,
            $item->reference_year,
            $item->reference_month,
            $item->status_origem,
            optional($item->cartorio)->name,
            $item->cartorio_hint,
            $item->data_fato?->format('Y-m-d'),
            $item->lavrado_unidade?->label(),
            $item->import_status->value,
            data_get($item->payload, 'source'),
            data_get($item->payload, 'kind'),
            $item->num_ip,
            $item->num_ipe,
            $item->num_cnj,
            $item->confirmed_at?->format('Y-m-d H:i:s'),
            $item->rejected_reason,
        ];
    }

    private function visibleCartorioIds(Request $request): ?Collection
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return null;
        }

        return $user->scopeKeys('cartorio');
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

    private function csvResponse(string $fileName, string $title, array $headers, array $rows): Response
    {
        $handle = fopen('php://temp', 'wb+');

        if ($handle === false) {
            abort(500, 'Nao foi possivel preparar a exportacao.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [$title], ';');
        fputcsv($handle, [], ';');

        foreach ($headers as $headerRow) {
            fputcsv($handle, $headerRow, ';');
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
        ]);
    }
}
