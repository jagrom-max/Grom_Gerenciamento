<?php

namespace App\Services\Escalas;

use App\Models\EscalaDia;
use App\Models\EscalaPlantaoFuncionario;
use App\Models\EscalaVersao;
use App\Models\RhAfastamento;
use App\Models\RhFuncionario;
use App\Models\RhHoliday;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gera a escala mensal provisória automaticamente.
 *
 * Regras:
 *  - Percorre apenas dias úteis (seg–sex) do mês.
 *  - Para cada dia, calcula quais funcionários estão IMPEDIDOS:
 *      · em afastamento ativo que cruza a data
 *      · com plantão externo na data (MESMO_DIA ou AMBOS)
 *      · com plantão externo no dia ANTERIOR (DIA_SEGUINTE ou AMBOS)
 *  - Atribui por regras de distribuição por cargo:
 *      · Delegada(o)  → cargo LEG-001        → 1 slot (delegada)
 *      · Escrivã(o)   → cargo LEG-005/RH-001 → 1 slot (escrivao)
 *      · Operacional  → cargo LEG-002/LEG-003 → 1 slot (operacional)
 *      · Fechar       → pool proporcional de escrivães + operacionais
 *  - Delegada(o): fixa(o) no titular da DDM do cadastro RH (LEG-001);
 *    em afastamento/impedimento, fica NULL para atribuição manual via Delegado Externo.
 *  - Se outros slots não tiverem disponíveis, o campo fica NULL + alerta.
 *
 * A geração é idempotente: se já existir qualquer dia na versão alvo, aborta.
 */
class GeradorEscalaMensalService
{
    // Códigos de cargo por papel na escala
    private const CARGO_DELEGADA    = ['LEG-001'];
    private const CARGO_ESCRIVAO    = ['LEG-005', 'RH-001'];
    private const CARGO_OPERACIONAL = ['LEG-002', 'LEG-003'];

    // Como o plantão bloqueia dias
    private const REGRA_MESMO_DIA   = 'MESMO_DIA';
    private const REGRA_DIA_SEGUINTE = 'DIA_SEGUINTE';
    private const REGRA_AMBOS       = 'AMBOS';

    /**
     * Gera a escala provisória para o mês.
     *
     * @return array{versao: int, dias_criados: int, alertas: string[]}
     */
    public function gerar(int $ano, int $mes, string $userId): array
    {
        // Validação de plantões externos antes de gerar a escala
        $validador = new ValidadorPlantoesExternosService();
        $conflitos = $validador->validar($ano, $mes);
        if (!empty($conflitos)) {
            return [
                'versao' => null,
                'dias_criados' => 0,
                'alertas' => ['Conflitos encontrados nos plantões externos. Geração abortada.'],
                'conflitos' => $conflitos,
            ];
        }

        return DB::transaction(function () use ($ano, $mes, $userId): array {
            // ── Versão alvo (com lock para evitar corrida) ─────────────────
            $ultimaVersao = EscalaDia::withTrashed()
                ->where('ano', $ano)
                ->where('mes', $mes)
                ->orderByDesc('versao')
                ->lockForUpdate()
                ->value('versao') ?? 0;

            $novaVersao = $ultimaVersao + 1;

            // Guarda de idempotência: versão atual não pode já estar provisória
            if ($ultimaVersao > 0) {
                $header = EscalaVersao::query()
                    ->where('ano', $ano)
                    ->where('mes', $mes)
                    ->where('versao', $ultimaVersao)
                    ->orderByDesc('versao')
                    ->lockForUpdate()
                    ->first();

                $diasAtivosUltimaVersao = EscalaDia::query()
                    ->where('ano', $ano)
                    ->where('mes', $mes)
                    ->where('versao', $ultimaVersao)
                    ->count();

                if ($diasAtivosUltimaVersao > 0 && (! $header || $header->status !== 'definitiva')) {
                    throw new \RuntimeException(
                        "Ja existe uma escala provisoria ou incompleta (v{$ultimaVersao}) para este mes. ".
                        "Grave-a como definitiva antes de gerar novamente."
                    );
                }

                if ($header && $header->status === 'provisoria') {
                    throw new \RuntimeException(
                        "Já existe uma escala PROVISÓRIA (v{$ultimaVersao}) para este mês. ".
                        "Grave-a como definitiva antes de gerar novamente."
                    );
                }
            }

            // ── Funcionários elegíveis ─────────────────────────────────────
            $funcionarios = RhFuncionario::query()
                ->with(['cargo', 'afastamentos' => fn ($q) => $q->where('is_active', true)])
                ->where('is_active', true)
                ->where('concorre_escala', true)
                ->get();

            // Classificar por papel
            $delegadas    = $this->filtrarPorCargo($funcionarios, self::CARGO_DELEGADA);
            $escrivaos    = $this->filtrarPorCargo($funcionarios, self::CARGO_ESCRIVAO);
            $operacionais = $this->filtrarPorCargo($funcionarios, self::CARGO_OPERACIONAL);
            $delegadaTitular = $delegadas->sortBy('name')->first();

            // ── Plantões externos do mês ──────────────────────────────────
            $primeiroDia = Carbon::create($ano, $mes, 1);
            $ultimoDia   = $primeiroDia->copy()->endOfMonth();
            $feriados = RhHoliday::query()
                ->where('is_active', true)
                ->whereDate('holiday_date', '>=', $primeiroDia->toDateString())
                ->whereDate('holiday_date', '<=', $ultimoDia->toDateString())
                ->pluck('holiday_date')
                ->map(fn ($date): string => Carbon::parse($date)->toDateString())
                ->flip()
                ->all();

            // Carrega todos os plantões do mês (+1 dia antes para DIA_SEGUINTE)
            $plantoesMes = EscalaPlantaoFuncionario::query()
                ->with(['plantaoExterno', 'funcionario'])
                ->whereBetween('data', [
                    $primeiroDia->copy()->subDay()->toDateString(),
                    $ultimoDia->toDateString(),
                ])
                ->orderBy('data')
                ->orderBy('funcionario_id')
                ->get();

            // Monta mapa: data_string → [ funcionario_id → ['regra' => …, 'sigla' => …] ]
            // A sigla é necessária para aplicar a exceção PLD→DEL do legado.
            $plantaoMapa = [];
            foreach ($plantoesMes as $p) {
                $plantaoMapa[$p->data->toDateString()][$p->funcionario_id] = [
                    'regra' => $p->plantaoExterno?->regra ?? self::REGRA_MESMO_DIA,
                    'sigla' => strtoupper(trim((string) ($p->plantaoExterno?->sigla ?? ''))),
                ];
            }

            // ── Geração dia a dia ─────────────────────────────────────────
            $alertas     = [];
            $diasCriados = 0;

            // ── Equidade: contadores de atribuição por funcionário ─────────
            // Contagem de dias atribuídos no mês
            $contagemEsc    = $escrivaos->pluck('id')->mapWithKeys(fn ($id) => [$id => 0])->all();
            $contagemOp     = $operacionais->pluck('id')->mapWithKeys(fn ($id) => [$id => 0])->all();
            // Fechar: pool global = escrivães + operacionais (contagem compartilhada)
            $poolFechar     = $escrivaos->merge($operacionais)->unique('id')->values();
            $contagemFechar = $poolFechar->pluck('id')->mapWithKeys(fn ($id) => [$id => 0])->all();

            // Dias elegíveis no mês (dias úteis e sem impedimento):
            // base para equidade proporcional em caso de férias/afastamentos.
            $diasElegiveisEsc    = $escrivaos->pluck('id')->mapWithKeys(fn ($id) => [$id => 0])->all();
            $diasElegiveisOp     = $operacionais->pluck('id')->mapWithKeys(fn ($id) => [$id => 0])->all();
            $diasElegiveisFechar = $poolFechar->pluck('id')->mapWithKeys(fn ($id) => [$id => 0])->all();

            $diaElegibilidade = $primeiroDia->copy();
            while ($diaElegibilidade->lte($ultimoDia)) {
                if ($diaElegibilidade->isWeekend() || isset($feriados[$diaElegibilidade->toDateString()])) {
                    $diaElegibilidade->addDay();
                    continue;
                }

                $dataRef     = $diaElegibilidade->toDateString();
                $anteriorRef = $diaElegibilidade->copy()->subDay()->toDateString();
                $impedidosDia = array_flip($this->calcularImpedidos(
                    $diaElegibilidade,
                    $funcionarios,
                    $plantaoMapa,
                    $dataRef,
                    $anteriorRef
                ));

                foreach ($escrivaos as $f) {
                    if (! isset($impedidosDia[$f->id])) {
                        $diasElegiveisEsc[$f->id] = ($diasElegiveisEsc[$f->id] ?? 0) + 1;
                    }
                }

                foreach ($operacionais as $f) {
                    if (! isset($impedidosDia[$f->id])) {
                        $diasElegiveisOp[$f->id] = ($diasElegiveisOp[$f->id] ?? 0) + 1;
                    }
                }

                foreach ($poolFechar as $f) {
                    if (! isset($impedidosDia[$f->id])) {
                        $diasElegiveisFechar[$f->id] = ($diasElegiveisFechar[$f->id] ?? 0) + 1;
                    }
                }

                $diaElegibilidade->addDay();
            }

            // Último dia útil atribuído (Carbon|null) — anti-consecutivo
            $ultimoDiaEsc    = $escrivaos->pluck('id')->mapWithKeys(fn ($id) => [$id => null])->all();
            $ultimoDiaOp     = $operacionais->pluck('id')->mapWithKeys(fn ($id) => [$id => null])->all();
            $ultimoDiaFechar = $poolFechar->pluck('id')->mapWithKeys(fn ($id) => [$id => null])->all();

            // Contagem por dia da semana (1=Seg…7=Dom) — anti-vício de dia fixo
            $weekdayEsc    = $escrivaos->pluck('id')->mapWithKeys(fn ($id) => [$id => array_fill(1, 7, 0)])->all();
            $weekdayOp     = $operacionais->pluck('id')->mapWithKeys(fn ($id) => [$id => array_fill(1, 7, 0)])->all();
            $weekdayFechar = $poolFechar->pluck('id')->mapWithKeys(fn ($id) => [$id => array_fill(1, 7, 0)])->all();

            if ($delegadas->isEmpty()) {
                $alertas[] = 'Sem funcionários elegíveis para o papel delegada(o) (cargo LEG-001).';
            }
            if ($escrivaos->isEmpty()) {
                $alertas[] = 'Sem funcionários elegíveis para os papéis escrivão/fechar (cargos LEG-005 ou RH-001).';
            }
            if ($operacionais->isEmpty()) {
                $alertas[] = 'Sem funcionários elegíveis para o papel operacional (cargos LEG-002 ou LEG-003).';
            }

            $dia = $primeiroDia->copy();
            while ($dia->lte($ultimoDia)) {
                // Pula dias sem expediente: sabados, domingos e feriados cadastrados.
                if ($dia->isWeekend() || isset($feriados[$dia->toDateString()])) {
                    $dia->addDay();
                    continue;
                }

                $dataStr     = $dia->toDateString();
                $anteriorStr = $dia->copy()->subDay()->toDateString();

                // ── Quem está impedido neste dia ──────────────────────────
                $impedidos = $this->calcularImpedidos(
                    $dia,
                    $funcionarios,
                    $plantaoMapa,
                    $dataStr,
                    $anteriorStr
                );

                // ── Plantão ext. textual do dia (campo plantao_externo) ──
                $plantaoTexto = $this->resumoPlantaoTextual($plantoesMes, $dataStr);

                // ── Delegada(o) ───────────────────────────────────────────
                $delegadaFunc = ($delegadaTitular && ! in_array($delegadaTitular->id, $impedidos, true))
                    ? $delegadaTitular
                    : null;
                $delegadaNome = $delegadaFunc ? ($delegadaFunc->short_name ?: $delegadaFunc->name) : null;

                if ($delegadaNome === null && $delegadas->isNotEmpty()) {
                    $alertas[] = "{$dataStr}: delegada(o) titular impedida/afastada; atribuir manualmente Delegado Externo.";
                }

                // ── Escrivão ──────────────────────────────────────────────
                $escrivaoFunc = $this->selecionarComEquidade(
                    $escrivaos, $impedidos, $contagemEsc, $ultimoDiaEsc, $weekdayEsc, $diasElegiveisEsc, $dia
                );
                $escrivaoNome = $escrivaoFunc ? ($escrivaoFunc->short_name ?: $escrivaoFunc->name) : null;

                if ($escrivaoNome === null) {
                    $alertas[] = "{$dataStr}: escrivã(o) sem alternativa disponível.";
                }

                // ── Operacional ───────────────────────────────────────────
                $operacionalFunc = $this->selecionarComEquidade(
                    $operacionais, $impedidos, $contagemOp, $ultimoDiaOp, $weekdayOp, $diasElegiveisOp, $dia
                );
                $operacionalNome = $operacionalFunc ? ($operacionalFunc->short_name ?: $operacionalFunc->name) : null;

                if ($operacionalNome === null && $operacionais->isNotEmpty()) {
                    $alertas[] = "{$dataStr}: operacional sem alternativa disponível.";
                }

                // ── Fechar: pool global proporcional de escrivães + operacionais ──
                $fecharFunc = $this->selecionarFechar(
                    $poolFechar,
                    $impedidos,
                    array_values(array_filter([$escrivaoFunc?->id, $operacionalFunc?->id])),
                    $contagemFechar,
                    $ultimoDiaFechar,
                    $weekdayFechar,
                    $diasElegiveisFechar,
                    $dia
                );
                $fecharNome = $fecharFunc ? ($fecharFunc->short_name ?: $fecharFunc->name) : null;

                if ($fecharNome === null && $poolFechar->isNotEmpty()) {
                    $alertas[] = "{$dataStr}: sem candidato para «fechar» no dia.";
                }

                // ── Cria registro ─────────────────────────────────────────
                EscalaDia::query()->create([
                    'data'            => $dataStr,
                    'mes'             => $mes,
                    'ano'             => $ano,
                    'versao'          => $novaVersao,
                    'is_fechada'      => false,
                    'escrivao'        => $escrivaoNome,
                    'operacional'     => $operacionalNome,
                    'fechar_nome'     => $fecharNome,
                    'delegada'        => $delegadaNome, // null = combobox aparece
                    'plantao_externo' => $plantaoTexto ?: null,
                    'created_by'      => $userId,
                    'updated_by'      => $userId,
                ]);

                $diasCriados++;
                $dia->addDay();
            }

            // ── Cabeçalho de versão ───────────────────────────────────────
            EscalaVersao::create([
                'ano'        => $ano,
                'mes'        => $mes,
                'versao'     => $novaVersao,
                'status'     => 'provisoria',
                'created_by' => $userId,
            ]);

            AuditLogger::log(
                'escalas',
                'escala_gerada',
                'GeradorEscalaMensalService',
                "{$ano}-{$mes}-v{$novaVersao}",
                "Escala gerada: {$diasCriados} dias úteis. Alertas: " . count($alertas)
            );

            return [
                'versao'       => $novaVersao,
                'dias_criados' => $diasCriados,
                'alertas'      => $alertas,
            ];
        });
    }

    // -----------------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------------

    /** Filtra Collection de funcionários pelos códigos de cargo informados. */
    private function filtrarPorCargo(Collection $todos, array $codigos): Collection
    {
        return $todos->filter(
            fn (RhFuncionario $f): bool => in_array($f->cargo?->code, $codigos, true)
        )->values();
    }

    /**
     * Retorna os IDs de funcionários IMPEDIDOS de trabalhar em $dia, seja por
     * afastamento ativo ou por regra de plantão externo.
     */
    private function calcularImpedidos(
        Carbon $dia,
        Collection $funcionarios,
        array $plantaoMapa,
        string $dataStr,
        string $anteriorStr
    ): array {
        $impedidos = [];

        foreach ($funcionarios as $f) {
            // ── Afastamento ──────────────────────────────────────────────
            foreach ($f->afastamentos as $af) {
                if (! $af->is_active) {
                    continue;
                }
                $inicio = $af->start_date;
                $fim    = $af->end_date;
                if ($inicio && $inicio->lte($dia) && ($fim === null || $fim->gte($dia))) {
                    $impedidos[] = $f->id;
                    break;
                }
            }

            // ── Plantão do próprio dia (MESMO_DIA ou AMBOS) ───────────────
            $entradaDia = $plantaoMapa[$dataStr][$f->id] ?? null;
            $regraDia   = $entradaDia['regra'] ?? null;
            $siglaDia   = $entradaDia['sigla'] ?? '';

            // Regra: PLD (Plantão de Dia) não bloqueia delegada, mas PLN (Plantão Noturno) e outros bloqueiam TODOS os cargos
            $isDelegada = in_array($f->cargo?->code, self::CARGO_DELEGADA, true);
            if (in_array($regraDia, [self::REGRA_MESMO_DIA, self::REGRA_AMBOS], true)) {
                // Só PLD permite delegada, todos os outros bloqueiam todos
                if (!($siglaDia === 'PLD' && $isDelegada)) {
                    $impedidos[] = $f->id;
                    continue;
                }
            }

            // ── Plantão do dia anterior (DIA_SEGUINTE ou AMBOS) ──────────
            $entradaAnterior = $plantaoMapa[$anteriorStr][$f->id] ?? null;
            $regraAnterior   = $entradaAnterior['regra'] ?? null;
            $siglaAnterior   = $entradaAnterior['sigla'] ?? '';
            // Se o plantão do dia anterior for PLN (ou qualquer outro com regra DIA_SEGUINTE ou AMBOS), bloqueia TODOS os cargos
            if (in_array($regraAnterior, [self::REGRA_DIA_SEGUINTE, self::REGRA_AMBOS], true)) {
                $impedidos[] = $f->id;
            }
        }

        return array_unique($impedidos);
    }

    /**
     * Retorna o short_name do próximo disponível na rotação circular,
     * Seleciona o funcionário mais adequado do pool aplicando equidade prática:
    *  1. Menor índice proporcional (atribuições / dias elegíveis no mês).
     *  2. Não trabalhou no dia imediatamente anterior (evita dias em sequência).
     *  3. Menor repetição no mesmo dia da semana (evita vício de dia fixo).
     *  4. Desempate final por nome (determinístico).
     *
     * Atualiza $contagem, $ultimoDia e $weekdayCount em-place ao selecionar.
     */
    private function selecionarComEquidade(
        Collection $pool,
        array $impedidos,
        array &$contagem,
        array &$ultimoDia,
        array &$weekdayCount,
        array $diasElegiveis,
        Carbon $dia
    ): ?RhFuncionario {
        $disponiveis = $pool->filter(
            fn (RhFuncionario $f): bool => ! in_array($f->id, $impedidos, true)
        )->values();

        if ($disponiveis->isEmpty()) {
            return null;
        }

        $diaSemana   = (int) $dia->format('N'); // 1=Seg … 7=Dom
        $anteriorStr = $dia->copy()->subDay()->toDateString();

        $melhor = $disponiveis->map(function (RhFuncionario $f) use (
            $contagem, $ultimoDia, $weekdayCount, $diasElegiveis, $anteriorStr, $diaSemana
        ): array {
            $ultimo      = $ultimoDia[$f->id] ?? null;
            $consecutivo = ($ultimo !== null && $ultimo->toDateString() === $anteriorStr) ? 1 : 0;
            $elegiveis   = max((int) ($diasElegiveis[$f->id] ?? 0), 1);
            $count       = (int) ($contagem[$f->id] ?? 0);

            return [
                'func'         => $f,
                'proporcao'    => $count / $elegiveis,
                'count'        => $count,
                'consecutivo'  => $consecutivo,
                'weekday_bias' => $weekdayCount[$f->id][$diaSemana] ?? 0,
                'nome_sort'    => $f->name,
            ];
        })->sortBy([
            ['proporcao',    'asc'],
            ['count',        'asc'],
            ['consecutivo',  'asc'],
            ['weekday_bias', 'asc'],
            ['nome_sort',    'asc'],
        ])->first();

        if (! $melhor) {
            return null;
        }

        /** @var RhFuncionario $vencedor */
        $vencedor = $melhor['func'];

        $contagem[$vencedor->id]                 = ($contagem[$vencedor->id] ?? 0) + 1;
        $ultimoDia[$vencedor->id]                = $dia->copy();
        $weekdayCount[$vencedor->id][$diaSemana] = ($weekdayCount[$vencedor->id][$diaSemana] ?? 0) + 1;

        return $vencedor;
    }

    /**
     * Seleciona quem "fecha" o dia obrigatoriamente entre os dois servidores
     * já escalados no respectivo dia, usando a proporcionalidade acumulada
     * do grupo completo de escrivães e operacionais concorrentes.
     */
    private function selecionarFechar(
        Collection $pool,
        array $impedidos,
        array $idsJaEscaladosNoDia,
        array &$contagemFechar,
        array &$ultimoDiaFechar,
        array &$weekdayCountFechar,
        array $diasElegiveisFechar,
        Carbon $dia
    ): ?RhFuncionario {
        $candidatos = $pool->filter(
            fn (RhFuncionario $f): bool => ! in_array($f->id, $impedidos, true)
                && in_array($f->id, $idsJaEscaladosNoDia, true)
        )->values();

        if ($candidatos->isEmpty()) {
            return null;
        }

        $diaSemana   = (int) $dia->format('N');
        $anteriorStr = $dia->copy()->subDay()->toDateString();

        $melhor = $candidatos->map(function (RhFuncionario $f) use (
            $contagemFechar,
            $ultimoDiaFechar,
            $weekdayCountFechar,
            $diasElegiveisFechar,
            $anteriorStr,
            $diaSemana
        ): array {
            $ultimo      = $ultimoDiaFechar[$f->id] ?? null;
            $consecutivo = ($ultimo !== null && $ultimo->toDateString() === $anteriorStr) ? 1 : 0;
            $elegiveis   = max((int) ($diasElegiveisFechar[$f->id] ?? 0), 1);
            $count       = (int) ($contagemFechar[$f->id] ?? 0);

            return [
                'func'         => $f,
                'proporcao'    => $count / $elegiveis,
                'count'        => $count,
                'consecutivo'  => $consecutivo,
                'weekday_bias' => $weekdayCountFechar[$f->id][$diaSemana] ?? 0,
                'nome_sort'    => $f->name,
            ];
        })->sortBy([
            ['proporcao',    'asc'],
            ['count',        'asc'],
            ['consecutivo',  'asc'],
            ['weekday_bias', 'asc'],
            ['nome_sort',    'asc'],
        ])->first();

        if (! $melhor) {
            return null;
        }

        /** @var RhFuncionario $vencedor */
        $vencedor = $melhor['func'];

        $contagemFechar[$vencedor->id]                    = ($contagemFechar[$vencedor->id] ?? 0) + 1;
        $ultimoDiaFechar[$vencedor->id]                   = $dia->copy();
        $weekdayCountFechar[$vencedor->id][$diaSemana]    = ($weekdayCountFechar[$vencedor->id][$diaSemana] ?? 0) + 1;

        return $vencedor;
    }

    /**
     * Monta o texto resumido dos plantões externos do dia
     * (ex.: "Dra. Marina (DDM24h), Frederico (PLN)").
     */
    private function resumoPlantaoTextual(Collection $plantoesMes, string $dataStr): string
    {
        $partes = [];
        foreach ($plantoesMes as $p) {
            if ($p->data->toDateString() !== $dataStr) {
                continue;
            }
            $nome  = $p->funcionario?->short_name ?? '?';
            $sigla = $p->plantaoExterno?->sigla ?? '?';
            $partes[] = "{$nome} ({$sigla})";
        }

        sort($partes, SORT_NATURAL | SORT_FLAG_CASE);
        $partes = array_values(array_unique($partes));

        return implode(PHP_EOL, $partes);
    }
}
