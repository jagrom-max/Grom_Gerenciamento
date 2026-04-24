<?php

namespace Tests\Feature;

use App\Models\Cartorio;
use App\Models\ProductivityStatMonthly;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatoriosAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_reports_hub(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/relatorios');

        $response->assertOk();
        $response->assertSee('Central de relatorios');
        $response->assertSee('Produtividade mensal A4');
    }

    public function test_user_without_permission_is_forbidden_from_reports_hub(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/relatorios');

        $response->assertForbidden();
    }

    public function test_authorized_user_can_open_productivity_a4_preview(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $cartorio = Cartorio::query()->firstOrCreate(
            ['code' => 'CRT-010'],
            ['number' => 10, 'name' => 'Cartorio Dez', 'is_active' => true],
        );

        ProductivityStatMonthly::query()->updateOrCreate(
            [
                'cartorio_id' => $cartorio->id,
                'reference_year' => 2026,
                'reference_month' => 3,
            ],
            [
                'ip_instaurados' => 21,
                'flagrantes_total' => 5,
                'flagrantes_ddm' => 3,
                'flagrantes_outras' => 2,
                'source_mode' => 'AUTO',
            ],
        );

        $response = $this->actingAs($user)->get('/relatorios/produtividade/a4?year=2026&month=3');

        $response->assertOk();
        $response->assertSee('Produtividade de Cartórios - Fechamento Mensal');
        $response->assertSee('Baixar PDF');
        $response->assertSee($cartorio->name);
        $response->assertSee('5');
    }

    public function test_authorized_user_can_download_productivity_a4_pdf(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/relatorios/produtividade/a4/pdf?year=2026&month=3');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
    }

    public function test_authorized_user_can_open_operational_followup_report_preview(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $cartorio = Cartorio::query()->create([
            'number' => 21,
            'code' => 'CRT-021',
            'name' => 'Cartorio Integrado',
            'manager_name' => 'Delegado Integrado',
            'designacao' => 'Plantao Integrado',
            'is_active' => true,
        ]);

        ProductivityStatMonthly::query()->create([
            'cartorio_id' => $cartorio->id,
            'reference_year' => 2026,
            'reference_month' => 3,
            'ip_instaurados' => 18,
            'ip_relatados' => 14,
            'cotas' => 3,
            'despachos' => 6,
            'concluidos' => 12,
            'registros' => 16,
            'ips_andamento' => 4,
            'flagrantes_total' => 7,
            'flagrantes_ddm' => 4,
            'flagrantes_outras' => 3,
            'source_mode' => 'AUTO',
        ]);

        $response = $this->actingAs($user)->get('/relatorios/acompanhamento-operacional?year=2026&month=3&cartorio_id=' . $cartorio->id);

        $response->assertOk();
        $response->assertSee('Acompanhamento Operacional Integrado');
        $response->assertSee('Cartorios reais espelhados');
        $response->assertSee('Cartorio Integrado');
        $response->assertSee('Confronto direto do cadastro de pessoas.');
    }

    public function test_authorized_user_can_download_operational_followup_report_pdf(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $cartorio = Cartorio::query()->create([
            'number' => 22,
            'code' => 'CRT-022',
            'name' => 'Cartorio PDF',
            'manager_name' => 'Delegado PDF',
            'designacao' => 'Plantao PDF',
            'is_active' => true,
        ]);

        ProductivityStatMonthly::query()->create([
            'cartorio_id' => $cartorio->id,
            'reference_year' => 2026,
            'reference_month' => 3,
            'ip_instaurados' => 9,
            'ip_relatados' => 6,
            'cotas' => 1,
            'despachos' => 2,
            'concluidos' => 5,
            'registros' => 7,
            'ips_andamento' => 2,
            'flagrantes_total' => 3,
            'flagrantes_ddm' => 1,
            'flagrantes_outras' => 2,
            'source_mode' => 'AUTO',
        ]);

        $response = $this->actingAs($user)->get('/relatorios/acompanhamento-operacional/pdf?year=2026&month=3&cartorio_id=' . $cartorio->id);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
    }
}
