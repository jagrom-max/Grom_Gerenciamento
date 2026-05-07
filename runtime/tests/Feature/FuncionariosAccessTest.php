<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuncionariosAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/funcionarios');

        $response->assertRedirect('/login');
    }

    public function test_authorized_user_can_open_funcionarios_dashboard(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/funcionarios');

        $response->assertOk();
        $response->assertSee('Funcionários');
        $response->assertSee('Confronto');
        $response->assertSee('Cargos');
        $response->assertSee('Afastamentos');
        $response->assertSee('Delegados Externos');
        $response->assertSee('Novo servidor');
        $response->assertDontSee('Maria Souza');
        $response->assertDontSee('Carlos Lima');
    }

    public function test_admin_can_create_funcionario_with_full_personal_data(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $cargo = \App\Models\RhCargo::query()->firstOrFail();

        $this->actingAs($user)->post('/rh/funcionarios', [
            'matricula' => 'FUN-010',
            'name' => 'Patricia Alves',
            'short_name' => 'Patricia Alves',
            'email' => 'patricia.alves@grom.local',
            'cargo_id' => $cargo->id,
            'sector' => 'Cartorio Central',
            'phone' => '(11) 98888-0100',
            'rg' => '10.010.010-1',
            'cpf' => '010.010.010-10',
            'birth_date' => '1991-04-01',
            'admission_date' => '2026-04-01',
            'designation_date' => '2026-04-02',
            'departure_date' => null,
            'removal_date' => null,
            'concorre_escala' => 1,
            'notes' => 'Entrada demonstrativa do modulo de RH.',
            'is_active' => 1,
        ])->assertRedirect('/rh');

        $this->assertDatabaseHas('rh_funcionarios', [
            'matricula' => 'FUN-010',
            'name' => 'Patricia Alves',
            'cargo_id' => $cargo->id,
            'sector' => 'Cartorio Central',
            'phone' => '(11) 98888-0100',
            'rg' => '10.010.010-1',
            'cpf' => '010.010.010-10',
            'concorre_escala' => true,
        ]);
    }

    public function test_user_without_permission_is_forbidden_from_funcionarios_dashboard(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/funcionarios');

        $response->assertForbidden();
    }
}
