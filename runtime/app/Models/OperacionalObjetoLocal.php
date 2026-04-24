<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperacionalObjetoLocal extends Model
{

    protected $table = 'operacional_objetos_locais';

    protected $fillable = ['nome', 'is_active', 'legacy_id'];

    protected $casts = ['is_active' => 'boolean'];

    public function objetos(): HasMany
    {
        return $this->hasMany(OperacionalObjeto::class, 'local_custodia_id');
    }

    public static function ativos()
    {
        return static::query()->where('is_active', true)->orderBy('nome');
    }
}
