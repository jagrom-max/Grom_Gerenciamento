<?php

namespace App\Http\Controllers;

use App\Enums\ImportItemStatus;
use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\RhAfastamento;
use App\Models\RhCargo;
use App\Models\RhDelegadoExterno;
use App\Models\RhFuncionario;
use App\Models\RhHoliday;
use App\Models\ProductivityFlagrante;
use App\Models\ProductivityBoletim;
use App\Models\ProductivityStatMonthly;
use App\Models\Role;
use App\Models\User;
use App\Services\Escalas\LegacyEscalasReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(LegacyEscalasReader $legacyEscalasReader): View
    {
        $user = auth()->user();
        $canViewFlagrantes = $user?->hasPermission('produtividade.flagrantes.view') ?? false;
        $canViewRh = $user?->hasPermission('rh.view') ?? false;
        $canViewEscalas = $user?->hasPermission('escalas.view') ?? false;
        $visibleCartorioIds = Cartorio::query()
            ->visibleTo($user)
            ->pluck('id');
        $hasCartorioScope = $visibleCartorioIds instanceof Collection && $visibleCartorioIds->isNotEmpty();
        $currentYear = (int) now()->format('Y');
        $currentMonth = (int) now()->format('n');
        $produtividadeStats = null;
        $produtividadeResumo = null;
        $lastImportBatch = null;
        $cartorioPreview = Cartorio::query()
            ->visibleTo($user)
            ->withCount([
                'importItems as import_items_total_count',
                'importItems as pending_import_items_count' => fn (Builder $query) => $query->where('import_status', ImportItemStatus::Pending->value),
                'flagrantes as flagrantes_ativos_count' => fn (Builder $query) => $query->where('is_active', true),
            ])
            ->with([
                'monthlyStats' => fn ($query) => $query
                    ->where('reference_year', $currentYear)
                    ->where('reference_month', $currentMonth),
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(5)
            ->get();
        $funcionariosPreview = collect();
        $afastamentosPreview = collect();
        $delegadosPreview = collect();
        $holidaysPreview = collect();
        $rhHoje = null;
        $afastadosHojePreview = collect();
        $agendadosPreview = collect();
        $escalaSnapshot = null;
        $latestBatchesPreview = collect();
        $pendingItemsPreview = collect();

        if ($canViewFlagrantes) {
            $productivityCurrentMonth = ProductivityStatMonthly::query()
                ->when($visibleCartorioIds->isNotEmpty(), fn ($query) => $query->whereIn('cartorio_id', $visibleCartorioIds))
                ->where('reference_year', $currentYear)
                ->where('reference_month', $currentMonth);

            $produtividadeStats = [
                'fila_pendente' => ImportItem::query()
                    ->where('import_status', ImportItemStatus::Pending->value)
                    ->count(),
                'fila_sem_cartorio' => ImportItem::query()
                    ->where('import_status', ImportItemStatus::Pending->value)
                    ->whereNull('cartorio_id')
                    ->count(),
                'boletins_mes' => ProductivityBoletim::query()
                    ->when($visibleCartorioIds->isNotEmpty(), fn ($query) => $query->whereIn('cartorio_id', $visibleCartorioIds))
                    ->where('is_active', true)
                    ->where('reference_year', $currentYear)
                    ->where('reference_month', $currentMonth)
                    ->count(),
                'boletins_nao_flagrantes_mes' => ProductivityBoletim::query()
                    ->when($visibleCartorioIds->isNotEmpty(), fn ($query) => $query->whereIn('cartorio_id', $visibleCartorioIds))
                    ->where('is_active', true)
                    ->where('is_flagrante', false)
                    ->where('reference_year', $currentYear)
                    ->where('reference_month', $currentMonth)
                    ->count(),
                'boletins_mpu_sem_ip_mes' => ProductivityBoletim::query()
                    ->when($visibleCartorioIds->isNotEmpty(), fn ($query) => $query->whereIn('cartorio_id', $visibleCartorioIds))
                    ->where('is_active', true)
                    ->whereNotNull('mpu_numero')
                    ->where('mpu_numero', '!=', '')
                    ->where(function ($query): void {
                        $query->whereNull('num_ip')->orWhere('num_ip', '');
                    })
                    ->where('reference_year', $currentYear)
                    ->where('reference_month', $currentMonth)
                    ->count(),
                'flagrantes_mes' => ProductivityFlagrante::query()
                    ->when($visibleCartorioIds->isNotEmpty(), fn ($query) => $query->whereIn('cartorio_id', $visibleCartorioIds))
                    ->where('is_active', true)
                    ->where('reference_year', (int) now()->format('Y'))
                    ->where('reference_month', (int) now()->format('n'))
                    ->count(),
            ];

            $produtividadeResumo = [
                'ip_instaurados' => (clone $productivityCurrentMonth)->sum('ip_instaurados'),
                'ip_relatados' => (clone $productivityCurrentMonth)->sum('ip_relatados'),
                'cotas' => (clone $productivityCurrentMonth)->sum('cotas'),
                'despachos' => (clone $productivityCurrentMonth)->sum('despachos'),
                'concluidos' => (clone $productivityCurrentMonth)->sum('concluidos'),
                'registros' => (clone $productivityCurrentMonth)->sum('registros'),
            ];

            $lastImportBatch = ImportBatch::query()
                ->orderByDesc('imported_at')
                ->orderByDesc('created_at')
                ->first();

            $latestBatchesPreview = ImportBatch::query()
                ->withCount([
                    'items as pending_items_count' => fn (Builder $query) => $query->where('import_status', ImportItemStatus::Pending->value),
                    'items as confirmed_items_count' => fn (Builder $query) => $query->where('import_status', ImportItemStatus::Confirmed->value),
                ])
                ->orderByDesc('imported_at')
                ->orderByDesc('created_at')
                ->limit(3)
                ->get();

            $pendingItemsPreview = ImportItem::query()
                ->with(['batch', 'cartorio'])
                ->where('import_status', ImportItemStatus::Pending->value)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
        }

        if ($canViewRh) {
            $hoje = Carbon::today();

            $funcionariosPreview = RhFuncionario::query()
                ->with(['cargo', 'afastamentos' => fn ($query) => $query->orderByDesc('start_date')])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->limit(5)
                ->get();

            $afastamentosPreview = RhAfastamento::query()
                ->with(['funcionario.cargo'])
                ->orderByDesc('is_active')
                ->orderByDesc('start_date')
                ->limit(5)
                ->get();

            $delegadosPreview = RhDelegadoExterno::query()
                ->orderByDesc('is_active')
                ->orderByDesc('start_date')
                ->orderBy('name')
                ->limit(5)
                ->get();

            $holidaysPreview = RhHoliday::query()
                ->orderBy('holiday_date')
                ->limit(5)
                ->get();

            // KPIs de efetivo hoje
            $rhHoje = [
                'total_ativos'     => RhFuncionario::where('is_active', true)->count(),
                'concorrem_escala' => RhFuncionario::where('is_active', true)->where('concorre_escala', true)->count(),
                'afastados_hoje'   => RhAfastamento::where('is_active', true)
                    ->whereDate('start_date', '<=', $hoje)
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $hoje))
                    ->count(),
                'agendados_7dias'  => RhAfastamento::where('is_active', true)
                    ->whereDate('start_date', '>', $hoje)
                    ->whereDate('start_date', '<=', $hoje->copy()->addDays(7))
                    ->count(),
                'feriado_proximo'  => RhHoliday::where('is_active', true)
                    ->whereDate('holiday_date', '>=', $hoje)
                    ->orderBy('holiday_date')
                    ->first(),
            ];
            $rhHoje['disponiveis'] = $rhHoje['total_ativos'] - $rhHoje['afastados_hoje'];

            // Afastados hoje (para o painel)
            $afastadosHojePreview = RhAfastamento::with('funcionario.cargo')
                ->where('is_active', true)
                ->whereDate('start_date', '<=', $hoje)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $hoje))
                ->orderBy(fn ($q) => $q->select('name')->from('rh_funcionarios')->whereColumn('id', 'rh_afastamentos.funcionario_id'))
                ->get();

            // Agendados próximos 14 dias
            $agendadosPreview = RhAfastamento::with('funcionario.cargo')
                ->where('is_active', true)
                ->whereDate('start_date', '>', $hoje)
                ->whereDate('start_date', '<=', $hoje->copy()->addDays(14))
                ->orderBy('start_date')
                ->get();
        }

        if ($canViewEscalas) {
            try {
                $escalaSnapshot = $legacyEscalasReader->snapshotForMonth($user, $currentYear, $currentMonth);
            } catch (\Throwable) {
                $escalaSnapshot = null;
            }
        }

        $rhHoje = $rhHoje ?? null;
        $afastadosHojePreview = $afastadosHojePreview ?? collect();
        $agendadosPreview = $agendadosPreview ?? collect();

        return view('dashboard', [
            'stats' => [
                'usuarios' => User::query()->count(),
                'roles' => Role::query()->count(),
                'permissoes' => $user?->permissionCodes()->count() ?? 0,
                'cartorios' => Cartorio::query()->count(),
                'escalas_dias' => $escalaSnapshot['summary']['dias_total'] ?? 0,
                'escalas_plantoes' => $escalaSnapshot['summary']['plantoes_atribuicoes'] ?? 0,
                'rh_cargos' => RhCargo::query()->count(),
                'rh_funcionarios' => RhFuncionario::query()->count(),
                'rh_afastamentos' => RhAfastamento::query()->where('is_active', true)->count(),
                'rh_delegados_externos' => RhDelegadoExterno::query()->where('is_active', true)->count(),
            ],
            'produtividadeStats' => $produtividadeStats,
            'produtividadeResumo' => $produtividadeResumo,
            'lastImportBatch' => $lastImportBatch,
            'canViewRh' => $canViewRh,
            'canViewEscalas' => $canViewEscalas,
            'cartorioPreview' => $cartorioPreview,
            'funcionariosPreview' => $funcionariosPreview,
            'afastamentosPreview' => $afastamentosPreview,
            'delegadosPreview' => $delegadosPreview,
            'holidaysPreview' => $holidaysPreview,
            'rhHoje' => $rhHoje,
            'afastadosHojePreview' => $afastadosHojePreview,
            'agendadosPreview' => $agendadosPreview,
            'escalaSnapshot' => $escalaSnapshot,
            'latestBatchesPreview' => $latestBatchesPreview,
            'pendingItemsPreview' => $pendingItemsPreview,
        ]);
    }
}
