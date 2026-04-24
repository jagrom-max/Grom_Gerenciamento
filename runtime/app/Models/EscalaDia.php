<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class EscalaDia extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'escalas_dias';

    protected $fillable = [
        'data',
        'mes',
        'ano',
        'versao',
        'is_fechada',
        'escrivao',
        'operacional',
        'fechar_nome',
        'delegada',
        'plantao_externo',
        'created_by',
        'updated_by',
        'legacy_id',
    ];

    protected $casts = [
        'data'       => 'date',
        'mes'        => 'integer',
        'ano'        => 'integer',
        'versao'     => 'integer',
        'is_fechada' => 'boolean',
    ];

    // -------------------------------------------------------
    // Accessors
    // -------------------------------------------------------

    /** Dia da semana abreviado em pt-BR: Seg, Ter, Qua… */
    public function getDiaSemanaAttribute(): string
    {
        return $this->data
            ? $this->data->locale('pt_BR')->isoFormat('ddd')
            : '';
    }

    /** Indica se é final de semana (sáb/dom). */
    public function getIsFinanciaSemanaAttribute(): bool
    {
        return $this->data
            ? in_array($this->data->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true)
            : false;
    }

    /** Retorna label compacto: "01/04 Ter" */
    public function getDataLabelAttribute(): string
    {
        return $this->data
            ? $this->data->format('d/m') . ' ' . $this->dia_semana
            : '';
    }

    // -------------------------------------------------------
    // Query helpers
    // -------------------------------------------------------

    /**
     * Retorna todos os dias de um determinado mês/ano na versão máxima disponível.
     */
    public static function diasDoMes(int $ano, int $mes): \Illuminate\Database\Eloquent\Collection
    {
        $versao = static::query()
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->max('versao');

        if ($versao === null) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return static::query()
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->where('versao', $versao)
            ->orderBy('data')
            ->get();
    }
}
