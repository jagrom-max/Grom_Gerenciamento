<?php

namespace App\Services\Escalas;

use App\Models\EscalaPlantaoFuncionario;
use App\Models\RhFuncionario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ValidadorPlantoesExternosService
{
    /**
     * Valida todos os plantões externos lançados para o mês/ano informado.
     * Retorna array de conflitos encontrados.
     *
     * @return array<string[]>
     */
    public function validar(int $ano, int $mes): array
    {
        $conflitos = [];
        $plantoes = EscalaPlantaoFuncionario::query()
            ->with(['funcionario', 'plantaoExterno'])
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderBy('data')
            ->get();

        // Mapa: data => [funcionario_id => [dados]]
        $mapaDias = [];
        foreach ($plantoes as $p) {
            $data = $p->data->toDateString();
            $fid  = $p->funcionario_id;
            if (!isset($mapaDias[$data])) {
                $mapaDias[$data] = [];
            }
            if (isset($mapaDias[$data][$fid])) {
                $conflitos[] = [
                    'type' => 'duplicidade',
                    'data' => $data,
                    'funcionario' => $p->funcionario?->short_name ?? $fid,
                    'msg' => "Funcionário com mais de um plantão externo no mesmo dia."
                ];
            }
            $mapaDias[$data][$fid] = [
                'plantao' => $p->plantaoExterno?->sigla ?? '',
                'regra'   => $p->plantaoExterno?->regra ?? '',
                'id'      => $p->id,
            ];
        }

        // Regras: não pode ter plantão em dias consecutivos, folga após noturno, etc
        $funcionarios = RhFuncionario::query()->where('is_active', true)->get();
        foreach ($funcionarios as $f) {
            $dias = [];
            foreach ($mapaDias as $data => $atribs) {
                if (isset($atribs[$f->id])) {
                    $dias[] = $data;
                }
            }
            sort($dias);
            for ($i = 1; $i < count($dias); $i++) {
                $d1 = Carbon::parse($dias[$i-1]);
                $d2 = Carbon::parse($dias[$i]);
                if ($d1->diffInDays($d2) == 1) {
                    $conflitos[] = [
                        'type' => 'sequencia',
                        'funcionario' => $f->short_name ?? $f->name,
                        'datas' => [$d1->toDateString(), $d2->toDateString()],
                        'msg' => 'Funcionário com plantão externo em dias consecutivos.'
                    ];
                }
            }
            // TODO: adicionar validação de folga após noturno, regras específicas por sigla
        }

        // TODO: adicionar validações específicas de regras de negócio (ex: não pode noturno seguido, etc)

        return $conflitos;
    }
}
