<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalaPlantaoExterno extends Model
{
    use HasFactory;

    protected $table = 'escalas_plantoes_externos';

    protected $fillable = [
        'nome',
        'sigla',
        'unidade',
        'regra',
        'observacao',
        'is_active',
        'legacy_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Regras válidas conforme modelo legado */
    public const REGRAS = ['AMBOS', 'MESMO_DIA', 'DIA_SEGUINTE'];

    public function atribuicoes(): HasMany
    {
        return $this->hasMany(EscalaPlantaoFuncionario::class, 'plantao_externo_id');
    }

    public function scopeAtivos($query)
    {
        return $query->where('is_active', true)->orderBy('nome');
    }
}
