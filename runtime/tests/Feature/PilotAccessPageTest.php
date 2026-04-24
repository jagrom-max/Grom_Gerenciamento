<?php

namespace Tests\Feature;

use App\Support\AccessCredentialPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotAccessPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_pilot_access_page_in_local_or_testing_env(): void
    {
        $response = $this->get('/acesso-teste');

        $response->assertOk();
        $response->assertSee('Acesso de teste do piloto');
        $response->assertSee(config('grom_access.bootstrap_admin.username', 'admin'));
        $response->assertSee('gestor.demo');
        $response->assertSee('operador.demo');
        $configured = (string) config('grom_access.bootstrap_admin.password');
        $cpf = AccessCredentialPolicy::normalizeCpf((string) config('grom_access.bootstrap_admin.cpf'));
        $expectedPassword = $configured !== ''
            ? $configured
            : AccessCredentialPolicy::firstAccessPassword($cpf !== '' ? $cpf : AccessCredentialPolicy::superManagerCpf());
        $response->assertSee($expectedPassword);
    }

    public function test_login_page_exposes_link_to_pilot_access_page_in_local_or_testing_env(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Ver credenciais');
        $response->assertSee('Evolução');
    }

    public function test_pilot_access_page_exposes_link_to_homologacao_page_in_local_or_testing_env(): void
    {
        $response = $this->get('/acesso-teste');

        $response->assertOk();
        $response->assertSee('Abrir evolucao');
    }
}
