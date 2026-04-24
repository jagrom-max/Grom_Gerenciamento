<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperacionalObjeto extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'operacional_objetos';

    protected $fillable = [
        'rdo_num', 'ano', 'lacre',
        'ip_tc_ddm', 'ip_externo',
        'tipo_objeto', 'objeto', 'quantidade', 'unidade',
        'marca', 'modelo', 'cor', 'numero_serie',
        'ic_remessa', 'ic_retorno', 'lacre_ic', 'laudo',
        'local_custodia_id', 'caixa',
        'situacao',
        'dest_solicitado', 'dest_data_solicitado',
        'dest_autorizado', 'dest_data_autorizado',
        'dest_status', 'dest_data',
        'observacoes',
        'created_by', 'updated_by', 'deleted_by', 'deleted_motivo',
        'legacy_id',
    ];

    protected $casts = [
        'ano'                   => 'integer',
        'quantidade'            => 'integer',
        'dest_data_solicitado'  => 'date',
        'dest_data_autorizado'  => 'date',
        'dest_data'             => 'date',
    ];

    // -------------------------------------------------------
    // Constantes canônicas
    // -------------------------------------------------------
    public const SITUACOES = [
        'Em Custódia',
        'Enviado IC',
        'Aguardando Destinação',
        'Restituído',
        'Destruído',
        'Encerrado',
    ];

    public const DEST_STATUSES = [
        'Solicitado',
        'Autorizado',
        'Concluído',
        'Indeferido',
    ];

    // -------------------------------------------------------
    // Relacionamentos
    // -------------------------------------------------------
    public function localCustodia(): BelongsTo
    {
        return $this->belongsTo(OperacionalObjetoLocal::class, 'local_custodia_id');
    }

    // -------------------------------------------------------
    // Accessors
    // -------------------------------------------------------
    public function getRdoFormatadoAttribute(): string
    {
        if (! $this->rdo_num) {
            return '—';
        }
        return $this->ano ? "{$this->rdo_num}/{$this->ano}" : $this->rdo_num;
    }

    public function getSituacaoLabelAttribute(): string
    {
        return $this->situacao ?: 'Em Custódia';
    }

    public function getLocalNomeAttribute(): string
    {
        return $this->localCustodia?->nome ?? '—';
    }

    public function getEmCustodiaAttribute(): bool
    {
        return $this->situacao === 'Em Custódia' || $this->situacao === 'Enviado IC';
    }
}
