<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscalaSubstituicaoDdm extends Model
{
    protected $table = 'escalas_substituicoes_ddm';
    protected $fillable = [
        'delegado_externo_id',
        'data_inicio',
        'data_fim',
        'motivo',
    ];
    public $timestamps = false;
}
