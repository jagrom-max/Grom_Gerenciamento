<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogger
{
    public static function log(
        string $moduleCode,
        string $eventType,
        string $entityType,
        string|int $entityId,
        ?string $description = null,
        array $metadata = []
    ): void {
        try {
            AuditEvent::query()->create([
                'actor_user_id' => Auth::id(),
                'module_code' => $moduleCode,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => (string) $entityId,
                'description' => $description,
                'source_ip' => request()?->ip(),
                'user_agent' => (string) str((string) request()?->userAgent())->limit(1000),
                'metadata' => $metadata,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Falha ao persistir evento de auditoria do Grom.Seg.', [
                'module_code' => $moduleCode,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => (string) $entityId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

