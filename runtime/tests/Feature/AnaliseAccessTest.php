<?php

namespace Tests\Feature;

use App\Enums\ImportItemStatus;
use App\Enums\LavradoUnidade;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnaliseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_analise_dashboard(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/analise');

        $response->assertOk();
        $response->assertSee('Analise de Dados');
        $response->assertSee('Entrada da consolidacao web');
        $response->assertSee('Origem dos lotes');
        $response->assertSee('Pendencias recentes');
        $response->assertSee('Qualidade da fila');
        $response->assertSee('Cartorios em atencao');
        $response->assertSee('por origem');
    }

    public function test_user_without_permission_is_forbidden_from_analise_dashboard(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/analise');

        $response->assertForbidden();
    }

    public function test_authorized_user_can_open_batch_detail(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $batch = ImportBatch::query()->create([
            'source_name' => 'lote_teste.csv',
            'source_type' => 'CSV',
            'imported_by' => $user->id,
            'imported_at' => now(),
            'processed_at' => now(),
            'total_rows' => 1,
            'rows_staged' => 1,
            'rows_updated' => 0,
            'rows_skipped' => 0,
            'error_count' => 0,
        ]);

        ImportItem::query()->create([
            'batch_id' => $batch->id,
            'source_process_key' => 'SPJ-AN-1',
            'spj' => 'SPJ-AN-1',
            'naturezas' => 'Roubo',
            'data_fato' => '2026-03-15',
            'status_origem' => 'Flagrante',
            'lavrado_unidade' => LavradoUnidade::Ddm,
            'import_status' => ImportItemStatus::Pending,
        ]);

        $response = $this->actingAs($user)->get("/analise/lotes/{$batch->id}");

        $response->assertOk();
        $response->assertSee('Detalhe do lote');
        $response->assertSee('lote_teste.csv');
        $response->assertSee('Itens do lote');
        $response->assertSee('Diagnostico de qualidade');
        $response->assertSee('Origem / Status');
        $response->assertSee('Payload:');
    }

    public function test_authorized_user_can_export_pending_items_csv(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get('/analise/exportar/pendencias');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertSee('Pendencias da Analise de Dados');
        $response->assertSee('source_process_key');
        $response->assertSee('reference_year');
        $response->assertSee('payload_source');
    }

    public function test_authorized_user_can_export_batch_csv(): void
    {
        $this->seed();

        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $batch = ImportBatch::query()->create([
            'source_name' => 'lote_export.csv',
            'source_type' => 'CSV',
            'imported_by' => $user->id,
            'imported_at' => now(),
            'processed_at' => now(),
            'total_rows' => 1,
            'rows_staged' => 1,
            'rows_updated' => 0,
            'rows_skipped' => 0,
            'error_count' => 0,
        ]);

        $response = $this->actingAs($user)->get("/analise/lotes/{$batch->id}/exportar");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertSee('Lote lote_export.csv');
        $response->assertSee('source_type');
        $response->assertSee('sheet_name');
        $response->assertSee('payload_kind');
    }
}
