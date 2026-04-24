<?php

namespace Tests\Feature;

use App\Models\RhAfastamento;
use App\Models\RhCargo;
use App\Models\RhFuncionario;
use App\Models\RhHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendariosAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/calendarios');

        $response->assertRedirect('/login');
    }

    public function test_authorized_user_can_open_calendar_page(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/calendarios?ano=2026&mes=4');

        $response->assertOk();
        $response->assertSee('Calendario de afastamentos');
        $response->assertSee('Afastamentos por dia');
        $response->assertSee('Dias criticos');
        $response->assertSee('Feriados de apoio');
        $response->assertSee('12 - dezembro');
    }

    public function test_authorized_user_sees_overlapping_absences_in_calendar(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $cargo = RhCargo::query()->firstOrFail();

        $funcionarioA = RhFuncionario::query()->create([
            'matricula' => 'FUN-100',
            'name' => 'Ana Impedida',
            'short_name' => 'Ana',
            'email' => 'ana.impedida@grom.local',
            'cargo_id' => $cargo->id,
            'sector' => 'Cartorio Central',
            'phone' => '(11) 4000-1100',
            'rg' => '10.100.100-1',
            'cpf' => '100.100.100-10',
            'birth_date' => '1988-01-01',
            'admission_date' => '2024-01-10',
            'designation_date' => '2024-02-01',
            'departure_date' => null,
            'removal_date' => null,
            'concorre_escala' => true,
            'is_active' => true,
            'notes' => null,
        ]);

        $funcionarioB = RhFuncionario::query()->create([
            'matricula' => 'FUN-101',
            'name' => 'Bruno Impedido',
            'short_name' => 'Bruno',
            'email' => 'bruno.impedido@grom.local',
            'cargo_id' => $cargo->id,
            'sector' => 'Plantao',
            'phone' => '(11) 4000-1101',
            'rg' => '10.100.100-2',
            'cpf' => '100.100.100-11',
            'birth_date' => '1989-02-02',
            'admission_date' => '2024-01-10',
            'designation_date' => '2024-02-01',
            'departure_date' => null,
            'removal_date' => null,
            'concorre_escala' => true,
            'is_active' => true,
            'notes' => null,
        ]);

        RhAfastamento::query()->create([
            'funcionario_id' => $funcionarioA->id,
            'reason' => 'Ferias',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-15',
            'is_active' => true,
            'notes' => null,
        ]);

        RhAfastamento::query()->create([
            'funcionario_id' => $funcionarioB->id,
            'reason' => 'Licenca premio',
            'start_date' => '2026-04-12',
            'end_date' => '2026-04-18',
            'is_active' => true,
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->get('/calendarios?ano=2026&mes=4');

        $response->assertOk();
        $response->assertSee('Sobreposicao');
        $response->assertSee('Ana');
        $response->assertSee('Bruno');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/calendarios');

        $response->assertForbidden();
    }

    public function test_authorized_user_can_create_and_toggle_holiday(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $createResponse = $this->actingAs($user)->post('/calendarios/feriados', [
            'holiday_date' => '2026-04-21',
            'name' => 'Tiradentes local',
            'scope' => 'nacional',
            'notes' => 'Cadastro de teste',
            'is_active' => 1,
        ]);

        $createResponse->assertRedirect();
        $this->assertTrue(
            RhHoliday::query()
                ->whereDate('holiday_date', '2026-04-21')
                ->where('name', 'Tiradentes local')
                ->exists(),
        );

        $holiday = RhHoliday::query()->whereDate('holiday_date', '2026-04-21')->firstOrFail();

        $toggleResponse = $this->actingAs($user)->patch("/calendarios/feriados/{$holiday->id}/toggle-active", [
            'ano' => 2026,
            'mes' => 4,
        ]);

        $toggleResponse->assertRedirect();
        $this->assertDatabaseHas('rh_holidays', [
            'id' => $holiday->id,
            'is_active' => false,
        ]);
    }

    public function test_authorized_user_can_import_holidays_from_legacy_snapshot(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->post('/calendarios/importar-legado', [
            'ano' => 2026,
            'mes' => 4,
        ]);

        $response->assertRedirect();
        $this->assertTrue(
            RhHoliday::query()
                ->whereDate('holiday_date', '2026-04-21')
                ->where('name', 'Tiradentes')
                ->where('scope', 'nacional')
                ->where('is_active', true)
                ->exists(),
        );
    }
}
