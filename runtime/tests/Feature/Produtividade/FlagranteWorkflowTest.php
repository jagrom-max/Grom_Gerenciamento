<?php

namespace Tests\Feature\Produtividade;

use App\Enums\ImportItemStatus;
use App\Enums\LavradoUnidade;
use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\ProductivityFlagrante;
use App\Models\ProductivityStatMonthly;
use App\Models\Role;
use App\Models\UserScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlagranteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_flagrantes_index_loads_for_authorized_user(): void
    {
        $context = $this->bootstrapContext();

        $response = $this->actingAs($context['user'])->get('/produtividade/flagrantes');

        $response->assertOk();
        $response->assertSee('Flagrantes e fila de confirmacao');
    }

    public function test_confirming_import_item_creates_flagrante_and_updates_monthly_stats(): void
    {
        $context = $this->bootstrapContext();

        $item = $this->makePendingImportItem($context['cartorio'], [
            'source_process_key' => 'SPJ-001',
            'spj' => 'SPJ-001',
            'num_ip' => 'IP-001',
            'data_fato' => '2026-03-11',
            'lavrado_unidade' => LavradoUnidade::Ddm,
        ]);

        $response = $this->actingAs($context['user'])->post("/produtividade/flagrantes/import-items/{$item->id}/confirm", [
            'filter_year' => 2026,
            'filter_month' => 3,
        ]);

        $response->assertRedirect();

        $item->refresh();

        $this->assertSame(ImportItemStatus::Confirmed, $item->import_status);
        $this->assertDatabaseHas('produtividade_flagrantes', [
            'cartorio_id' => $context['cartorio']->id,
            'source_item_id' => $item->id,
            'spj' => 'SPJ-001',
            'num_ip' => 'IP-001',
            'lavrado_unidade' => LavradoUnidade::Ddm->value,
        ]);

        $stats = ProductivityStatMonthly::query()
            ->where('cartorio_id', $context['cartorio']->id)
            ->where('reference_year', 2026)
            ->where('reference_month', 3)
            ->firstOrFail();

        $this->assertSame(1, $stats->flagrantes_total);
        $this->assertSame(1, $stats->flagrantes_ddm);
        $this->assertSame(0, $stats->flagrantes_outras);
    }

    public function test_rejecting_import_item_marks_it_without_creating_flagrante(): void
    {
        $context = $this->bootstrapContext();

        $item = $this->makePendingImportItem($context['cartorio'], [
            'source_process_key' => 'SPJ-002',
            'spj' => 'SPJ-002',
            'num_ip' => 'IP-002',
            'data_fato' => '2026-03-12',
            'lavrado_unidade' => LavradoUnidade::OutrasUnidades,
        ]);

        $response = $this->actingAs($context['user'])->post("/produtividade/flagrantes/import-items/{$item->id}/reject", [
            'rejected_reason' => 'Nao corresponde ao cartorio.',
            'filter_year' => 2026,
            'filter_month' => 3,
        ]);

        $response->assertRedirect();

        $item->refresh();

        $this->assertSame(ImportItemStatus::Rejected, $item->import_status);
        $this->assertSame('Nao corresponde ao cartorio.', $item->rejected_reason);
        $this->assertDatabaseMissing('produtividade_flagrantes', [
            'source_item_id' => $item->id,
        ]);
    }

    public function test_manual_flagrante_deduplicates_by_ip_and_complements_existing_record(): void
    {
        $context = $this->bootstrapContext();

        ProductivityFlagrante::query()->create([
            'cartorio_id' => $context['cartorio']->id,
            'spj' => 'SPJ-BASE',
            'naturezas' => 'Roubo',
            'num_ip' => 'IP-BASE',
            'num_ipe' => null,
            'num_cnj' => null,
            'data_fato' => '2026-03-15',
            'lavrado_unidade' => LavradoUnidade::OutrasUnidades,
            'manually_confirmed' => true,
            'confirmed_by' => $context['user']->id,
            'confirmed_at' => now(),
            'notes' => 'Registro inicial.',
        ]);

        $response = $this->actingAs($context['user'])->post('/produtividade/flagrantes/manual', [
            'cartorio_id' => $context['cartorio']->id,
            'spj' => '',
            'naturezas' => 'Furto; Roubo',
            'num_ip' => 'IP-BASE',
            'num_ipe' => 'IPE-123',
            'num_cnj' => 'CNJ-123',
            'data_fato' => '2026-03-15',
            'lavrado_unidade' => LavradoUnidade::Ddm->value,
            'notes' => 'Complemento vindo de consolidacao.',
            'filter_year' => 2026,
            'filter_month' => 3,
        ]);

        $response->assertRedirect();

        $this->assertSame(1, ProductivityFlagrante::query()->count());

        $flagrante = ProductivityFlagrante::query()->firstOrFail();

        $this->assertSame('IP-BASE', $flagrante->num_ip);
        $this->assertSame('IPE-123', $flagrante->num_ipe);
        $this->assertSame('CNJ-123', $flagrante->num_cnj);
        $this->assertSame('Roubo; Furto', $flagrante->naturezas);

        $stats = ProductivityStatMonthly::query()
            ->where('cartorio_id', $context['cartorio']->id)
            ->where('reference_year', 2026)
            ->where('reference_month', 3)
            ->firstOrFail();

        $this->assertSame(1, $stats->flagrantes_total);
        $this->assertSame(0, $stats->flagrantes_ddm);
        $this->assertSame(1, $stats->flagrantes_outras);
    }

    public function test_user_with_lavrado_scope_cannot_confirm_outside_allowed_unidade(): void
    {
        $context = $this->bootstrapContext();

        $role = Role::query()->where('code', 'gestor_cartorio')->firstOrFail();
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $user->roles()->attach($role->id, ['assigned_by' => null]);

        UserScope::query()->create([
            'user_id' => $user->id,
            'scope_type' => 'lavrado_unidade',
            'scope_key' => LavradoUnidade::Ddm->value,
            'created_by' => null,
        ]);

        $item = $this->makePendingImportItem($context['cartorio'], [
            'source_process_key' => 'SPJ-900',
            'spj' => 'SPJ-900',
            'num_ip' => 'IP-900',
            'data_fato' => '2026-03-18',
            'lavrado_unidade' => LavradoUnidade::OutrasUnidades,
        ]);

        $response = $this->actingAs($user)->post("/produtividade/flagrantes/import-items/{$item->id}/confirm", [
            'filter_year' => 2026,
            'filter_month' => 3,
        ]);

        $response->assertForbidden();
        $this->assertSame(ImportItemStatus::Pending, $item->refresh()->import_status);
        $this->assertDatabaseMissing('produtividade_flagrantes', [
            'source_item_id' => $item->id,
        ]);
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

    private function makePendingImportItem(Cartorio $cartorio, array $payload): ImportItem
    {
        $batch = ImportBatch::query()->create([
            'source_name' => 'Consolidacao Excel',
            'imported_by' => User::query()->value('id'),
            'imported_at' => now(),
            'total_rows' => 1,
        ]);

        return ImportItem::query()->create([
            'batch_id' => $batch->id,
            'source_process_key' => $payload['source_process_key'],
            'cartorio_id' => $cartorio->id,
            'spj' => $payload['spj'] ?? null,
            'naturezas' => $payload['naturezas'] ?? 'Roubo',
            'num_ip' => $payload['num_ip'] ?? null,
            'num_ipe' => $payload['num_ipe'] ?? null,
            'num_cnj' => $payload['num_cnj'] ?? null,
            'data_fato' => $payload['data_fato'] ?? '2026-03-01',
            'status_origem' => 'Flagrante',
            'lavrado_unidade' => $payload['lavrado_unidade'] ?? LavradoUnidade::OutrasUnidades,
            'payload' => ['source' => 'test'],
            'import_status' => ImportItemStatus::Pending,
        ]);
    }
}
