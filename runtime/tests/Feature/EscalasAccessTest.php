<?php

namespace Tests\Feature;

use App\Models\EscalaDia;
use App\Models\EscalaPlantaoExterno;
use App\Models\EscalaPlantaoFuncionario;
use App\Models\RhCargo;
use App\Models\RhFuncionario;
use App\Models\User;
use App\Services\Escalas\LegacyEscalasReader;
use App\Support\Pdf\HeadlessBrowserPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscalasAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/escalas');

        $response->assertRedirect('/login');
    }

    public function test_authorized_user_can_open_monthly_scale_page(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/escalas?ano=2026&mes=4');

        $response->assertOk();
        $response->assertSee('Escala Mensal');
        $response->assertSee('Pesquisar');
        $response->assertSee('Resultado');
        $response->assertSee('Plantão externo');
        $response->assertDontSee('Base funcional da escala');
        $response->assertDontSee('legado Python');
    }

    public function test_monthly_scale_page_renders_legacy_snapshot_when_php_month_is_empty(): void
    {
        $this->seed();

        EscalaDia::query()->where('ano', 2026)->where('mes', 4)->delete();

        $this->app->instance(LegacyEscalasReader::class, new class extends LegacyEscalasReader {
            public function snapshotForMonth(?User $actor = null, ?int $year = null, ?int $month = null): array
            {
                return [
                    'source_name' => 'legacy-test.sqlite3',
                    'year' => $year ?? 2026,
                    'month' => $month ?? 4,
                    'month_label' => 'abril',
                    'available_years' => [2026],
                    'available_months' => [4],
                    'version' => 3,
                    'scale_rows' => [
                        [
                            'date' => '2026-04-01',
                            'day_label' => 'ter.',
                            'date_label' => '01/04',
                            'display_mode' => 'normal',
                            'escrivao' => 'Escrivao Legado',
                            'operacional' => 'Operacional Legado',
                            'fechar' => 'Fechamento Legado',
                            'delegada' => 'Delegada Legado',
                            'plantao_externo' => 'PLT',
                        ],
                    ],
                    'holidays' => [
                        ['date' => '2026-04-21', 'date_label' => '21/04', 'descricao' => 'Tiradentes', 'tipo' => 'Nacional'],
                    ],
                    'plantoes' => [],
                    'plantao_catalog' => [],
                    'funcionarios' => [],
                    'afastamentos_mes' => [],
                    'summary' => [
                        'dias_total' => 1,
                        'dias_com_escrivao' => 1,
                        'dias_com_operacional' => 1,
                        'dias_com_delegada' => 1,
                        'dias_com_plantao_externo' => 1,
                        'feriados_mes' => 1,
                        'plantoes_atribuicoes' => 0,
                        'plantoes_catalogo_ativos' => 0,
                        'funcionarios_total' => 0,
                        'funcionarios_ativos' => 0,
                        'funcionarios_concorrem' => 0,
                        'funcionarios_em_afastamento' => 0,
                    ],
                    'warnings' => [],
                ];
            }
        });

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/escalas?ano=2026&mes=4');

        $response->assertOk();
        $response->assertSee('01/04');
        $response->assertSee('Resultado');
        $response->assertDontSee('Nenhuma linha de escala encontrada para o período selecionado.');
    }

    public function test_authorized_user_can_open_scale_proof_page(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/escalas/prova?ano=2026&mes=4');

        $response->assertOk();
        $response->assertSee('Prova da Escala');
        $response->assertSee('Abrir pré-visualização');
        $response->assertSee('Voltar para escala');
    }

    public function test_authorized_user_can_open_plantoes_page(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/escalas/plantoes?ano=2026&mes=4');

        $response->assertOk();
        $response->assertSee('Plantões');
        $response->assertSee('Pesquisar');
        $response->assertSee('Atribuições do mês');
        $response->assertDontSee('Catálogo de plantões');
    }

    public function test_alias_routes_redirect_to_escalas_pages(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $this->actingAs($user)->get('/escala')->assertRedirect('/escalas');
        $this->actingAs($user)->get('/plantoes')->assertRedirect('/escalas/plantoes');
    }

    public function test_authorized_user_can_open_plantoes_relatorio_page(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/escalas/plantoes/relatorio?year=2026&month=4');

        $response->assertOk();
        $response->assertSee('Relatório de Plantões Externos');
        $response->assertSee('Cartório Central - Gerenciamento');
        $response->assertSee('Resumo por Servidor');
        $response->assertSee('Detalhe Cronológico');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/escalas');

        $response->assertForbidden();
    }

    public function test_monthly_scale_page_does_not_render_legacy_confront_section(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/escalas?ano=2026&mes=4');

        $response->assertOk();
        $response->assertDontSee('Confronto direto entre a base funcional do Python');
        $response->assertDontSee('Sincronizar Legado');
        $response->assertDontSee('Espelho PHP do RH');
    }

    public function test_print_preview_mode_keeps_auto_print_script(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/escalas/imprimir?ano=2026&mes=4&preview=1');

        $response->assertOk();
        $response->assertSee('window.print()');
        $response->assertDontSee('Imprimir / Salvar PDF');
    }

    public function test_active_staff_remain_visible_and_inactive_are_hidden_from_month_info(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/escalas?ano=2026&mes=4');

        $response->assertOk();
        $response->assertSee('Resultado');
        $response->assertSee('Data');
        $response->assertSee('Plantão externo');
        $response->assertDontSee('Nenhuma linha de escala encontrada para o período selecionado.');
    }

    public function test_authorized_user_can_download_scale_pdf(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $renderer = new class extends HeadlessBrowserPdfRenderer {
            public function renderBlade(string $view, array $data, ?string $filePrefix = null): string
            {
                $path = tempnam(sys_get_temp_dir(), 'escala-print-');
                $pdfPath = $path . '.pdf';
                rename($path, $pdfPath);
                file_put_contents($pdfPath, '%PDF-1.4' . PHP_EOL . '% fake pdf for tests');

                return $pdfPath;
            }
        };

        app()->instance(HeadlessBrowserPdfRenderer::class, $renderer);

        $response = $this->actingAs($user)->get('/escalas/imprimir/pdf?ano=2026&mes=4');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
    }

    public function test_authorized_user_can_assign_external_duty_to_multiple_dates(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $cargo = RhCargo::query()->firstOrFail();
        $funcionario = RhFuncionario::query()->create([
            'matricula' => 'ESC-MULTI-001',
            'name' => 'Servidor Real',
            'short_name' => 'Servidor Real',
            'cargo_id' => $cargo->id,
            'admission_date' => '2026-01-01',
            'designation_date' => '2026-01-01',
            'concorre_escala' => true,
            'is_active' => true,
        ]);

        $plantao = EscalaPlantaoExterno::query()->create([
            'nome' => 'Plantao Teste',
            'sigla' => 'PT',
            'regra' => 'MESMO_DIA',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post('/escalas/plantoes-funcionarios', [
            'funcionario_id' => $funcionario->id,
            'plantao_externo_id' => $plantao->id,
            'datas' => ['2026-05-03', '2026-05-04', '2026-05-05'],
        ]);

        $response->assertSessionHas('status-success');

        $this->assertSame(3, EscalaPlantaoFuncionario::query()
            ->where('funcionario_id', $funcionario->id)
            ->where('plantao_externo_id', $plantao->id)
            ->count());

        $this->actingAs($user)->post('/escalas/plantoes-funcionarios', [
            'funcionario_id' => $funcionario->id,
            'plantao_externo_id' => $plantao->id,
            'datas' => ['2026-05-03', '2026-05-04'],
        ])->assertSessionHas('status-warning');

        $this->assertSame(3, EscalaPlantaoFuncionario::query()
            ->where('funcionario_id', $funcionario->id)
            ->where('plantao_externo_id', $plantao->id)
            ->count());
    }
}
