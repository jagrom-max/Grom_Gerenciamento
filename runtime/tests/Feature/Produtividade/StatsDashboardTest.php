<?php

namespace Tests\Feature\Produtividade;

use App\Enums\ImportItemStatus;
use App\Enums\LavradoUnidade;
use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\ProductivityStatMonthly;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_productivity_stats_dashboard(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/produtividade/estatisticas?year=2026&month=3');

        $response->assertOk();
        $response->assertSee('Estatisticas operacionais de produtividade');
        $response->assertSee('Cartorios reais espelhados');
        $response->assertSee('Ranking operacional');
        $response->assertSee('Pendencias envelhecidas');
        $response->assertSee('Base de apoio operacional');
        $response->assertSee('Funcionários RH');
        $response->assertDontSee('Maria Souza');
        $response->assertDontSee('Carlos Lima');
    }

    public function test_authorized_user_can_filter_and_export_productivity_stats(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $cartorio = Cartorio::query()->create([
            'number' => 30,
            'code' => 'CRT-030',
            'name' => 'Cartorio Estatisticas',
            'is_active' => true,
        ]);

        ProductivityStatMonthly::query()->create([
            'cartorio_id' => $cartorio->id,
            'reference_year' => 2026,
            'reference_month' => 3,
            'ip_instaurados' => 11,
            'ip_relatados' => 7,
            'cotas' => 2,
            'despachos' => 4,
            'concluidos' => 3,
            'registros' => 8,
            'ips_andamento' => 5,
            'flagrantes_total' => 4,
            'flagrantes_ddm' => 2,
            'flagrantes_outras' => 2,
            'source_mode' => 'AUTO',
        ]);

        $batch = ImportBatch::query()->create([
            'source_name' => 'stats.csv',
            'source_type' => 'CSV',
            'imported_by' => $user->id,
            'imported_at' => now()->subDays(10),
            'processed_at' => now()->subDays(10),
            'total_rows' => 1,
            'rows_staged' => 1,
            'rows_updated' => 0,
            'rows_skipped' => 0,
            'error_count' => 0,
        ]);

        ImportItem::query()->create([
            'batch_id' => $batch->id,
            'cartorio_id' => $cartorio->id,
            'source_process_key' => 'SPJ-STATS-1',
            'spj' => 'SPJ-STATS-1',
            'naturezas' => 'Estatistica',
            'data_fato' => '2026-03-01',
            'lavrado_unidade' => LavradoUnidade::Ddm,
            'import_status' => ImportItemStatus::Pending,
        ]);

        $response = $this->actingAs($user)->get('/produtividade/estatisticas?year=2026&month=3&cartorio_id=' . $cartorio->id);

        $response->assertOk();
        $response->assertSee('Cartorio Estatisticas');
        $response->assertSee('11');
        $response->assertSee('4');

        $exportResponse = $this->actingAs($user)->get('/produtividade/estatisticas/exportar?year=2026&month=3&cartorio_id=' . $cartorio->id);

        $exportResponse->assertOk();
        $exportResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $exportResponse->assertSee('Estatisticas de produtividade');
        $exportResponse->assertSee('Cartorio Estatisticas');
    }

    public function test_user_without_permission_is_forbidden_from_productivity_stats_dashboard(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/produtividade/estatisticas');

        $response->assertForbidden();
    }
}
