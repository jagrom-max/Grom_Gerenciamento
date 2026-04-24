<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RhDelegadoExternoPeriodo extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'rh_delegado_externo_periodos';

    protected $keyType = 'string';

    protected $fillable = [
        'delegado_externo_id',
        'motivo',
        'start_date',
        'end_date',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
    ];

    public function delegadoExterno(): BelongsTo
    {
        return $this->belongsTo(RhDelegadoExterno::class, 'delegado_externo_id');
    }

    public function durationInDays(): ?int
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function statusLabel(): string
    {
        $today = Carbon::today();

        if ($this->is_active
            && $this->start_date?->lessThanOrEqualTo($today)
            && ($this->end_date === null || $this->end_date?->greaterThanOrEqualTo($today))) {
            return 'Em vigor';
        }

        if ($this->is_active && $this->start_date?->greaterThan($today)) {
            return 'Agendado';
        }

        return $this->is_active ? 'Encerrado' : 'Inativo';
    }

    public function statusTone(): string
    {
        return $this->statusLabel() === 'Em vigor' ? 'good' : 'warn';
    }
}
