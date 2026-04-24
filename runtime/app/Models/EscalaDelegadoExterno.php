<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscalaDelegadoExterno extends Model
{
    protected $table = 'escalas_delegados_externos';

    protected $fillable = [
        'nome_completo',
        'nome_simplificado',
        'unidade',
        'contato',
        'telefone',
        'obs',
        'is_active',
        'legacy_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Retorna o nome que aparece na escala (simplificado se houver, senão completo). */
    public function getLabelAttribute(): string
    {
        return trim((string) $this->nome_simplificado) !== ''
            ? $this->nome_simplificado
            : $this->nome_completo;
    }

    public function scopeAtivos($query)
    {
        return $query->where('is_active', true)->orderBy('nome_simplificado');
    }
}
