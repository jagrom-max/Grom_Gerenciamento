<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnaliseFlagramtePendencia extends Model
{
    use HasUuids;

    protected $table = 'analise_flagrante_pendencias';

    protected $fillable = [
        'import_source',
        'spj',
        'spj_prefix',
        'spj_year',
        'data_ocorrencia',
        'lavrado',
        'area_fato',
        'naturezas',
        'num_ip',
        'mpu_numero',
        'cartorio_ip_planilha',
        'status',
        'cartorio_id',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'spj_year'    => 'integer',
        'reviewed_at' => 'datetime',
    ];

    // ── Escopos ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReviewed($query)
    {
        return $query->whereIn('status', ['approved', 'corrected', 'dismissed']);
    }

    // ── Relações ──────────────────────────────────────────────────────────────

    public function cartorio(): BelongsTo
    {
        return $this->belongsTo(Cartorio::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'   => 'Pendente',
            'approved'  => 'Aprovado',
            'corrected' => 'Corrigido',
            'dismissed' => 'Dispensado',
            default     => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending'   => '#f59e0b',
            'approved'  => '#10b981',
            'corrected' => '#3b82f6',
            'dismissed' => '#6b7280',
            default     => '#6b7280',
        };
    }
}
