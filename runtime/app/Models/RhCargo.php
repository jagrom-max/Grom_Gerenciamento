<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhCargo extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'rh_cargos';

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function funcionarios(): HasMany
    {
        return $this->hasMany(RhFuncionario::class, 'cargo_id');
    }
}
