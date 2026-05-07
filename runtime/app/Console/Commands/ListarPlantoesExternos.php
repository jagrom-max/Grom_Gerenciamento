<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EscalaPlantaoFuncionario;
use App\Models\RhFuncionario;
use App\Models\EscalaPlantaoExterno;

class ListarPlantoesExternos extends Command
{
    protected $signature = 'escalas:listar-plantoes-externos {ano} {mes}';
    protected $description = 'Lista todos os plantões externos cadastrados para o mês/ano informado';

    public function handle()
    {
        $ano = (int) $this->argument('ano');
        $mes = (int) $this->argument('mes');

        $plantoes = EscalaPlantaoFuncionario::query()
            ->with(['funcionario', 'plantaoExterno'])
            ->whereYear('data', $ano)
            ->whereMonth('data', $mes)
            ->orderBy('data')
            ->get();

        if ($plantoes->isEmpty()) {
            $this->warn('Nenhum plantão externo encontrado para o período.');
            return 0;
        }

        $this->info("Plantões externos cadastrados para {$mes}/{$ano}:");
        $this->table([
            'ID', 'Data', 'Funcionário', 'Plantão', 'Sigla', 'Unidade', 'Regra'
        ],
            $plantoes->map(function ($p) {
                return [
                    $p->id,
                    $p->data?->format('d/m/Y'),
                    $p->funcionario?->short_name ?? $p->funcionario?->name ?? $p->funcionario_id,
                    $p->plantaoExterno?->nome ?? $p->plantao_externo_id,
                    $p->plantaoExterno?->sigla ?? '',
                    $p->plantaoExterno?->unidade ?? '',
                    $p->plantaoExterno?->regra ?? '',
                ];
            })->toArray()
        );
        return 0;
    }
}
