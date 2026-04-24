<?php

namespace Database\Seeders;

use App\Enums\ImportItemStatus;
use App\Enums\LavradoUnidade;
use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\ProductivityFlagrante;
use App\Models\ProductivityStatMonthly;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class GromPilotDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('n');
        $previousMonth = $now->copy()->subMonthNoOverflow();
        $sharedPassword = (string) (config('grom_access.bootstrap_admin.password') ?: 'GromPilot#2026');

        /** @var User $admin */
        $admin = User::query()->where('username', config('grom_access.bootstrap_admin.username'))->firstOrFail();

        $cartorioCentral = Cartorio::query()->updateOrCreate(
            ['number' => 10],
            [
                'code' => 'CRT-010',
                'name' => 'Cartorio Central',
                'designacao' => 'Plantao e distribuicao inicial',
                'manager_name' => 'Delegado Responsavel',
                'notes' => 'Carga demonstrativa local do piloto web.',
                'is_active' => true,
            ],
        );

        $cartorioInvestigacao = Cartorio::query()->updateOrCreate(
            ['number' => 20],
            [
                'code' => 'CRT-020',
                'name' => 'Cartorio Investigacao',
                'designacao' => 'Acompanhamento e conclusao',
                'manager_name' => 'Escrivao Chefe',
                'notes' => 'Carga demonstrativa local do piloto web.',
                'is_active' => true,
            ],
        );

        $gestor = User::query()->updateOrCreate(
            ['username' => 'gestor.demo'],
            [
                'name' => 'Gestor Demo',
                'email' => 'gestor.demo@grom.local',
                'password' => $sharedPassword,
                'is_active' => true,
                'must_change_password' => false,
            ],
        );

        $operador = User::query()->updateOrCreate(
            ['username' => 'operador.demo'],
            [
                'name' => 'Operador Demo',
                'email' => 'operador.demo@grom.local',
                'password' => $sharedPassword,
                'is_active' => true,
                'must_change_password' => false,
            ],
        );

        $gestorRoleId = Role::query()->where('code', 'gestor_cartorio')->value('id');
        $operadorRoleId = Role::query()->where('code', 'operador')->value('id');

        $gestor->roles()->sync([
            $gestorRoleId => ['assigned_by' => $admin->id],
        ]);

        $operador->roles()->sync([
            $operadorRoleId => ['assigned_by' => $admin->id],
        ]);

        ProductivityStatMonthly::query()->updateOrCreate(
            [
                'cartorio_id' => $cartorioCentral->id,
                'reference_year' => $currentYear,
                'reference_month' => $currentMonth,
            ],
            [
                'ip_instaurados' => 34,
                'ip_relatados' => 17,
                'cotas' => 9,
                'despachos' => 28,
                'concluidos' => 11,
                'registros' => 52,
                'ips_andamento' => 43,
                'flagrantes_total' => 2,
                'flagrantes_ddm' => 1,
                'flagrantes_outras' => 1,
                'source_mode' => 'AUTO',
                'manual_notes' => 'Fechamento demonstrativo do piloto local.',
            ],
        );

        ProductivityStatMonthly::query()->updateOrCreate(
            [
                'cartorio_id' => $cartorioInvestigacao->id,
                'reference_year' => $currentYear,
                'reference_month' => $currentMonth,
            ],
            [
                'ip_instaurados' => 21,
                'ip_relatados' => 12,
                'cotas' => 5,
                'despachos' => 16,
                'concluidos' => 8,
                'registros' => 31,
                'ips_andamento' => 24,
                'flagrantes_total' => 1,
                'flagrantes_ddm' => 1,
                'flagrantes_outras' => 0,
                'source_mode' => 'AUTO',
                'manual_notes' => 'Fechamento demonstrativo do piloto local.',
            ],
        );

        ProductivityStatMonthly::query()->updateOrCreate(
            [
                'cartorio_id' => $cartorioCentral->id,
                'reference_year' => (int) $previousMonth->format('Y'),
                'reference_month' => (int) $previousMonth->format('n'),
            ],
            [
                'ip_instaurados' => 29,
                'ip_relatados' => 14,
                'cotas' => 7,
                'despachos' => 20,
                'concluidos' => 10,
                'registros' => 44,
                'ips_andamento' => 39,
                'flagrantes_total' => 1,
                'flagrantes_ddm' => 0,
                'flagrantes_outras' => 1,
                'source_mode' => 'AUTO',
                'manual_notes' => 'Historico demonstrativo do piloto local.',
            ],
        );

        $batch = ImportBatch::query()->updateOrCreate(
            ['source_name' => 'PILOTO_LOCAL_DEMO.csv'],
            [
                'source_type' => 'DEMO_LOCAL',
                'source_hash' => null,
                'sheet_name' => null,
                'header_row' => 1,
                'source_period_start' => $previousMonth->copy()->startOfMonth()->toDateString(),
                'source_period_end' => $now->copy()->endOfMonth()->toDateString(),
                'imported_by' => $admin->id,
                'imported_at' => $now,
                'processed_at' => $now,
                'total_rows' => 4,
                'rows_staged' => 3,
                'rows_updated' => 1,
                'rows_skipped' => 0,
                'error_count' => 0,
                'notes' => 'Carga demonstrativa local para navegacao do piloto.',
            ],
        );

        $confirmedImportItem = ImportItem::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'source_process_key' => 'SPJ-DEMO-1001',
            ],
            [
                'cartorio_id' => $cartorioCentral->id,
                'cartorio_hint' => '010 - Cartorio Central',
                'reference_year' => $currentYear,
                'reference_month' => $currentMonth,
                'spj' => 'SPJ-DEMO-1001',
                'naturezas' => 'Violencia domestica; Ameaca',
                'num_ip' => 'IP-2026-1001',
                'num_ipe' => null,
                'num_cnj' => 'CNJ-DEMO-1001',
                'data_fato' => $now->copy()->subDays(4)->toDateString(),
                'status_origem' => 'Flagrante',
                'lavrado_unidade' => LavradoUnidade::Ddm,
                'payload' => ['source' => 'pilot_demo', 'kind' => 'confirmed_import'],
                'import_status' => ImportItemStatus::Confirmed,
                'confirmed_by' => $admin->id,
                'confirmed_at' => $now->copy()->subDays(3),
                'rejected_reason' => null,
            ],
        );

        $confirmedFlagrante = ProductivityFlagrante::query()->updateOrCreate(
            [
                'cartorio_id' => $cartorioCentral->id,
                'spj' => 'SPJ-DEMO-1001',
            ],
            [
                'source_item_id' => $confirmedImportItem->id,
                'reference_year' => $currentYear,
                'reference_month' => $currentMonth,
                'naturezas' => 'Violencia domestica; Ameaca',
                'num_ip' => 'IP-2026-1001',
                'num_ipe' => null,
                'num_cnj' => 'CNJ-DEMO-1001',
                'data_fato' => $now->copy()->subDays(4)->toDateString(),
                'lavrado_unidade' => LavradoUnidade::Ddm,
                'manually_confirmed' => false,
                'is_active' => true,
                'confirmed_by' => $admin->id,
                'confirmed_at' => $now->copy()->subDays(3),
                'notes' => 'Registro demonstrativo confirmado a partir da fila.',
            ],
        );

        $confirmedImportItem->update([
            'productivity_flagrante_id' => $confirmedFlagrante->id,
        ]);

        ProductivityFlagrante::query()->updateOrCreate(
            [
                'cartorio_id' => $cartorioCentral->id,
                'spj' => 'SPJ-DEMO-1002',
            ],
            [
                'source_item_id' => null,
                'reference_year' => $currentYear,
                'reference_month' => $currentMonth,
                'naturezas' => 'Lesao corporal',
                'num_ip' => 'IP-2026-1002',
                'num_ipe' => 'IPE-2026-1002',
                'num_cnj' => 'CNJ-DEMO-1002',
                'data_fato' => $now->copy()->subDays(2)->toDateString(),
                'lavrado_unidade' => LavradoUnidade::OutrasUnidades,
                'manually_confirmed' => true,
                'is_active' => true,
                'confirmed_by' => $admin->id,
                'confirmed_at' => $now->copy()->subDays(2),
                'notes' => 'Registro manual demonstrativo do piloto local.',
            ],
        );

        ProductivityFlagrante::query()->updateOrCreate(
            [
                'cartorio_id' => $cartorioInvestigacao->id,
                'spj' => 'SPJ-DEMO-2001',
            ],
            [
                'source_item_id' => null,
                'reference_year' => $currentYear,
                'reference_month' => $currentMonth,
                'naturezas' => 'Estupro de vulneravel',
                'num_ip' => 'IP-2026-2001',
                'num_ipe' => null,
                'num_cnj' => 'CNJ-DEMO-2001',
                'data_fato' => $now->copy()->subDay()->toDateString(),
                'lavrado_unidade' => LavradoUnidade::Ddm,
                'manually_confirmed' => true,
                'is_active' => true,
                'confirmed_by' => $admin->id,
                'confirmed_at' => $now->copy()->subDay(),
                'notes' => 'Registro demonstrativo para o segundo cartorio.',
            ],
        );

        ImportItem::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'source_process_key' => 'SPJ-DEMO-PEND-010',
            ],
            [
                'cartorio_id' => $cartorioCentral->id,
                'cartorio_hint' => '010 - Cartorio Central',
                'reference_year' => $currentYear,
                'reference_month' => $currentMonth,
                'spj' => 'SPJ-DEMO-PEND-010',
                'naturezas' => 'Perseguicao',
                'num_ip' => 'IP-2026-P010',
                'num_ipe' => null,
                'num_cnj' => 'CNJ-DEMO-P010',
                'data_fato' => $now->copy()->toDateString(),
                'status_origem' => 'Flagrante',
                'lavrado_unidade' => LavradoUnidade::OutrasUnidades,
                'payload' => ['source' => 'pilot_demo', 'kind' => 'pending_assigned'],
                'import_status' => ImportItemStatus::Pending,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'rejected_reason' => null,
                'productivity_flagrante_id' => null,
            ],
        );

        ImportItem::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'source_process_key' => 'SPJ-DEMO-PEND-020',
            ],
            [
                'cartorio_id' => $cartorioInvestigacao->id,
                'cartorio_hint' => '020 - Cartorio Investigacao',
                'reference_year' => $currentYear,
                'reference_month' => $currentMonth,
                'spj' => 'SPJ-DEMO-PEND-020',
                'naturezas' => 'Captura de procurado',
                'num_ip' => 'IP-2026-P020',
                'num_ipe' => null,
                'num_cnj' => 'CNJ-DEMO-P020',
                'data_fato' => $now->copy()->subDays(5)->toDateString(),
                'status_origem' => 'Flagrante',
                'lavrado_unidade' => LavradoUnidade::Ddm,
                'payload' => ['source' => 'pilot_demo', 'kind' => 'pending_assigned'],
                'import_status' => ImportItemStatus::Pending,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'rejected_reason' => null,
                'productivity_flagrante_id' => null,
            ],
        );

        ImportItem::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'source_process_key' => 'SPJ-DEMO-SEM-CARTORIO',
            ],
            [
                'cartorio_id' => null,
                'cartorio_hint' => 'Cartorio nao identificado pela consolidacao',
                'reference_year' => $currentYear,
                'reference_month' => $currentMonth,
                'spj' => 'SPJ-DEMO-SEM-CARTORIO',
                'naturezas' => 'Descumprimento de medida protetiva',
                'num_ip' => 'IP-2026-SC001',
                'num_ipe' => null,
                'num_cnj' => 'CNJ-DEMO-SC001',
                'data_fato' => $now->copy()->subDays(6)->toDateString(),
                'status_origem' => 'Flagrante',
                'lavrado_unidade' => LavradoUnidade::OutrasUnidades,
                'payload' => ['source' => 'pilot_demo', 'kind' => 'pending_unassigned'],
                'import_status' => ImportItemStatus::Pending,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'rejected_reason' => null,
                'productivity_flagrante_id' => null,
            ],
        );
    }
}
