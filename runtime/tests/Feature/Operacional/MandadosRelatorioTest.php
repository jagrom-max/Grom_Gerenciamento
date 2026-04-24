<?php

namespace Tests\Feature\Operacional;

use App\Models\OperacionalMandado;
use App\Models\User;
use App\Support\Pdf\HeadlessBrowserPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MandadosRelatorioTest extends TestCase
{
    use RefreshDatabase;

    public function test_relatorio_de_mandados_usa_uma_unica_coluna_de_procedimento_com_cumprimento_compacto(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        OperacionalMandado::query()->create([
            'tipo_sigla' => 'MPP',
            'tipo_mandado' => 'Mandado de Prisão',
            'subtipo_prisao' => 'Preventivo',
            'cnj_numero' => '0000001-00.2026.8.26.0000',
            'nome' => 'João Exemplo',
            'data_emissao' => '2026-04-01',
            'validade' => '2026-05-01',
            'procedimento' => 'Cumprido',
            'cumprido_por' => 'PM',
            'data_cumprimento' => '2026-04-12',
            'bo_numero' => 'PM0123/2026',
        ]);

        OperacionalMandado::query()->create([
            'tipo_sigla' => 'MPC',
            'tipo_mandado' => 'Mandado de Prisão',
            'subtipo_prisao' => 'Civil',
            'cnj_numero' => '0000002-00.2026.8.26.0000',
            'nome' => 'Maria Exemplo',
            'data_emissao' => '2026-04-02',
            'validade' => '2026-05-02',
            'procedimento' => 'Cumprido',
            'cumprido_por' => 'Polícia Civil',
            'data_cumprimento' => '2026-04-13',
            'bo_numero' => 'PCSP0456/2026',
        ]);

        OperacionalMandado::query()->create([
            'tipo_sigla' => 'MPT',
            'tipo_mandado' => 'Mandado de Prisão',
            'subtipo_prisao' => 'Temporário',
            'cnj_numero' => '0000003-00.2026.8.26.0000',
            'nome' => 'Revogado Exemplo',
            'data_emissao' => '2026-04-03',
            'validade' => '2026-05-03',
            'procedimento' => 'Revogado',
        ]);

        $response = $this->actingAs($user)->get(route('operacional.mandados.relatorio'));

        $response->assertOk();
        $response->assertSee('PM/GCM');
        $response->assertSee('PCSP');
        $response->assertSee('BO PM0123/2026');
        $response->assertSee('Revogado');
        $response->assertSee('Nome / Alvo');
        $response->assertSee('Cartório Central - Gerenciamento');
        $response->assertSee('Baixar PDF');
        $response->assertSee('Procedimento');
        $response->assertDontSee('<th>Cumprimento</th>');
        $response->assertDontSee('Índice');
        $response->assertDontSee('Vencidos (Em Aberto)');
        $response->assertDontSee('mandados no filtro');
        $response->assertDontSee('Imprimir / PDF');
    }

    public function test_relatorio_pdf_usa_renderizador_sem_cabecalho_do_navegador(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $renderer = new class extends HeadlessBrowserPdfRenderer {
            public string $view = '';

            public array $data = [];

            public function renderBlade(string $view, array $data, ?string $filePrefix = null): string
            {
                $this->view = $view;
                $this->data = $data;

                $path = tempnam(sys_get_temp_dir(), 'mandados-relatorio-');
                $pdfPath = $path . '.pdf';
                rename($path, $pdfPath);
                file_put_contents($pdfPath, '%PDF-1.4' . PHP_EOL . '% fake pdf for tests');

                return $pdfPath;
            }
        };

        app()->instance(HeadlessBrowserPdfRenderer::class, $renderer);

        for ($i = 1; $i <= 15; $i++) {
            OperacionalMandado::query()->create([
                'tipo_sigla' => $i % 2 === 0 ? 'MPC' : 'MPP',
                'tipo_mandado' => 'Mandado de Prisão',
                'subtipo_prisao' => 'Preventivo',
                'cnj_numero' => sprintf('%07d-00.2026.8.26.0000', $i),
                'nome' => 'Mandado ' . $i,
                'data_emissao' => '2026-04-' . str_pad((string) min($i, 28), 2, '0', STR_PAD_LEFT),
                'validade' => '2026-05-31',
                'procedimento' => $i % 3 === 0 ? 'Cumprido' : 'Em Aberto',
                'cumprido_por' => $i % 3 === 0 ? ($i % 2 === 0 ? 'Polícia Civil' : 'PM') : null,
                'data_cumprimento' => $i % 3 === 0 ? '2026-04-12' : null,
                'bo_numero' => $i % 3 === 0 ? 'BO' . $i . '/2026' : null,
            ]);
        }

        $response = $this->actingAs($user)->get(route('operacional.mandados.relatorio.pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
        $this->assertSame('operacional.mandados.relatorio', $renderer->view);
        $this->assertCount(15, $renderer->data['mandados']);
        $this->assertStringStartsWith('data:', $renderer->data['brasaoSrc']);
        $this->assertStringStartsWith('data:', $renderer->data['logoSrc']);
    }
}
