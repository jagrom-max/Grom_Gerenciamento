<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperacionalOrdemServico extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'operacional_ordens_servico';

    protected $fillable = [
        'numero',
        'data_emissao',
        'data_prazo',
        'cartorio_id',
        'solicitante',
        'tipo',
        'assunto',
        'descricao',
        'status',
        'data_conclusao',
        'responsavel',
        'resultado',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_motivo',
    ];

    protected $casts = [
        'data_emissao'   => 'date',
        'data_prazo'     => 'date',
        'data_conclusao' => 'date',
    ];

    const TIPOS = [
        'Administrativa',
        'Técnica',
        'Manutenção',
        'Segurança',
        'Infraestrutura',
        'Serviços Gerais',
        'Outra',
    ];

    const STATUSES = [
        'Aberta',
        'Em andamento',
        'Aguardando',
        'Concluída',
        'Cancelada',
    ];

    const STATUS_TONOS = [
        'Aberta'        => 'warn',
        'Em andamento'  => '',
        'Aguardando'    => 'warn',
        'Concluída'     => 'good',
        'Cancelada'     => 'danger',
    ];

    public function getStatusToneAttribute(): string
    {
        return self::STATUS_TONOS[$this->status] ?? '';
    }

    public function getEstaAtrasadaAttribute(): bool
    {
        return $this->data_prazo
            && ! in_array($this->status, ['Concluída', 'Cancelada'])
            && $this->data_prazo->isPast();
    }
}
