<?php

namespace Tests\Feature;

use App\Models\EscalaDia;
use App\Models\EscalaPlantaoExterno;
use App\Models\EscalaPlantaoFuncionario;
use App\Models\RhFuncionario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class EscalasPlantoesExternosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Garante que plantões externos são exibidos em feriados e finais de semana.
     */
    public function test_plantao_externo_exibido_em_feriado_e_fim_de_semana(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $funcionario = RhFuncionario::factory()->create([
            'name' => 'Servidor Plantonista',
            'is_active' => true,
        ]);

        $plantao = EscalaPlantaoExterno::factory()->create([
            'nome' => 'Plantão Externo',
            'sigla' => 'PLT',
            'is_active' => true,
        ]);

        // Sábado (2026-05-02), Domingo (2026-05-03), Feriado (2026-05-01)
        $datas = [
            '2026-05-01', // Feriado
            '2026-05-02', // Sábado
            '2026-05-03', // Domingo
        ];
        foreach ($datas as $data) {
            EscalaPlantaoFuncionario::create([
                'data' => $data,
                'funcionario_id' => $funcionario->id,
                'plantao_externo_id' => $plantao->id,
            ]);
        }

        // Cria feriado
        \App\Models\RhHoliday::factory()->create([
            'date' => '2026-05-01',
            'descricao' => 'Dia do Trabalho',
        ]);

        $response = $this->actingAs($user)->get('/escalas/print?ano=2026&mes=5');
        $response->assertOk();
        $response->assertSee('PLT');
        $response->assertSee('Plantão Externo');
        $response->assertSee('Dia do Trabalho');
    }
}
