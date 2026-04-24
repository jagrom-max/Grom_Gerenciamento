<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditTrailExportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'module_code' => ['nullable', 'string', 'max:100'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'entity_type' => ['nullable', 'string', 'max:100'],
            'actor_username' => ['nullable', 'string', 'max:255'],
            'source_ip' => ['nullable', 'string', 'max:45'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $events = $this->filteredQuery($filters)
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $this->csvResponse(
            sprintf('auditoria-%s.csv', now()->format('Ymd-His')),
            'Auditoria do Grom.Seg',
            [[
                'created_at',
                'actor_username',
                'actor_name',
                'module_code',
                'event_type',
                'entity_type',
                'entity_id',
                'description',
                'source_ip',
                'user_agent',
                'metadata',
            ]],
            $events->map(fn (AuditEvent $event): array => [
                $event->created_at?->format('Y-m-d H:i:s'),
                $event->actor?->username,
                $event->actor?->name,
                $event->module_code,
                $event->event_type,
                $event->entity_type,
                $event->entity_id,
                $event->description,
                $event->source_ip,
                $event->user_agent,
                $event->metadata ? json_encode($event->metadata, JSON_UNESCAPED_UNICODE) : null,
            ])->all(),
        );
    }

    private function filteredQuery(array $filters): Builder
    {
        return AuditEvent::query()
            ->when($filters['q'] ?? null, function (Builder $query, string $value): void {
                $term = trim($value);

                $query->where(function (Builder $innerQuery) use ($term): void {
                    $innerQuery
                        ->where('module_code', 'like', "%{$term}%")
                        ->orWhere('event_type', 'like', "%{$term}%")
                        ->orWhere('entity_type', 'like', "%{$term}%")
                        ->orWhere('entity_id', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('source_ip', 'like', "%{$term}%")
                        ->orWhereHas('actor', fn (Builder $actorQuery) => $actorQuery
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('username', 'like', "%{$term}%"));
                });
            })
            ->when($filters['module_code'] ?? null, fn (Builder $query, string $moduleCode) => $query->where('module_code', $moduleCode))
            ->when($filters['event_type'] ?? null, fn (Builder $query, string $eventType) => $query->where('event_type', $eventType))
            ->when($filters['entity_type'] ?? null, fn (Builder $query, string $entityType) => $query->where('entity_type', $entityType))
            ->when($filters['actor_username'] ?? null, fn (Builder $query, string $actorUsername) => $query->whereHas('actor', fn (Builder $actorQuery) => $actorQuery->where('username', $actorUsername)))
            ->when($filters['source_ip'] ?? null, fn (Builder $query, string $sourceIp) => $query->where('source_ip', $sourceIp))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $dateTo) => $query->whereDate('created_at', '<=', $dateTo));
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

