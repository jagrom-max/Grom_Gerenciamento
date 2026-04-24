<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\AccessCredentialPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function bootstrapPassword(User $user): string
    {
        $configured = (string) config('grom_access.bootstrap_admin.password');

        return $configured !== ''
            ? $configured
            : AccessCredentialPolicy::firstAccessPassword($user->cpf ?? AccessCredentialPolicy::superManagerCpf());
    }

    public function test_user_with_pending_password_change_can_access_dashboard_when_requirement_is_disabled(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_user_can_change_password_and_clear_pending_flag(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();

        $response = $this->actingAs($user)->put('/password/change', [
            'current_password' => $this->bootstrapPassword($user),
            'password' => 'NovaSenha#2026Segura',
            'password_confirmation' => 'NovaSenha#2026Segura',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NovaSenha#2026Segura', $user->password));
    }

    public function test_user_with_pending_password_change_is_redirected_when_requirement_is_enabled(): void
    {
        config()->set('grom_access.require_password_change', true);

        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => true]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('password.edit'));
    }
}
