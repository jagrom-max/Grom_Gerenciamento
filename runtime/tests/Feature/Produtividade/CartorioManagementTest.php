<?php

namespace Tests\Feature\Produtividade;

use App\Models\Cartorio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartorioManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_open_cartorios_index(): void
    {
        $user = $this->bootstrapUser();

        $response = $this->actingAs($user)->get('/produtividade/cartorios');

        $response->assertOk();
        $response->assertSee('Cartorios e produtividade mensal');
    }

    public function test_super_admin_can_create_cartorio_and_seed_histories(): void
    {
        $user = $this->bootstrapUser();

        $response = $this->actingAs($user)->post('/produtividade/cartorios', [
            'number' => 901,
            'name' => 'Cartorio Central',
            'designacao' => 'Central',
            'manager_name' => 'Delegado Responsavel',
            'notes' => 'Piloto web',
            'is_active' => 1,
        ]);

        $response->assertRedirect('/produtividade/cartorios');

        $this->assertDatabaseHas('cartorios', [
            'number' => 901,
            'code' => 'CRT-901',
            'name' => 'Cartorio Central',
            'manager_name' => 'Delegado Responsavel',
            'is_active' => 1,
        ]);

        $cartorioId = Cartorio::query()->where('number', 901)->value('id');

        $this->assertDatabaseHas('cartorio_status_history', [
            'cartorio_id' => $cartorioId,
            'status' => 'ATIVO',
        ]);

        $this->assertDatabaseHas('cartorio_manager_history', [
            'cartorio_id' => $cartorioId,
            'manager_name' => 'Delegado Responsavel',
        ]);
    }

    public function test_toggle_active_updates_status_history(): void
    {
        $user = $this->bootstrapUser();

        $cartorio = Cartorio::query()->create([
            'number' => 902,
            'code' => 'CRT-902',
            'name' => 'Cartorio Dois',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->patch("/produtividade/cartorios/{$cartorio->id}/toggle-active");

        $response->assertRedirect('/produtividade/cartorios');

        $cartorio->refresh();

        $this->assertFalse($cartorio->is_active);
        $this->assertDatabaseHas('cartorio_status_history', [
            'cartorio_id' => $cartorio->id,
            'status' => 'INATIVO',
        ]);
    }

    private function bootstrapUser(): User
    {
        $this->seed();

        /** @var User $user */
        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        return $user;
    }
}
