<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Escalas\LegacyEscalasReader;
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
        $response->assertSee('Leitura somente consulta da escala mensal consolidada no legado');
        $response->assertSee('Fonte legada');
        $response->assertSee('Dias da escala');
        $response->assertSee('Feriados do mes');
        $response->assertSee('Base funcional da escala');
        $response->assertSee('Espelho PHP');
    }

    public function test_authorized_user_can_open_scale_proof_page(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/escalas/prova?ano=2026&mes=4');

        $response->assertOk();
        $response->assertSee('Prova da Escala Mensal');
        $response->assertSee('Timbrado consolidado');
        $response->assertSee('Abrir pré-visualização');
        $response->assertSee('Pré-visualização real');
    }

    public function test_authorized_user_can_open_plantoes_page(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/escalas/plantoes?ano=2026&mes=4');

        $response->assertOk();
        $response->assertSee('Plantões');
        $response->assertSee('Leitura somente consulta da base legada');
        $response->assertSee('Fonte legada');
        $response->assertSee('Catalogo de plantões');
        $response->assertSee('Atribuições do mês');
        $response->assertSee('Espelho PHP de referência');
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

    public function test_legacy_scale_uses_weekend_and_holiday_display_rules(): void
    {
        if (! config('grom_legacy.enabled')) {
            $this->markTestSkipped('Legacy sync is disabled in this environment.');
        }

        $this->seed();

        $snapshot = app(LegacyEscalasReader::class)->snapshotForMonth(null, 2026, 4);
        $rows = collect($snapshot['scale_rows']);

        $goodFriday = $rows->firstWhere('date', '2026-04-03');
        $saturday = $rows->firstWhere('date', '2026-04-04');
        $sunday = $rows->firstWhere('date', '2026-04-05');

        $this->assertNotNull($goodFriday);
        $this->assertNotNull($saturday);
        $this->assertNotNull($sunday);

        $this->assertSame('holiday', $goodFriday['display_mode']);
        $this->assertSame('weekend', $saturday['display_mode']);
        $this->assertSame('weekend', $sunday['display_mode']);
        $this->assertSame('', trim((string) $saturday['escrivao']));
        $this->assertSame('', trim((string) $saturday['operacional']));
        $this->assertSame('', trim((string) $sunday['fechar']));
        $this->assertSame('Frederico (PLN), Izabela (PLD), Marina (RN)', $saturday['plantao_externo']);
        $this->assertSame('', trim((string) $sunday['plantao_externo']));
    }

    public function test_print_preview_mode_does_not_auto_open_browser_print(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/escalas/imprimir?ano=2026&mes=4&preview=1');

        $response->assertOk();
        $response->assertDontSee('window.print()');
        $response->assertDontSee('Imprimir / Salvar PDF');
    }

    public function test_active_staff_remain_visible_even_when_not_on_scale(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/escalas?ano=2026&mes=4');

        $response->assertOk();
        $response->assertSee('Observacoes dos ativos');
        $response->assertSee('Carlos Lima');
        $response->assertSee('Maria Souza');
        $response->assertSee('Ativo');
    }
}
