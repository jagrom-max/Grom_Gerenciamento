<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RhDelegadoExterno extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'rh_delegados_externos';

    protected $keyType = 'string';

    protected $fillable = [
        'registration_code',
        'name',
        'origin_unit',
        'role_title',
        'contact',
        'email',
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

    public function periodos(): HasMany
    {
        return $this->hasMany(RhDelegadoExternoPeriodo::class, 'delegado_externo_id')
            ->orderByDesc('start_date');
    }

    public function currentPeriodo(): ?RhDelegadoExternoPeriodo
    {
        $today = Carbon::today();

        return $this->periodos()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->where(function ($q) use ($today): void {
                $q->whereNull('end_date')
                  ->orWhereDate('end_date', '>=', $today);
            })
            ->first();
    }
}
