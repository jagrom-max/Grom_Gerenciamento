<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\AccessCredentialPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function bootstrapPassword(User $user): string
    {
        $configured = (string) config('grom_access.bootstrap_admin.password');

        return $configured !== ''
            ? $configured
            : AccessCredentialPolicy::firstAccessPassword($user->cpf ?? AccessCredentialPolicy::superManagerCpf());
    }

    public function test_login_screen_is_available(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Acesso Grom.Seg');
        $response->assertDontSee('Selecione o sistema');
    }

    public function test_user_can_authenticate_with_username(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();

        $response = $this->post('/login', [
            'login' => $user->username,
            'password' => $this->bootstrapPassword($user),
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['is_active' => false]);

        $response = $this->from('/login')->post('/login', [
            'login' => $user->username,
            'password' => $this->bootstrapPassword($user),
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }
}

