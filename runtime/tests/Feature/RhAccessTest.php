<?php

namespace Tests\Feature;

use App\Models\RhCargo;
use App\Models\RhAfastamento;
use App\Models\RhDelegadoExterno;
use App\Models\RhDelegadoExternoPeriodo;
use App\Models\RhFuncionario;
use App\Models\RhHoliday;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RhAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_rh_dashboard(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $this->actingAs($user)->post('/rh/cargos', [
            'code' => 'RH-010',
            'name' => 'Analista de RH',
            'description' => 'Cargo demonstrativo para o piloto web.',
            'is_active' => 1,
        ])->assertRedirect('/rh');

        $response = $this->actingAs($user)->get('/rh');

        $response->assertOk();
        $response->assertSee('RH / Admin');
        $response->assertSee('Cargos');
        $response->assertSee('Funcionários');
        $response->assertSee('Delegados Externos');
        $response->assertSee('Feriados');
        $response->assertSee('Novo feriado');
        $response->assertSee('Histórico recente');
        $response->assertSee('Paixão de Cristo');
        $response->assertSee('cargos.create');
    }

    public function test_admin_can_create_cargo_and_funcionario(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $this->actingAs($user)->post('/rh/cargos', [
            'code' => 'RH-010',
            'name' => 'Analista de RH',
            'description' => 'Cargo demonstrativo para o piloto web.',
            'is_active' => 1,
        ])->assertRedirect('/rh');

        $cargo = RhCargo::query()->where('code', 'RH-010')->firstOrFail();

        $this->actingAs($user)->post('/rh/funcionarios', [
            'matricula' => 'FUN-010',
            'name' => 'Patricia Alves',
            'email' => 'patricia.alves@grom.local',
            'cargo_id' => $cargo->id,
            'concorre_escala' => 1,
            'admission_date' => '2026-04-01',
            'departure_date' => null,
            'notes' => 'Entrada demonstrativa do modulo de RH.',
            'is_active' => 1,
        ])->assertRedirect('/rh');

        $this->assertDatabaseHas('rh_cargos', [
            'code' => 'RH-010',
            'name' => 'Analista de RH',
        ]);

        $this->assertDatabaseHas('rh_funcionarios', [
            'matricula' => 'FUN-010',
            'name' => 'Patricia Alves',
            'cargo_id' => $cargo->id,
        ]);
    }

    public function test_super_admin_can_activate_access_during_funcionario_creation(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $cargo = RhCargo::query()->firstOrFail();
        $role = Role::query()->firstOrFail();

        $this->actingAs($user)->post('/rh/funcionarios', [
            'matricula' => 'FUN-011',
            'name' => 'Marcia Prado',
            'email' => 'marcia.prado@grom.local',
            'cargo_id' => $cargo->id,
            'cpf' => '12312312312',
            'rg' => '44999888',
            'phone' => '(11) 98888-2222',
            'concorre_escala' => 1,
            'admission_date' => '2026-04-12',
            'departure_date' => null,
            'notes' => 'Criado com acesso no mesmo fluxo.',
            'is_active' => 1,
            'create_access' => 1,
            'access_roles' => [$role->id],
        ])->assertRedirect('/rh');

        $funcionario = RhFuncionario::query()->where('matricula', 'FUN-011')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'funcionario_id' => $funcionario->id,
            'cpf' => '12312312312',
            'tipo_usuario' => 'servidor',
            'is_active' => true,
        ]);

        $accessUser = User::query()->where('funcionario_id', $funcionario->id)->firstOrFail();
        $this->assertTrue($accessUser->roles()->where('roles.id', $role->id)->exists());
    }

    public function test_admin_can_register_afastamento(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $funcionario = RhFuncionario::query()->firstOrFail();

        $this->actingAs($user)->post('/rh/afastamentos', [
            'funcionario_id' => $funcionario->id,
            'reason' => 'Ferias regulamentares',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-10',
            'notes' => 'Registro demonstrativo do piloto web.',
        ])->assertRedirect('/rh');

        $this->assertDatabaseHas('rh_afastamentos', [
            'funcionario_id' => $funcionario->id,
            'reason' => 'Ferias regulamentares',
        ]);
    }

    public function test_admin_cannot_register_overlapping_active_afastamento_for_same_funcionario(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $funcionario = RhFuncionario::query()->firstOrFail();

        RhAfastamento::query()->create([
            'funcionario_id' => $funcionario->id,
            'reason' => 'Licenca medica',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-20',
            'is_active' => true,
        ]);

        $countBefore = RhAfastamento::query()->count();

        $response = $this->from('/rh')->actingAs($user)->post('/rh/afastamentos', [
            'funcionario_id' => $funcionario->id,
            'reason' => 'Ferias regulamentares',
            'start_date' => '2026-04-18',
            'end_date' => '2026-04-25',
            'notes' => 'Nao deveria ser aceito por sobreposicao.',
        ]);

        $response
            ->assertRedirect('/rh')
            ->assertSessionHasErrors(['start_date']);

        $this->assertSame($countBefore, RhAfastamento::query()->count());
    }

    public function test_admin_cannot_update_afastamento_to_overlapping_active_period_for_same_funcionario(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $funcionario = RhFuncionario::query()->firstOrFail();

        $existing = RhAfastamento::query()->create([
            'funcionario_id' => $funcionario->id,
            'reason' => 'Licenca medica',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-20',
            'is_active' => true,
        ]);

        $editable = RhAfastamento::query()->create([
            'funcionario_id' => $funcionario->id,
            'reason' => 'Ferias regulamentares',
            'start_date' => '2026-04-25',
            'end_date' => '2026-04-30',
            'is_active' => true,
        ]);

        $countBefore = RhAfastamento::query()->count();

        $response = $this->from('/rh')->actingAs($user)->put("/rh/afastamentos/{$editable->id}", [
            'reason' => 'Ferias regulamentares',
            'start_date' => '2026-04-18',
            'end_date' => '2026-04-28',
            'notes' => 'Nao deveria ser aceito por sobreposicao.',
        ]);

        $response
            ->assertRedirect('/rh')
            ->assertSessionHasErrors(['start_date']);

        $this->assertSame($countBefore, RhAfastamento::query()->count());

        $this->assertSame('2026-04-25', $editable->fresh()->start_date?->toDateString());
        $this->assertSame('2026-04-30', $editable->fresh()->end_date?->toDateString());
        $this->assertSame('2026-04-10', $existing->fresh()->start_date?->toDateString());
        $this->assertSame('2026-04-20', $existing->fresh()->end_date?->toDateString());
    }

    public function test_rh_dashboard_counts_afastamento_days_separating_ferias_from_other_reasons(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $funcionario = RhFuncionario::query()->firstOrFail();

        RhAfastamento::query()->create([
            'funcionario_id' => $funcionario->id,
            'reason' => 'Férias regulamentares',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-10',
            'is_active' => true,
        ]);

        RhAfastamento::query()->create([
            'funcionario_id' => $funcionario->id,
            'reason' => 'Licença médica',
            'start_date' => '2026-04-15',
            'end_date' => '2026-04-17',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/rh');

        $response->assertOk();
        $response->assertSeeText('Férias acumuladas');
        $response->assertSeeText('Demais afastamentos');
        $response->assertSeeText('Períodos em aberto');
        $response->assertSeeText('Férias regulamentares');
        $response->assertSeeText('Licença médica');
        $response->assertSeeText('10 dias');
        $response->assertSeeText('3 dias');
    }

    public function test_admin_can_register_and_toggle_holiday(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $this->actingAs($user)->post('/rh/feriados', [
            'holiday_date' => '2026-03-18',
            'name' => 'Consciencia Negra',
            'scope' => 'estadual',
            'notes' => 'Feriado demonstrativo do piloto web.',
            'is_active' => 1,
        ])->assertRedirect('/rh');

        $holiday = RhHoliday::query()->where('name', 'Consciencia Negra')->firstOrFail();

        $this->actingAs($user)->patch("/rh/feriados/{$holiday->id}/toggle-active")
            ->assertRedirect('/rh');

        $this->assertDatabaseHas('rh_holidays', [
            'id' => $holiday->id,
            'name' => 'Consciencia Negra',
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_register_duplicate_holiday_date(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $existingHoliday = RhHoliday::query()->firstOrFail();

        $countBefore = RhHoliday::query()->count();

        $response = $this->from('/rh')->actingAs($user)->post('/rh/feriados', [
            'holiday_date' => $existingHoliday->holiday_date?->toDateString(),
            'name' => $existingHoliday->name . ' duplicado',
            'scope' => $existingHoliday->scope,
            'notes' => 'Duplicado.',
            'is_active' => 1,
        ]);

        $response
            ->assertRedirect('/rh')
            ->assertSessionHasErrors(['holiday_date']);

        $this->assertSame($countBefore, RhHoliday::query()->count());
    }

    public function test_delete_funcionario_route_archives_without_losing_history(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $funcionario = RhFuncionario::query()->where('is_active', true)->firstOrFail();

        RhAfastamento::query()->create([
            'funcionario_id' => $funcionario->id,
            'reason' => 'Ferias regulamentares',
            'start_date' => '2026-05-05',
            'end_date' => '2026-05-10',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->delete("/rh/funcionarios/{$funcionario->id}")
            ->assertRedirect('/rh');

        $funcionario->refresh();

        $this->assertFalse($funcionario->is_active);
        $this->assertFalse($funcionario->concorre_escala);
        $this->assertNotNull($funcionario->departure_date);
        $this->assertDatabaseHas('rh_funcionarios', [
            'id' => $funcionario->id,
            'matricula' => $funcionario->matricula,
        ]);
        $this->assertDatabaseHas('rh_afastamentos', [
            'funcionario_id' => $funcionario->id,
            'reason' => 'Ferias regulamentares',
        ]);
    }

    public function test_admin_can_register_and_toggle_delegado_externo(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $this->actingAs($user)->post('/rh/delegados-externos', [
            'registration_code' => 'DEX-010',
            'name' => 'Mariana Costa',
            'origin_unit' => 'Delegacia Regional Sul',
            'role_title' => 'Delegada Externa',
            'contact' => '(11) 9999-0100',
            'email' => 'mariana.costa@grom.local',
            'start_date' => '2026-04-01',
            'end_date' => '2026-10-01',
            'notes' => 'Delegacao demonstrativa para cobertura temporaria.',
            'is_active' => 1,
        ])->assertRedirect('/rh');

        $delegadoExterno = RhDelegadoExterno::query()->where('registration_code', 'DEX-010')->firstOrFail();

        $this->actingAs($user)->patch("/rh/delegados-externos/{$delegadoExterno->id}/toggle-active")
            ->assertRedirect('/rh');

        $this->assertDatabaseHas('rh_delegados_externos', [
            'id' => $delegadoExterno->id,
            'name' => 'Mariana Costa',
            'is_active' => false,
        ]);
    }

    public function test_rh_dashboard_applies_filters_and_reports_filtered_count(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $cargo = RhCargo::query()->create([
            'code' => 'RH-FLT',
            'name' => 'Cargo de Filtro',
            'description' => 'Cargo criado para teste de filtros do RH.',
            'is_active' => true,
        ]);

        RhFuncionario::query()->create([
            'matricula' => 'FLT-001',
            'name' => 'Funcionario Ativo',
            'email' => 'ativo@grom.local',
            'cargo_id' => $cargo->id,
            'admission_date' => '2026-04-01',
            'departure_date' => null,
            'notes' => null,
            'is_active' => true,
        ]);

        RhFuncionario::query()->create([
            'matricula' => 'FLT-002',
            'name' => 'Funcionario Inativo',
            'email' => 'inativo@grom.local',
            'cargo_id' => $cargo->id,
            'admission_date' => '2026-04-01',
            'departure_date' => null,
            'notes' => null,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->get("/rh?cargo_id={$cargo->id}&status=active");

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => ($summary['funcionarios_exibidos'] ?? null) === 1);
    }

    public function test_user_without_permission_is_forbidden_from_rh_dashboard(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/rh');

        $response->assertForbidden();
    }

    // ── Delegados Externos: show, update, períodos ──────────────────────────

    private function createDelegadoExterno(array $overrides = []): RhDelegadoExterno
    {
        return RhDelegadoExterno::query()->create(array_merge([
            'registration_code' => 'DEX-T01',
            'name'              => 'Delegado Teste Periodos',
            'origin_unit'       => 'Unidade Central',
            'role_title'        => 'Delegado',
            'start_date'        => '2026-01-01',
            'end_date'          => null,
            'is_active'         => true,
        ], $overrides));
    }

    public function test_admin_can_view_delegado_externo_show_page(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $delegado = $this->createDelegadoExterno();

        $response = $this->actingAs($user)->get("/rh/delegados-externos/{$delegado->id}");

        $response->assertOk();
        $response->assertSeeText('Delegado Teste Periodos');
        $response->assertSeeText('Períodos como Substituto DDM');
    }

    public function test_admin_can_update_delegado_externo(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $delegado = $this->createDelegadoExterno();

        $this->actingAs($user)->put("/rh/delegados-externos/{$delegado->id}", [
            'registration_code' => 'DEX-T01',
            'name'              => 'Delegado Atualizado',
            'origin_unit'       => 'Unidade Norte',
            'role_title'        => 'Delegado Regional',
            'start_date'        => '2026-01-01',
            'is_active'         => 1,
        ])->assertRedirect("/rh/delegados-externos/{$delegado->id}");

        $this->assertDatabaseHas('rh_delegados_externos', [
            'id'          => $delegado->id,
            'name'        => 'Delegado Atualizado',
            'origin_unit' => 'Unidade Norte',
        ]);
    }

    public function test_admin_can_register_periodo_substituto_ddm(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $delegado = $this->createDelegadoExterno();

        $this->actingAs($user)->post("/rh/delegados-externos/{$delegado->id}/periodos", [
            'motivo'     => 'Férias',
            'start_date' => '2026-06-01',
            'end_date'   => '2026-06-30',
            'notes'      => 'Cobertura de ferias anuais.',
        ])->assertRedirect("/rh/delegados-externos/{$delegado->id}");

        $this->assertDatabaseHas('rh_delegado_externo_periodos', [
            'delegado_externo_id' => $delegado->id,
            'motivo'              => 'Férias',
        ]);

        $periodo = RhDelegadoExternoPeriodo::query()
            ->where('delegado_externo_id', $delegado->id)
            ->where('motivo', 'Férias')
            ->firstOrFail();

        $this->assertSame('2026-06-01', $periodo->start_date?->toDateString());
        $this->assertSame('2026-06-30', $periodo->end_date?->toDateString());
        $this->assertTrue($periodo->is_active);
    }

    public function test_admin_can_toggle_periodo_active(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $delegado = $this->createDelegadoExterno();
        $periodo  = $delegado->periodos()->create([
            'motivo'     => 'Licença Prêmio',
            'start_date' => '2026-03-01',
            'end_date'   => '2026-03-31',
            'is_active'  => true,
        ]);

        $this->actingAs($user)
            ->patch("/rh/delegados-externos/{$delegado->id}/periodos/{$periodo->id}/toggle-active")
            ->assertRedirect("/rh/delegados-externos/{$delegado->id}");

        $this->assertDatabaseHas('rh_delegado_externo_periodos', [
            'id'        => $periodo->id,
            'is_active' => false,
        ]);
    }

    public function test_show_page_displays_periodo_summary(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $delegado = $this->createDelegadoExterno();

        // 30 dias (01/06 a 30/06)
        $delegado->periodos()->create([
            'motivo'     => 'Férias',
            'start_date' => '2026-06-01',
            'end_date'   => '2026-06-30',
            'is_active'  => true,
        ]);

        // 10 dias (01/07 a 10/07)
        $delegado->periodos()->create([
            'motivo'     => 'Substituição eventual',
            'start_date' => '2026-07-01',
            'end_date'   => '2026-07-10',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($user)->get("/rh/delegados-externos/{$delegado->id}");

        $response->assertOk();
        $response->assertSeeText('Períodos como Substituto DDM');
        $response->assertSeeText('40 dias');
        $response->assertSeeText('2 registro(s)');
        $response->assertSeeText('Férias');
        $response->assertSeeText('Substituição eventual');
    }
}
