<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cabeçalho de versão de uma escala mensal.
 *
 * Uma versão inicia como "provisoria" (auditável/editável) e é
 * homologada para "definitiva" pelo responsável. Emendas parciais
 * geram uma nova versão, preservando o histórico imutável.
 *
 * @property int           $id
 * @property int           $ano
 * @property int           $mes
 * @property int           $versao
 * @property string        $status    provisoria|definitiva
 * @property string|null   $obs
 * @property string|null   $created_by
 * @property string|null   $fechada_por
 * @property Carbon|null   $fechada_em
 */
class EscalaVersao extends Model
{
    protected $table = 'escalas_versoes';

    protected $fillable = [
        'ano',
        'mes',
        'versao',
        'status',
        'obs',
        'created_by',
        'fechada_por',
        'fechada_em',
        'conferida_em', // NOVO: data/hora da conferência obrigatória
    ];

    protected $casts = [
        'ano'        => 'integer',
        'mes'        => 'integer',
        'versao'     => 'integer',
        'fechada_em' => 'datetime',
        'conferida_em' => 'datetime',
    ];
    /** Marca a versão como conferida (tela ou PDF). */
    public function marcarConferida(): void
    {
        $this->conferida_em = now();
        $this->save();
    }

    /** Indica se a versão já foi conferida. */
    public function getEhConferidaAttribute(): bool
    {
        return !empty($this->conferida_em);
    }

    // -------------------------------------------------------
    // Query helpers
    // -------------------------------------------------------

    /** Retorna o cabeçalho da versão mais recente de um mês. */
    public static function maisRecente(int $ano, int $mes): ?static
    {
        return static::query()
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->orderByDesc('versao')
            ->first();
    }

    /**
     * Retorna (ou cria) o cabeçalho da versão ativa para o mês.
     * Se a versão corrente for definitiva, mantém (não cria nova automaticamente).
     */
    public static function ativaOuCriar(int $ano, int $mes, int $versao, ?string $userId = null): static
    {
        return static::firstOrCreate(
            ['ano' => $ano, 'mes' => $mes, 'versao' => $versao],
            ['status' => 'provisoria', 'created_by' => $userId]
        );
    }

    // -------------------------------------------------------
    // Accessors
    // -------------------------------------------------------

    public function getEhProvisoriaAttribute(): bool
    {
        return $this->status === 'provisoria';
    }

    public function getEhDefinitivaAttribute(): bool
    {
        return $this->status === 'definitiva';
    }

    /** Label de exibição. */
    public function getLabelAttribute(): string
    {
        return sprintf(
            'v%d — %s',
            $this->versao,
            $this->status === 'definitiva' ? 'Definitiva' : 'Provisória'
        );
    }
}
