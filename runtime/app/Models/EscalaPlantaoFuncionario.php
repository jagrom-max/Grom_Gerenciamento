<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalaPlantaoFuncionario extends Model
{
    use HasFactory;

    protected $table = 'escalas_plantoes_funcionarios';

    protected $fillable = [
        'data',
        'funcionario_id',
        'plantao_externo_id',
        'created_by',
        'legacy_id',
    ];

    protected $casts = [
        'data' => 'date',
    ];

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(RhFuncionario::class, 'funcionario_id');
    }

    public function plantaoExterno(): BelongsTo
    {
        return $this->belongsTo(EscalaPlantaoExterno::class, 'plantao_externo_id');
    }

    /** Label no formato "Marina (PLD)" para exibição na escala. */
    public function getLabelAttribute(): string
    {
        $nome = $this->funcionario?->nome_simplificado
            ?: ($this->funcionario?->name ?? '—');
        $sigla = $this->plantaoExterno?->sigla ?? '';
        return $sigla ? "{$nome} ({$sigla})" : $nome;
    }
}
