<?php

namespace Tests\Feature;

use App\Models\EscalaDia;
use App\Models\RhAfastamento;
use App\Models\RhCargo;
use App\Models\RhFuncionario;
use App\Models\RhHoliday;
use App\Services\Escalas\GeradorEscalaMensalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class EscalasGeradorMensalTest extends TestCase
{
    use RefreshDatabase;

    public function test_fechar_usa_pool_global_com_proporcionalidade_por_dias_elegiveis(): void
    {
        [$escrivaoCargo, $operacionalCargo] = $this->criarCargosEscala();

        $alice = $this->criarFuncionario('Alice Escrivao', $escrivaoCargo->id);
        $bruno = $this->criarFuncionario('Bruno Ferias', $escrivaoCargo->id);
        $carlos = $this->criarFuncionario('Carlos Operacional', $operacionalCargo->id);
        $diana = $this->criarFuncionario('Diana Operacional', $operacionalCargo->id);

        RhAfastamento::query()->create([
            'funcionario_id' => $bruno->id,
            'reason' => 'Ferias',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-15',
            'is_active' => true,
        ]);

        app(GeradorEscalaMensalService::class)->gerar(2026, 5, (string) Str::uuid());

        $dias = EscalaDia::query()
            ->where('ano', 2026)
            ->where('mes', 5)
            ->orderBy('data')
            ->get();

        $this->assertCount(21, $dias);

        $fechamentos = $dias->countBy('fechar_nome')->all();
        $elegiveis = [
            'Alice' => 21,
            'Bruno' => 10,
            'Carlos' => 21,
            'Diana' => 21,
        ];

        $this->assertSame(0, $dias
            ->whereBetween('data', [Carbon::parse('2026-05-01'), Carbon::parse('2026-05-15')])
            ->where('fechar_nome', 'Bruno')
            ->count());

        foreach ($elegiveis as $nome => $diasElegiveis) {
            $this->assertArrayHasKey($nome, $fechamentos);
            $ratio = $fechamentos[$nome] / $diasElegiveis;
            $this->assertLessThanOrEqual(0.10, abs($ratio - (21 / 73)), "Fechamento fora da proporcao para {$nome}.");
        }

        $this->assertTrue($dias->every(
            fn (EscalaDia $dia): bool => in_array($dia->fechar_nome, [$dia->escrivao, $dia->operacional], true)
        ));
    }

    public function test_gerador_nao_cria_linhas_operacionais_em_feriados_cadastrados(): void
    {
        [$escrivaoCargo, $operacionalCargo] = $this->criarCargosEscala();

        $this->criarFuncionario('Alice Escrivao', $escrivaoCargo->id);
        $this->criarFuncionario('Bruno Escrivao', $escrivaoCargo->id);
        $this->criarFuncionario('Carlos Operacional', $operacionalCargo->id);
        $this->criarFuncionario('Diana Operacional', $operacionalCargo->id);

        RhHoliday::query()->create([
            'holiday_date' => '2026-06-04',
            'name' => 'Corpus Christi',
            'scope' => 'municipal',
            'is_active' => true,
        ]);

        RhHoliday::query()->create([
            'holiday_date' => '2026-06-24',
            'name' => 'Aniversario de Rio Claro / Sao Joao Batista',
            'scope' => 'municipal',
            'is_active' => true,
        ]);

        app(GeradorEscalaMensalService::class)->gerar(2026, 6, (string) Str::uuid());

        $dias = EscalaDia::query()
            ->where('ano', 2026)
            ->where('mes', 6)
            ->orderBy('data')
            ->pluck('data')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->all();

        $this->assertCount(20, $dias);
        $this->assertNotContains('2026-06-04', $dias);
        $this->assertNotContains('2026-06-24', $dias);
    }

    private function criarCargosEscala(): array
    {
        return [
            RhCargo::query()->create([
                'code' => 'LEG-005',
                'name' => 'Escrivao',
                'is_active' => true,
            ]),
            RhCargo::query()->create([
                'code' => 'LEG-002',
                'name' => 'Operacional',
                'is_active' => true,
            ]),
        ];
    }

    private function criarFuncionario(string $nome, string $cargoId): RhFuncionario
    {
        return RhFuncionario::query()->create([
            'matricula' => Str::slug($nome),
            'name' => $nome,
            'short_name' => explode(' ', $nome)[0],
            'cargo_id' => $cargoId,
            'admission_date' => '2026-01-01',
            'designation_date' => '2026-01-01',
            'concorre_escala' => true,
            'is_active' => true,
        ]);
    }
}
