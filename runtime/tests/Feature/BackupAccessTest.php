<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/backup');

        $response->assertRedirect('/login');
    }

    public function test_authorized_user_can_open_backup_page(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/backup');

        $response->assertOk();
        $response->assertSee('Backup');
        $response->assertSee('Bases locais');
        $response->assertSee('Relatorios recentes');
        $response->assertSee('Caminhos observados');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/backup');

        $response->assertForbidden();
    }
}
