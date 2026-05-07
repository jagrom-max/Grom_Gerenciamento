<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperacionalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/operacional');

        $response->assertRedirect('/login');
    }

    public function test_authorized_user_can_open_operational_panel(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/operacional?year=2026&month=4');

        $response->assertOk();
        $response->assertSee('Painel operacional');
        $response->assertSee('Cartorios reais do periodo');
        $response->assertSee('Base operacional ligada');
        $response->assertSee('Ranking operacional');
        $response->assertSee('Ação rápida');
        $response->assertSee('Pendencias envelhecidas');
        $response->assertSee('Espelho RH');
        $response->assertDontSee('Maria Souza');
        $response->assertDontSee('Carlos Lima');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/operacional');

        $response->assertForbidden();
    }
}
