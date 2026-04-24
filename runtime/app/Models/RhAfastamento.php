<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RhAfastamento extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'rh_afastamentos';

    protected $keyType = 'string';

    protected $fillable = [
        'funcionario_id',
        'reason',
        'start_date',
        'end_date',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(RhFuncionario::class, 'funcionario_id');
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

    public function durationInDays(): ?int
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function isFerias(): bool
    {
        return Str::of($this->reason)
            ->ascii()
            ->lower()
            ->contains('ferias');
    }
}
