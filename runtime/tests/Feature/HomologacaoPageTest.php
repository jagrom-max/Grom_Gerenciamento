<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomologacaoPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_homologacao_page_in_local_or_testing_env(): void
    {
        $this->seed();

        $response = $this->get('/homologacao');

        $response->assertOk();
        $response->assertSee('Painel de evolucao e aprovacao');
        $response->assertSee('Panorama de aprovacao');
        $response->assertSee('A4 padronizado');
        $response->assertSee('RBAC com perfis e permissoes');
        $response->assertSee('Operacional');
        $response->assertSee('Funcionarios');
        $response->assertSee('Escalas e plantões legados');
        $response->assertSee('Agenda de afastamentos consultada');
        $response->assertSee('Backup local');
        $response->assertSee('Abrir evolucao');
        $response->assertSee('Abrir modulo');
    }

    public function test_guest_can_open_evolucao_alias_in_local_or_testing_env(): void
    {
        $this->seed();

        $response = $this->get('/evolucao');

        $response->assertOk();
        $response->assertSee('Painel de evolucao e aprovacao');
        $response->assertSee('Versao homologacao');
        $response->assertSee('Operacional');
        $response->assertSee('Funcionarios');
        $response->assertSee('Escalas do mes');
        $response->assertSee('Agenda de afastamentos');
        $response->assertSee('Backup');
    }
}
