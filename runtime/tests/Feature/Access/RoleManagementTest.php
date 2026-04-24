<?php

namespace Tests\Feature\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_roles_dashboard(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/access/roles');

        $response->assertOk();
        $response->assertSee('Perfis de acesso');
        $response->assertSee('Base central do RBAC');
    }

    public function test_admin_can_create_role_with_permissions(): void
    {
        $this->seed();

        $admin = User::query()->firstOrFail();
        $admin->update(['must_change_password' => false]);

        $permissionIds = Permission::query()
            ->whereIn('code', ['dashboard.view', 'relatorios.emit'])
            ->pluck('id')
            ->all();

        $response = $this->actingAs($admin)->post('/access/roles', [
            'code' => 'fiscal',
            'name' => 'Fiscal',
            'description' => 'Perfil para fiscalizacao e consulta',
            'permissions' => $permissionIds,
        ]);

        $response->assertRedirect(route('access.roles.index'));

        $role = Role::query()->where('code', 'fiscal')->firstOrFail();
        $this->assertSame('Fiscal', $role->name);
        $this->assertSame(2, $role->permissions()->count());
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $this->seed();

        $admin = User::query()->firstOrFail();
        $admin->update(['must_change_password' => false]);

        $role = Role::query()->create([
            'code' => 'analista_base',
            'name' => 'Analista Base',
            'description' => 'Perfil inicial',
        ]);

        $permissionIds = Permission::query()
            ->whereIn('code', ['dashboard.view', 'analise.view'])
            ->pluck('id')
            ->all();

        $response = $this->actingAs($admin)->put("/access/roles/{$role->id}", [
            'code' => 'analista_base',
            'name' => 'Analista Base Atualizado',
            'description' => 'Perfil revisado',
            'permissions' => $permissionIds,
        ]);

        $response->assertRedirect(route('access.roles.index'));

        $role->refresh();
        $this->assertSame('Analista Base Atualizado', $role->name);
        $this->assertSame(2, $role->permissions()->count());
    }

    public function test_user_without_manage_permission_cannot_create_roles(): void
    {
        $this->seed();

        $auditorRole = Role::query()->where('code', 'auditor')->firstOrFail();
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $user->roles()->attach($auditorRole->id, ['assigned_by' => null]);

        $response = $this->actingAs($user)->post('/access/roles', [
            'code' => 'bloqueado',
            'name' => 'Bloqueado',
        ]);

        $response->assertForbidden();
    }
}
