<?php

namespace Tests\Feature\Produtividade;

use App\Enums\ImportItemStatus;
use App\Enums\LavradoUnidade;
use App\Models\AuditEvent;
use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\Role;
use App\Models\UserScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FlagranteImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_import_external_csv_into_pending_queue(): void
    {
        $context = $this->bootstrapContext();

        $csv = implode("\n", [
            'spj;naturezas;data_fato;status;num_ip;num_ipe;num_cnj;cartorio_designado;lavrado_unidade',
            'SPJ-100;Roubo;11/03/2026;Flagrante;IP-100;;CNJ-100;10 - Cartorio Dez;DDM',
            'SPJ-200;Furto;12/03/2026;SPJ com MPU;IP-200;;;10 - Cartorio Dez;Outras Unidades',
        ]);

        $response = $this->actingAs($context['user'])->post('/produtividade/flagrantes/importar', [
            'source_file' => UploadedFile::fake()->createWithContent('consolidacao.csv', $csv),
            'cartorio_id' => $context['cartorio']->id,
            'filter_year' => 2026,
            'filter_month' => 3,
        ]);

        $response->assertRedirect();

        $batch = ImportBatch::query()
            ->where('source_name', 'consolidacao.csv')
            ->firstOrFail();
        $item = ImportItem::query()->firstOrFail();

        $this->assertSame('CSV', $batch->source_type);
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(1, $batch->rows_staged);
        $this->assertSame(1, $batch->rows_skipped);
        $this->assertSame(0, $batch->error_count);
        $this->assertSame(ImportItemStatus::Pending, $item->import_status);
        $this->assertSame($context['cartorio']->id, $item->cartorio_id);
        $this->assertSame('SPJ-100', $item->spj);
        $this->assertSame('IP-100', $item->num_ip);
        $this->assertSame(LavradoUnidade::Ddm, $item->lavrado_unidade);
    }

    public function test_reimport_supersedes_previous_pending_item_and_keeps_only_latest_pending(): void
    {
        $context = $this->bootstrapContext();

        $firstCsv = implode("\n", [
            'spj;naturezas;data_fato;status;num_ip;num_ipe;num_cnj;cartorio_designado;lavrado_unidade',
            'SPJ-300;Roubo;13/03/2026;Flagrante;IP-300;;;10 - Cartorio Dez;Outras Unidades',
        ]);

        $secondCsv = implode("\n", [
            'spj;naturezas;data_fato;status;num_ip;num_ipe;num_cnj;cartorio_designado;lavrado_unidade',
            'SPJ-300;Roubo;13/03/2026;Flagrante;IP-300;IPE-300;CNJ-300;10 - Cartorio Dez;DDM',
        ]);

        $this->actingAs($context['user'])->post('/produtividade/flagrantes/importar', [
            'source_file' => UploadedFile::fake()->createWithContent('primeiro.csv', $firstCsv),
        ]);

        $response = $this->actingAs($context['user'])->post('/produtividade/flagrantes/importar', [
            'source_file' => UploadedFile::fake()->createWithContent('segundo.csv', $secondCsv),
        ]);

        $response->assertRedirect();

        $this->assertSame(2, ImportItem::query()->count());
        $this->assertSame(1, ImportItem::query()->where('import_status', ImportItemStatus::Pending->value)->count());
        $this->assertSame(1, ImportItem::query()->where('import_status', ImportItemStatus::Rejected->value)->count());

        $pending = ImportItem::query()
            ->where('import_status', ImportItemStatus::Pending->value)
            ->firstOrFail();
        $rejected = ImportItem::query()
            ->where('import_status', ImportItemStatus::Rejected->value)
            ->firstOrFail();
        $batch = ImportBatch::query()
            ->where('source_name', 'segundo.csv')
            ->firstOrFail();

        $this->assertSame('IPE-300', $pending->num_ipe);
        $this->assertSame('CNJ-300', $pending->num_cnj);
        $this->assertSame(LavradoUnidade::Ddm, $pending->lavrado_unidade);
        $this->assertStringContainsString('Substituido por consolidacao mais recente', (string) $rejected->rejected_reason);
        $this->assertSame(1, $batch->rows_staged);
        $this->assertSame(1, $batch->rows_updated);
    }

    public function test_selected_cartorio_is_used_as_fallback_when_file_has_no_clear_mapping(): void
    {
        $context = $this->bootstrapContext();

        $csv = implode("\n", [
            'spj;naturezas;data_fato;status;num_ip;num_ipe;num_cnj;cartorio_designado;lavrado_unidade',
            'SPJ-400;Estelionato;14/03/2026;Flagrante;IP-400;;;;Outras Unidades',
        ]);

        $response = $this->actingAs($context['user'])->post('/produtividade/flagrantes/importar', [
            'source_file' => UploadedFile::fake()->createWithContent('fallback.csv', $csv),
            'cartorio_id' => $context['cartorio']->id,
        ]);

        $response->assertRedirect();

        $item = ImportItem::query()->firstOrFail();

        $this->assertSame($context['cartorio']->id, $item->cartorio_id);
        $this->assertSame('SPJ-400', $item->spj);
    }

    public function test_user_can_sync_flagrantes_from_legacy_sqlite(): void
    {
        $context = $this->bootstrapContext();
        $legacyPath = $this->createLegacyAnaliseDatabase();

        config([
            'grom_legacy.enabled' => true,
            'grom_legacy.analise_db_path' => $legacyPath,
        ]);

        $response = $this->actingAs($context['user'])->post('/produtividade/flagrantes/sincronizar-legado', [
            'filter_year' => 2026,
            'filter_month' => 3,
        ]);

        $response->assertRedirect();

        $batch = ImportBatch::query()->where('source_type', 'LEGACY_SQLITE')->firstOrFail();
        $item = ImportItem::query()->where('batch_id', $batch->id)->firstOrFail();

        $this->assertStringStartsWith('legacy_analise_', $batch->source_name);
        $this->assertSame(1, $batch->rows_staged);
        $this->assertSame(0, $batch->error_count);
        $this->assertSame($context['cartorio']->id, $item->cartorio_id);
        $this->assertSame('SPJ-500/2026', $item->spj);
        $this->assertSame('IP-500', $item->num_ip);
        $this->assertSame('CNJ-500', $item->num_cnj);
        $this->assertSame(LavradoUnidade::Ddm, $item->lavrado_unidade);
        $this->assertSame('Roubo; Furto', $item->naturezas);
    }

    public function test_import_respects_cartorio_scope_and_skips_unauthorized_rows(): void
    {
        $this->seed();

        $role = Role::query()->where('code', 'gestor_cartorio')->firstOrFail();

        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $user->roles()->attach($role->id, ['assigned_by' => null]);

        $allowedCartorio = Cartorio::query()->firstOrCreate(
            ['code' => 'CRT-010'],
            ['number' => 10, 'name' => 'Cartorio Permitido', 'is_active' => true],
        );

        $blockedCartorio = Cartorio::query()->firstOrCreate(
            ['code' => 'CRT-020'],
            ['number' => 20, 'name' => 'Cartorio Bloqueado', 'is_active' => true],
        );

        UserScope::query()->create([
            'user_id' => $user->id,
            'scope_type' => 'cartorio',
            'scope_key' => $allowedCartorio->id,
            'created_by' => null,
        ]);

        $csv = implode("\n", [
            'spj;naturezas;data_fato;status;num_ip;num_ipe;num_cnj;cartorio_designado;lavrado_unidade',
            'SPJ-600;Roubo;15/03/2026;Flagrante;IP-600;;CNJ-600;10 - Cartorio Permitido;DDM',
            'SPJ-700;Furto;16/03/2026;Flagrante;IP-700;;CNJ-700;20 - Cartorio Bloqueado;DDM',
        ]);

        $response = $this->actingAs($user)->post('/produtividade/flagrantes/importar', [
            'source_file' => UploadedFile::fake()->createWithContent('escopo.csv', $csv),
        ]);

        $response->assertRedirect();

        $batch = ImportBatch::query()->where('source_name', 'escopo.csv')->firstOrFail();
        $items = ImportItem::query()->where('batch_id', $batch->id)->get();

        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(1, $batch->rows_staged);
        $this->assertSame(1, $batch->rows_skipped);
        $this->assertSame(1, $items->count());
        $this->assertSame($allowedCartorio->id, $items->firstOrFail()->cartorio_id);
        $this->assertDatabaseMissing('import_items', [
            'batch_id' => $batch->id,
            'cartorio_id' => $blockedCartorio->id,
        ]);
    }

    public function test_user_can_assign_pending_item_without_cartorio_to_a_cartorio_queue(): void
    {
        $context = $this->bootstrapContext();

        $csv = implode("\n", [
            'spj;naturezas;data_fato;status;num_ip;num_ipe;num_cnj;cartorio_designado;lavrado_unidade',
            'SPJ-410;Estelionato;14/03/2026;Flagrante;IP-410;;; ;Outras Unidades',
        ]);

        $this->actingAs($context['user'])->post('/produtividade/flagrantes/importar', [
            'source_file' => UploadedFile::fake()->createWithContent('sem-cartorio.csv', $csv),
            'filter_year' => 2026,
            'filter_month' => 3,
        ])->assertRedirect();

        $item = ImportItem::query()->firstOrFail();

        $this->assertNull($item->cartorio_id);
        $this->assertSame(ImportItemStatus::Pending, $item->import_status);

        $this->actingAs($context['user'])
            ->get('/produtividade/flagrantes?cartorio_id='.$context['cartorio']->id.'&year=2026&month=3')
            ->assertOk()
            ->assertSee('Pendencias sem cartorio mapeado')
            ->assertSee('SPJ-410');

        $response = $this->actingAs($context['user'])->post("/produtividade/flagrantes/import-items/{$item->id}/assign-cartorio", [
            'cartorio_id' => $context['cartorio']->id,
            'filter_cartorio_id' => $context['cartorio']->id,
            'filter_year' => 2026,
            'filter_month' => 3,
        ]);

        $response->assertRedirect('/produtividade/flagrantes?cartorio_id='.$context['cartorio']->id.'&year=2026&month=3');

        $item->refresh();

        $this->assertSame($context['cartorio']->id, $item->cartorio_id);
        $this->assertSame('010 - Cartorio Dez', $item->cartorio_hint);
        $this->assertSame($context['user']->id, data_get($item->payload, 'manual_assignment.assigned_by'));
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'flagrantes.queue_assign_cartorio',
            'entity_id' => $item->id,
        ]);
        $this->assertSame(1, AuditEvent::query()->where('event_type', 'flagrantes.queue_assign_cartorio')->count());
    }

    public function test_console_command_can_sync_flagrantes_from_legacy_sqlite(): void
    {
        $context = $this->bootstrapContext();
        $legacyPath = $this->createLegacyAnaliseDatabase();

        config([
            'grom_legacy.enabled' => true,
            'grom_legacy.analise_db_path' => $legacyPath,
        ]);

        Artisan::call('grom:sync-legacy-analise-flagrantes', [
            '--actor' => $context['user']->username,
        ]);

        $this->assertSame(1, ImportBatch::query()->where('source_type', 'LEGACY_SQLITE')->count());
        $this->assertSame(1, ImportItem::query()->count());
    }

    private function bootstrapContext(): array
    {
        $this->seed();

        /** @var User $user */
        $user = User::query()->firstOrFail();
        $user->update(['must_change_password' => false]);

        $cartorio = Cartorio::query()->firstOrCreate(
            ['code' => 'CRT-010'],
            ['number' => 10, 'name' => 'Cartorio Dez', 'is_active' => true],
        );

        return compact('user', 'cartorio');
    }

    private function createLegacyAnaliseDatabase(): string
    {
        $path = database_path('legacy_analise_'.uniqid('', true).'.sqlite');

        if (file_exists($path)) {
            @unlink($path);
        }

        touch($path);

        $legacy = new \SQLite3($path);
        $legacy->exec('PRAGMA journal_mode=MEMORY');
        $legacy->exec('PRAGMA synchronous=OFF');
        $legacy->exec('PRAGMA temp_store=MEMORY');
        $legacy->exec('CREATE TABLE analise_ocorrencias (spj TEXT, spj_fmt TEXT, data_ocorrencia TEXT, flagrante INTEGER, mpu_numero TEXT, num_ip TEXT, num_ip_e TEXT, cnj_mpu TEXT, cnj_ip_importado TEXT, cartorio_designado TEXT, cartorio_ip TEXT, lavrado TEXT)');
        $legacy->exec('CREATE TABLE analise_ocorrencias_extra (spj TEXT PRIMARY KEY, lavrado_unidade TEXT)');
        $legacy->exec('CREATE TABLE analise_naturezas (spj TEXT, slot INTEGER, natureza TEXT)');

        $legacy->exec("INSERT INTO analise_ocorrencias VALUES ('SPJ-500', 'SPJ-500/2026', '2026-03-15', 1, '', 'IP-500', '', 'CNJ-500', '', '10 - Cartorio Dez', '', '')");
        $legacy->exec("INSERT INTO analise_ocorrencias_extra VALUES ('SPJ-500', 'DDM')");
        $legacy->exec("INSERT INTO analise_naturezas VALUES ('SPJ-500', 1, 'Roubo')");
        $legacy->exec("INSERT INTO analise_naturezas VALUES ('SPJ-500', 2, 'Furto')");
        $legacy->close();

        return $path;
    }
}
