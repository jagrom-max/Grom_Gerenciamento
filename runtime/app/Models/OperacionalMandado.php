<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperacionalMandado extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'operacional_mandados';

    protected $fillable = [
        'tipo_sigla',
        'tipo_mandado',
        'subtipo_prisao',
        'cnj_numero',
        'vara',
        'nome',
        'cpf',
        'rg',
        'data_emissao',
        'validade',
        'tipificacao_penal',
        'artigo',
        'paragrafo',
        'tipificacoes_extra',
        'pena_anos',
        'pena_meses',
        'pena_dias',
        'regime',
        'procedimento',
        'cumprido_por',
        'data_cumprimento',
        'bo_numero',
        'observacoes',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_motivo',
        'legacy_id',
    ];

    protected $casts = [
        'tipificacoes_extra' => 'array',
        'data_emissao'       => 'date',
        'validade'           => 'date',
        'data_cumprimento'   => 'date',
        'pena_anos'          => 'integer',
        'pena_meses'         => 'integer',
        'pena_dias'          => 'integer',
    ];

    // -------------------------------------------------------
    // Constantes canônicas (espelham aba_mandados.py)
    // -------------------------------------------------------
    public const TIPOS_SIGLA = [
        'MPP' => 'Mandado de Prisão Preventiva',
        'MPT' => 'Mandado de Prisão Temporária',
        'MPD' => 'Mandado de Prisão Definitiva',
        'MPC' => 'Mandado de Prisão Civil',
        'MBA' => 'Mandado de Busca e Apreensão de Objetos',
        'MAM' => 'Mandado de Apreensão de Menor',
    ];

    // sigla -> [tipo_mandado, subtipo_prisao|null]
    public const SIGLA_PARA_TIPO = [
        'MPP' => ['Mandado de Prisão', 'Preventivo'],
        'MPT' => ['Mandado de Prisão', 'Temporário'],
        'MPD' => ['Mandado de Prisão', 'Definitivo'],
        'MPC' => ['Mandado de Prisão', 'Civil'],
        'MBA' => ['Busca e Apreensão de Objetos', null],
        'MAM' => ['Mandado de Apreensão de Menor', null],
    ];

    public const PROCEDIMENTOS = ['Em Aberto', 'Cumprido', 'Revogado'];
    public const CUMPRIDO_POR  = ['DDM', 'PM', 'GCM', 'Polícia Civil'];
    public const REGIMES       = ['Aberto', 'Semiaberto', 'Fechado'];

    // Leis mais frequentes em DDM / delegacias especializadas
    public const LEIS = [
        'CP'             => 'Código Penal (Dec.-Lei 2.848/1940)',
        'CPP'            => 'Código de Processo Penal (Dec.-Lei 3.689/1941)',
        '11.340/2006'    => 'Lei Maria da Penha (11.340/2006)',
        '8.069/1990'     => 'Estatuto da Criança e do Adolescente — ECA (8.069/1990)',
        '9.605/1998'     => 'Lei de Crimes Ambientais (9.605/1998)',
        '11.343/2006'    => 'Lei de Drogas (11.343/2006)',
        '10.741/2003'    => 'Estatuto do Idoso (10.741/2003)',
        '9.099/1995'     => 'Lei dos Juizados Especiais (9.099/1995)',
        '12.037/2009'    => 'Lei de Identificação Criminal (12.037/2009)',
        '12.850/2013'    => 'Lei de Organizações Criminosas (12.850/2013)',
        '13.869/2019'    => 'Lei de Abuso de Autoridade (13.869/2019)',
        '13.964/2019'    => 'Pacote Anticrime (13.964/2019)',
        '14.344/2022'    => 'Lei Henry Borel (14.344/2022)',
        'OUTRA'          => 'Outra (descrever no campo Artigo)',
    ];

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------
    public function getLabelTipoAttribute(): string
    {
        return self::TIPOS_SIGLA[$this->tipo_sigla] ?? $this->tipo_mandado ?? '';
    }

    public function getDescricaoTipoComSiglaAttribute(): string
    {
        $label = self::TIPOS_SIGLA[$this->tipo_sigla] ?? $this->tipo_sigla;
        return "{$this->tipo_sigla} — {$label}";
    }

    public function getCpfFormatadoAttribute(): string
    {
        $c = $this->cpf ?? '';
        if (strlen($c) !== 11) {
            return $c;
        }
        return substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9, 2);
    }

    public function getPenaFormatadaAttribute(): string
    {
        $partes = [];
        if ($this->pena_anos > 0) {
            $partes[] = "{$this->pena_anos} ano(s)";
        }
        if ($this->pena_meses > 0) {
            $partes[] = "{$this->pena_meses} mês(es)";
        }
        if ($this->pena_dias > 0) {
            $partes[] = "{$this->pena_dias} dia(s)";
        }
        return $partes ? implode(', ', $partes) : '—';
    }

    public function getEstaVencidoAttribute(): bool
    {
        if ($this->procedimento !== 'Em Aberto') {
            return false;
        }
        return $this->validade !== null && $this->validade->isPast();
    }

    public function getCumpridoPorExibicaoAttribute(): string
    {
        return match ($this->cumprido_por) {
            'PM', 'GCM' => 'PM/GCM',
            'Polícia Civil' => 'PCSP',
            default => $this->cumprido_por ?: '—',
        };
    }

    public function getProcedimentoResumoAttribute(): string
    {
        if ($this->procedimento !== 'Cumprido') {
            return $this->procedimento ?: '—';
        }

        $partes = ['Cumprido'];

        if ($this->cumprido_por_exibicao !== '—') {
            $partes[] = $this->cumprido_por_exibicao;
        }

        if ($this->data_cumprimento) {
            $partes[] = $this->data_cumprimento->format('d/m/Y');
        }

        if ($this->bo_numero) {
            $partes[] = 'BO '.$this->bo_numero;
        }

        return implode(' | ', $partes);
    }

    // -------------------------------------------------------
    // Derivação de sigla a partir dos campos legados
    // -------------------------------------------------------
    public static function siglaFromLegacy(string $tipoMandado, ?string $subtipoPrisao): string
    {
        $t  = mb_strtoupper(self::removeDiacritics($tipoMandado));
        $sp = mb_strtoupper(self::removeDiacritics($subtipoPrisao ?? ''));

        if (str_contains($t, 'PRIS')) {
            if (str_contains($sp, 'PREVENT')) return 'MPP';
            if (str_contains($sp, 'TEMPOR'))  return 'MPT';
            if (str_contains($sp, 'DEFIN') || str_contains($sp, 'DEF')) return 'MPD';
            if (str_contains($sp, 'CIVIL'))   return 'MPC';
            return 'MPP'; // fallback prisão sem subtipo -> preventivo
        }
        if (str_contains($t, 'MENOR') || str_contains($t, 'APREENSO') && str_contains($t, 'MENOR')) return 'MAM';
        if (str_contains($t, 'BUSCA') || str_contains($t, 'APREENSO') || str_contains($t, 'OBJETO')) return 'MBA';

        return mb_strtoupper(mb_substr($tipoMandado, 0, 3)) ?: '???';
    }

    private static function removeDiacritics(string $str): string
    {
        return \Normalizer::normalize($str, \Normalizer::FORM_D) !== false
            ? preg_replace('/\p{Mn}/u', '', \Normalizer::normalize($str, \Normalizer::FORM_D))
            : $str;
    }
}
