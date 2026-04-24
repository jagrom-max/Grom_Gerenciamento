<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    public function __invoke(Request $request): View
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

        $filteredQuery = $this->filteredQuery($filters);
        $events = (clone $filteredQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->with('actor')
            ->paginate(25)
            ->withQueryString();

        $summaryQuery = $this->filteredQuery($filters);

        return view('auditoria.index', [
            'filters' => $filters,
            'events' => $events,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'modules' => (clone $summaryQuery)->distinct('module_code')->count('module_code'),
                'actors' => (clone $summaryQuery)->whereNotNull('actor_user_id')->distinct('actor_user_id')->count('actor_user_id'),
                'latest' => (clone $summaryQuery)->max('created_at'),
            ],
            'eventTypes' => (clone $summaryQuery)
                ->selectRaw('event_type, COUNT(*) as total')
                ->groupBy('event_type')
                ->orderByDesc('total')
                ->orderBy('event_type')
                ->limit(8)
                ->get(),
            'moduleBreakdown' => (clone $summaryQuery)
                ->selectRaw('module_code, COUNT(*) as total')
                ->groupBy('module_code')
                ->orderByDesc('total')
                ->orderBy('module_code')
                ->limit(8)
                ->get(),
        ]);
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
}
