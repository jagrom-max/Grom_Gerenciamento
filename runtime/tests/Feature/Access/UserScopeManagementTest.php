<?php

namespace Tests\Feature\Access;

use App\Models\Cartorio;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserScopeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_and_remove_user_scope(): void
    {
        $admin = $this->bootstrapAdmin();
        $target = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $cartorio = Cartorio::query()->firstOrCreate(
            ['code' => 'CRT-010'],
            ['number' => 10, 'name' => 'Cartorio Central', 'is_active' => true],
        );

        $response = $this->actingAs($admin)->post("/access/users/{$target->id}/scopes", [
            'scope_type' => 'cartorio',
            'scope_key' => $cartorio->id,
        ]);

        $response->assertRedirect(route('access.users.index'));
        $this->assertDatabaseHas('user_scopes', [
            'user_id' => $target->id,
            'scope_type' => 'cartorio',
            'scope_key' => $cartorio->id,
        ]);

        $scope = UserScope::query()->where('user_id', $target->id)->firstOrFail();

        $removeResponse = $this->actingAs($admin)->delete("/access/users/{$target->id}/scopes/{$scope->id}");

        $removeResponse->assertRedirect(route('access.users.index'));
        $this->assertDatabaseMissing('user_scopes', [
            'id' => $scope->id,
        ]);
    }

    public function test_cartorio_scope_limits_cartorio_listing(): void
    {
        $this->seed();

        $role = Role::query()->where('code', 'operador')->firstOrFail();
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $user->roles()->attach($role->id, ['assigned_by' => null]);

        $allowed = Cartorio::query()->firstOrCreate(
            ['code' => 'CRT-020'],
            ['number' => 20, 'name' => 'Cartorio Permitido', 'is_active' => true],
        );

        Cartorio::query()->create([
            'number' => 30,
            'code' => 'CRT-030',
            'name' => 'Cartorio Oculto',
            'is_active' => true,
        ]);

        UserScope::query()->create([
            'user_id' => $user->id,
            'scope_type' => 'cartorio',
            'scope_key' => $allowed->id,
            'created_by' => null,
        ]);

        $response = $this->actingAs($user)->get('/produtividade/cartorios');

        $response->assertOk();
        $response->assertSee('Cartorio Permitido');
        $response->assertDontSee('Cartorio Oculto');
    }

    private function bootstrapAdmin(): User
    {
        $this->seed();

        /** @var User $user */
        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        return $user;
    }
}
