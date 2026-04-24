<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CartorioManagerHistory extends Model
{
    public $timestamps = false;

    protected $table = 'cartorio_manager_history';

    protected $fillable = [
        'cartorio_id',
        'manager_name',
        'started_at',
        'ended_at',
        'reason',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at'   => 'date',
        'changed_at' => 'datetime',
    ];

    // Registros abertos (sem data de término)
    public function scopeVigente(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isVigente(): bool
    {
        return $this->ended_at === null;
    }

    public function cartorio(): BelongsTo
    {
        return $this->belongsTo(Cartorio::class);
    }
}
