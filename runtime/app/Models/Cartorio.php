<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cartorio extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'number',
        'code',
        'name',
        'designacao',
        'manager_name',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function monthlyStats(): HasMany
    {
        return $this->hasMany(ProductivityStatMonthly::class);
    }

    public function importItems(): HasMany
    {
        return $this->hasMany(ImportItem::class);
    }

    public function flagrantes(): HasMany
    {
        return $this->hasMany(ProductivityFlagrante::class);
    }

    public function boletins(): HasMany
    {
        return $this->hasMany(ProductivityBoletim::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(CartorioStatusHistory::class);
    }

    public function managerHistory(): HasMany
    {
        return $this->hasMany(CartorioManagerHistory::class)->orderByDesc('started_at');
    }

    public function currentDesignacao(): ?CartorioManagerHistory
    {
        return $this->managerHistory()->whereNull('ended_at')->first();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $scopeKeys = $user->scopeKeys('cartorio');

        if ($scopeKeys->isEmpty()) {
            return $query;
        }

        return $query->whereIn('id', $scopeKeys->all());
    }
}
