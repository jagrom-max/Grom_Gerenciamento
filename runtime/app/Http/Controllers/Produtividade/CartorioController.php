<?php

namespace App\Http\Controllers\Produtividade;

use App\Http\Controllers\Controller;
use App\Models\Cartorio;
use App\Models\CartorioManagerHistory;
use App\Models\CartorioStatusHistory;
use App\Models\RhFuncionario;
use App\Models\ProductivityStatMonthly;
use App\Services\Produtividade\LegacyProdutividadeSyncService;
use App\Support\Produtividade\ProdutividadeStatsDashboardData;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CartorioController extends Controller
{
    public function __construct(
        private readonly ProdutividadeStatsDashboardData $dashboardData,
    )
    {
    }

    public function index(): View
    {
        $user = auth()->user();
        $referencePeriod = $this->dashboardData->latestAvailablePeriod();
        $referenceYear = $referencePeriod['year'];
        $referenceMonth = $referencePeriod['month'];

        $cartorios = Cartorio::query()
            ->visibleTo($user)
            ->with([
                'monthlyStats' => fn ($query) => $query
                    ->where('reference_year', $referenceYear)
                    ->where('reference_month', $referenceMonth),
                'managerHistory' => fn ($query) => $query->orderByDesc('started_at'),
            ])
            ->orderByDesc('is_active')
            ->orderBy('number')
            ->get();

        $monthlyRollup = ProductivityStatMonthly::query()
            ->selectRaw('COALESCE(SUM(ip_instaurados), 0) AS ip_instaurados')
            ->selectRaw('COALESCE(SUM(flagrantes_total), 0) AS flagrantes_total')
            ->selectRaw('COALESCE(SUM(flagrantes_ddm), 0) AS flagrantes_ddm')
            ->selectRaw('COALESCE(SUM(flagrantes_outras), 0) AS flagrantes_outras')
            ->where('reference_year', $referenceYear)
            ->where('reference_month', $referenceMonth)
            ->first();

        // Policiais de carreira ativos para o combobox de designação
        $codigosPolicial = ['RH-001', 'LEG-005', 'RH-002', 'LEG-001', 'LEG-002', 'LEG-003', 'LEG-004'];
        $policiais = RhFuncionario::query()
            ->where('is_active', true)
            ->with('cargo')
            ->whereHas('cargo', fn ($q) => $q->whereIn('code', $codigosPolicial))
            ->orderBy('short_name')
            ->get()
            ->groupBy(fn ($f) => in_array($f->cargo?->code, ['RH-001', 'LEG-005']) ? 'escrivao' : 'outros');

        return view('produtividade.cartorios.index', [
            'cartorios' => $cartorios,
            'policiais'  => $policiais,
            'referenceLabel' => now()->translatedFormat('F \\d\\e Y'),
            'summary' => [
                'total' => Cartorio::query()->visibleTo($user)->count(),
                'active' => Cartorio::query()->visibleTo($user)->where('is_active', true)->count(),
                'inactive' => Cartorio::query()->visibleTo($user)->where('is_active', false)->count(),
                'ip_instaurados' => (int) ($monthlyRollup->ip_instaurados ?? 0),
                'flagrantes_total' => (int) ($monthlyRollup->flagrantes_total ?? 0),
                'flagrantes_ddm' => (int) ($monthlyRollup->flagrantes_ddm ?? 0),
                'flagrantes_outras' => (int) ($monthlyRollup->flagrantes_outras ?? 0),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:9999', 'unique:cartorios,number'],
            'name' => ['required', 'string', 'max:255'],
            'designacao' => ['nullable', 'string', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $cartorio = Cartorio::query()->create([
            'number' => (int) $data['number'],
            'code' => $this->buildCode((int) $data['number']),
            'name' => trim($data['name']),
            'designacao' => $this->cleanNullable($data['designacao'] ?? null),
            'manager_name' => $this->cleanNullable($data['manager_name'] ?? null),
            'notes' => $this->cleanNullable($data['notes'] ?? null),
            'is_active' => (bool) $data['is_active'],
        ]);

        $this->logStatusHistory($cartorio, $cartorio->is_active, 'Cadastro inicial do cartorio.');
        $this->logManagerHistory($cartorio, $cartorio->manager_name, 'Responsavel inicial definido no cadastro.');

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'cartorios.create',
            entityType: 'cartorio',
            entityId: $cartorio->id,
            description: 'Cartorio criado no piloto web de produtividade.',
            metadata: [
                'number' => $cartorio->number,
                'code' => $cartorio->code,
            ]
        );

        return redirect()->route('produtividade.cartorios.index')->with('status', 'Cartorio criado com sucesso.');
    }

    public function update(Request $request, Cartorio $cartorio): RedirectResponse
    {
        $this->ensureCanAccessCartorio($cartorio);

        $data = $request->validate([
            'number'    => ['required', 'integer', 'min:1', 'max:9999', Rule::unique('cartorios', 'number')->ignore($cartorio->id)],
            'name'      => ['required', 'string', 'max:255'],
            'designacao'=> ['nullable', 'string', 'max:255'],
            'notes'     => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $previousActive = $cartorio->is_active;

        // manager_name é gerenciado exclusivamente via storeDesignacao — nunca alterado aqui
        $cartorio->update([
            'number'    => (int) $data['number'],
            'code'      => $this->buildCode((int) $data['number']),
            'name'      => trim($data['name']),
            'designacao'=> $this->cleanNullable($data['designacao'] ?? null),
            'notes'     => $this->cleanNullable($data['notes'] ?? null),
            'is_active' => (bool) $data['is_active'],
        ]);

        if ($previousActive !== $cartorio->is_active) {
            $this->logStatusHistory($cartorio, $cartorio->is_active, 'Status alterado pela gestao web do cartorio.');
        }

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'cartorios.update',
            entityType: 'cartorio',
            entityId: $cartorio->id,
            description: 'Cartorio atualizado no piloto web de produtividade.',
            metadata: [
                'number' => $cartorio->number,
                'code' => $cartorio->code,
            ]
        );

        return redirect()->route('produtividade.cartorios.index')->with('status', 'Cartorio atualizado com sucesso.');
    }

    public function toggleActive(Cartorio $cartorio): RedirectResponse
    {
        $this->ensureCanAccessCartorio($cartorio);

        $cartorio->update([
            'is_active' => ! $cartorio->is_active,
        ]);

        $this->logStatusHistory($cartorio, $cartorio->is_active, 'Status alterado pela acao rapida da gestao web.');

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'cartorios.toggle_active',
            entityType: 'cartorio',
            entityId: $cartorio->id,
            description: $cartorio->is_active ? 'Cartorio reativado.' : 'Cartorio inativado.',
            metadata: [
                'number' => $cartorio->number,
                'code' => $cartorio->code,
            ]
        );

        return redirect()->route('produtividade.cartorios.index')->with('status', 'Status do cartorio atualizado.');
    }

    public function syncLegacy(LegacyProdutividadeSyncService $syncService): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('produtividade.cartorios.manage'), 403);

        $result = $syncService->sync(auth()->user());

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'cartorios.sync_legacy',
            entityType: 'cartorio',
            entityId: null,
            description: 'Sincronizacao da base legada de cartorios e estatisticas executada no piloto web.',
            metadata: $result,
        );

        $message = $result['synced']
            ? sprintf(
                'Legado sincronizado: %d cartorios, %d estatisticas e %d flagrantes avaliados.',
                (int) ($result['cartorios']['created'] + $result['cartorios']['updated']),
                (int) ($result['stats']['created'] + $result['stats']['updated']),
                (int) ($result['flagrantes']['created'] + $result['flagrantes']['updated']),
            )
            : 'Nao foi possivel sincronizar a base legada de produtividade.';

        return redirect()->route('produtividade.cartorios.index')->with('status', $message);
    }

    public function storeDesignacao(Request $request, Cartorio $cartorio): RedirectResponse
    {
        $this->ensureCanAccessCartorio($cartorio);
        abort_unless(auth()->user()?->hasPermission('produtividade.cartorios.manage'), 403);

        $data = $request->validate([
            'manager_name' => ['required', 'string', 'max:255'],
            'started_at'   => ['required', 'date', 'before_or_equal:today'],
            'ended_at'     => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:started_at'],
            'reason'       => ['nullable', 'string', 'max:1000'],
        ]);

        // Determina se é registro histórico (com período já encerrado) ou nova designação vigente
        $isHistorico = filled($data['ended_at'] ?? null);

        if (! $isHistorico) {
            // Nova designação vigente: encerra o período anteriormente aberto
            CartorioManagerHistory::query()
                ->where('cartorio_id', $cartorio->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => \Illuminate\Support\Carbon::parse($data['started_at'])->subDay()]);
        }

        // Registra o período — imutável após criado
        CartorioManagerHistory::query()->create([
            'cartorio_id'  => $cartorio->id,
            'manager_name' => trim($data['manager_name']),
            'started_at'   => $data['started_at'],
            'ended_at'     => $isHistorico ? $data['ended_at'] : null,
            'reason'       => $this->cleanNullable($data['reason'] ?? null),
            'changed_by'   => auth()->id(),
            'changed_at'   => now(),
        ]);

        // Atualiza o responsável denormalizado apenas quando é designação vigente
        // is_active JAMAIS é alterado aqui — troca de responsável não inativa o cartório.
        if (! $isHistorico) {
            $cartorio->update(['manager_name' => trim($data['manager_name'])]);

            if (! $cartorio->is_active) {
                $cartorio->update(['is_active' => true]);
                $this->logStatusHistory($cartorio, true, 'Reativado automaticamente ao registrar nova designacao de responsavel.');
            }
        }

        $tipoRegistro = $isHistorico ? 'historico' : 'vigente';

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'cartorios.designacao',
            entityType: 'cartorio',
            entityId: $cartorio->id,
            description: 'Designacao de responsavel registrada no cartorio (' . $tipoRegistro . ').',
            metadata: [
                'cartorio_number' => $cartorio->number,
                'manager_name'    => trim($data['manager_name']),
                'started_at'      => $data['started_at'],
                'ended_at'        => $data['ended_at'] ?? null,
                'tipo'            => $tipoRegistro,
            ]
        );

        $msg = $isHistorico
            ? 'Registro histórico de designação salvo com sucesso.'
            : 'Designação vigente registrada com sucesso.';

        return redirect()
            ->route('produtividade.cartorios.index')
            ->with('status', $msg);
    }

    public function updateDesignacao(Request $request, Cartorio $cartorio, CartorioManagerHistory $history): RedirectResponse
    {
        $this->ensureCanAccessCartorio($cartorio);
        abort_unless(auth()->user()?->hasPermission('produtividade.cartorios.manage'), 403);

        // Garante que o registro pertence a este cartório
        abort_if($history->cartorio_id !== $cartorio->id, 404);

        $data = $request->validate([
            'manager_name' => ['required', 'string', 'max:255'],
            'started_at'   => ['required', 'date'],
            'ended_at'     => ['nullable', 'date', 'after_or_equal:started_at'],
            'reason'       => ['nullable', 'string', 'max:1000'],
        ]);

        // Captura valores anteriores para auditoria
        $before = [
            'manager_name' => $history->manager_name,
            'started_at'   => $history->started_at?->toDateString(),
            'ended_at'     => $history->ended_at?->toDateString(),
            'reason'       => $history->reason,
        ];

        $history->update([
            'manager_name' => trim($data['manager_name']),
            'started_at'   => $data['started_at'],
            'ended_at'     => $this->cleanNullable($data['ended_at'] ?? null),
            'reason'       => $this->cleanNullable($data['reason'] ?? null),
        ]);

        // Se era o registro vigente (ended_at continua null) e nome mudou → sincroniza cartório
        $aindaVigente = $history->ended_at === null;
        if ($aindaVigente && $cartorio->manager_name !== trim($data['manager_name'])) {
            $cartorio->update(['manager_name' => trim($data['manager_name'])]);
        }

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'cartorios.designacao.correcao',
            entityType: 'cartorio',
            entityId: $cartorio->id,
            description: 'Correcao de registro de designacao efetuada.',
            metadata: [
                'cartorio_number' => $cartorio->number,
                'history_id'      => $history->id,
                'before'          => $before,
                'after'           => [
                    'manager_name' => trim($data['manager_name']),
                    'started_at'   => $data['started_at'],
                    'ended_at'     => $data['ended_at'] ?? null,
                    'reason'       => $data['reason'] ?? null,
                ],
                'corrigido_por' => auth()->id(),
            ]
        );

        return redirect()
            ->route('produtividade.cartorios.index')
            ->with('status', 'Registro de designação corrigido com sucesso.');
    }

    private function buildCode(int $number): string
    {
        return sprintf('CRT-%03d', $number);
    }

    private function cleanNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function logStatusHistory(Cartorio $cartorio, bool $isActive, ?string $reason = null): void
    {
        CartorioStatusHistory::query()->create([
            'cartorio_id' => $cartorio->id,
            'status' => $isActive ? 'ATIVO' : 'INATIVO',
            'reason' => $reason,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }

    private function logManagerHistory(Cartorio $cartorio, ?string $managerName, ?string $reason = null): void
    {
        $managerName = $this->cleanNullable($managerName);

        if ($managerName === null) {
            return;
        }

        CartorioManagerHistory::query()->create([
            'cartorio_id'  => $cartorio->id,
            'manager_name' => $managerName,
            'started_at'   => now()->toDateString(),
            'ended_at'     => null,
            'reason'       => $reason,
            'changed_by'   => auth()->id(),
            'changed_at'   => now(),
        ]);
    }

    private function ensureCanAccessCartorio(Cartorio $cartorio): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        if ($user->isSuperAdmin()) {
            return;
        }

        $scopeKeys = $user->scopeKeys('cartorio');

        if ($scopeKeys->isEmpty()) {
            return;
        }

        abort_unless($user->hasScope('cartorio', (string) $cartorio->id), 403);
    }
}
