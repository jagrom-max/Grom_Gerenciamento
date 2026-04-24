<?php

namespace App\Http\Controllers\Produtividade;

use App\Enums\ImportItemStatus;
use App\Enums\LavradoUnidade;
use App\Http\Controllers\Controller;
use App\Models\Cartorio;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\ProductivityFlagrante;
use App\Models\ProductivityStatMonthly;
use App\Models\User;
use App\Services\Produtividade\FlagranteImportService;
use App\Services\Produtividade\LegacyAnaliseSyncService;
use App\Services\Produtividade\FlagranteWorkflowService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class FlagranteController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $allowedCartorioIds = $this->allowedCartorioIds($user);
        $allowedLavradoUnidades = $this->allowedLavradoUnidades($user);

        $cartorios = Cartorio::query()
            ->visibleTo($user)
            ->orderByDesc('is_active')
            ->orderBy('number')
            ->get();
        $selectedCartorio = $this->resolveCartorio($request, $cartorios->pluck('id')->all());
        $year = max((int) $request->integer('year', (int) now()->format('Y')), 2020);
        $month = max((int) $request->integer('month', (int) now()->format('n')), 0);
        $unassignedPendingQuery = ImportItem::query()
            ->whereNull('cartorio_id')
            ->where('import_status', ImportItemStatus::Pending->value)
            ->when($allowedLavradoUnidades !== [], function ($query) use ($allowedLavradoUnidades): void {
                $query->whereIn('lavrado_unidade', $allowedLavradoUnidades);
            });

        $pendingItems = collect();
        $confirmedFlagrantes = collect();
        $yearBreakdown = collect();
        $selectedStats = ['total' => 0, 'ddm' => 0, 'outras' => 0];
        $unassignedPendingCount = (clone $unassignedPendingQuery)->count();
        $unassignedPendingItems = (clone $unassignedPendingQuery)
            ->orderByDesc('data_fato')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();
        $recentBatches = ImportBatch::query()
            ->orderByDesc('imported_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        if ($selectedCartorio) {
            $pendingItems = ImportItem::query()
                ->where('cartorio_id', $selectedCartorio->id)
                ->where('import_status', ImportItemStatus::Pending->value)
                ->when($allowedLavradoUnidades !== [], function ($query) use ($allowedLavradoUnidades): void {
                    $query->whereIn('lavrado_unidade', $allowedLavradoUnidades);
                })
                ->orderByDesc('data_fato')
                ->orderByDesc('created_at')
                ->get();

            $confirmedFlagrantes = ProductivityFlagrante::query()
                ->where('cartorio_id', $selectedCartorio->id)
                ->where('is_active', true)
                ->where('reference_year', $year)
                ->when($allowedLavradoUnidades !== [], function ($query) use ($allowedLavradoUnidades): void {
                    $query->whereIn('lavrado_unidade', $allowedLavradoUnidades);
                })
                ->when($month > 0, fn ($query) => $query->where('reference_month', $month))
                ->orderByDesc('data_fato')
                ->orderByDesc('created_at')
                ->get();

            $yearBreakdown = ProductivityStatMonthly::query()
                ->where('cartorio_id', $selectedCartorio->id)
                ->where('reference_year', $year)
                ->orderBy('reference_month')
                ->get()
                ->keyBy('reference_month');

            if ($month > 0) {
                $row = $yearBreakdown->get($month);
                $selectedStats = [
                    'total' => (int) ($row?->flagrantes_total ?? 0),
                    'ddm' => (int) ($row?->flagrantes_ddm ?? 0),
                    'outras' => (int) ($row?->flagrantes_outras ?? 0),
                ];
            } else {
                $selectedStats = [
                    'total' => (int) $yearBreakdown->sum('flagrantes_total'),
                    'ddm' => (int) $yearBreakdown->sum('flagrantes_ddm'),
                    'outras' => (int) $yearBreakdown->sum('flagrantes_outras'),
                ];
            }
        }

        return view('produtividade.flagrantes.index', [
            'cartorios' => $cartorios,
            'selectedCartorio' => $selectedCartorio,
            'year' => $year,
            'month' => $month,
            'pendingItems' => $pendingItems,
            'unassignedPendingItems' => $unassignedPendingItems,
            'unassignedPendingCount' => $unassignedPendingCount,
            'confirmedFlagrantes' => $confirmedFlagrantes,
            'yearBreakdown' => $yearBreakdown,
            'selectedStats' => $selectedStats,
            'recentBatches' => $recentBatches,
        ]);
    }

    public function importSpreadsheet(Request $request, FlagranteImportService $service): RedirectResponse
    {
        $data = $request->validate([
            'source_file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:12288'],
            'cartorio_id' => ['nullable', 'exists:cartorios,id'],
            'filter_year' => ['nullable', 'integer'],
            'filter_month' => ['nullable', 'integer'],
        ]);

        $fallbackCartorio = null;
        if (! empty($data['cartorio_id'])) {
            $fallbackCartorio = Cartorio::query()->findOrFail($data['cartorio_id']);
            $this->ensureCanAccessCartorio($request->user(), $fallbackCartorio);
        }

        try {
            $result = $service->importUploadedFile(
                $request->file('source_file'),
                $request->user(),
                $fallbackCartorio,
                [
                    'allowed_cartorio_ids' => $this->allowedCartorioIds($request->user()),
                    'allowed_lavrado_unidades' => $this->allowedLavradoUnidades($request->user()),
                ],
            );
        } catch (RuntimeException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'source_file' => $exception->getMessage(),
            ]);
        }

        /** @var ImportBatch $batch */
        $batch = $result['batch'];
        $summary = $result['summary'];

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'flagrantes.import_batch',
            entityType: 'import_batch',
            entityId: $batch->id,
            description: 'Lote de consolidacao externa importado para a fila de flagrantes.',
            metadata: $summary,
        );

        return redirect()->route('produtividade.flagrantes.index', [
            'cartorio_id' => $data['cartorio_id'] ?? null,
            'year' => $data['filter_year'] ?? null,
            'month' => $data['filter_month'] ?? null,
        ])->with(
            'status',
            sprintf(
                'Importacao concluida. %d staged, %d atualizados, %d ignorados, %d erros.',
                (int) ($summary['rows_staged'] ?? 0),
                (int) ($summary['rows_updated'] ?? 0),
                (int) ($summary['rows_skipped'] ?? 0),
                (int) ($summary['error_count'] ?? 0),
            ),
        );
    }

    public function syncLegacyAnalise(Request $request, LegacyAnaliseSyncService $service): RedirectResponse
    {
        $data = $request->validate([
            'filter_year' => ['nullable', 'integer'],
            'filter_month' => ['nullable', 'integer'],
        ]);

        try {
            $result = $service->syncFlagrantes($request->user());
        } catch (RuntimeException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'source_file' => $exception->getMessage(),
            ]);
        }

        /** @var ImportBatch $batch */
        $batch = $result['batch'];
        $summary = $result['summary'];

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'flagrantes.sync_legacy',
            entityType: 'import_batch',
            entityId: $batch->id,
            description: 'Sincronizacao direta da base legada Analise de Dados para a fila de flagrantes.',
            metadata: $summary,
        );

        return redirect()->route('produtividade.flagrantes.index', [
            'year' => $data['filter_year'] ?? null,
            'month' => $data['filter_month'] ?? null,
        ])->with(
            'status',
            sprintf(
                'Sincronizacao do legado concluida. %d sugestoes enfileiradas, %d pendencias substituidas, %d linhas ignoradas e %d erros.',
                (int) ($summary['rows_staged'] ?? 0),
                (int) ($summary['rows_updated'] ?? 0),
                (int) ($summary['rows_skipped'] ?? 0),
                (int) ($summary['error_count'] ?? 0),
            ),
        );
    }

    public function assignImportItemCartorio(Request $request, ImportItem $item, FlagranteWorkflowService $service): RedirectResponse
    {
        $data = $request->validate([
            'cartorio_id' => ['required', 'exists:cartorios,id'],
            'filter_cartorio_id' => ['nullable', 'exists:cartorios,id'],
            'filter_year' => ['nullable', 'integer'],
            'filter_month' => ['nullable', 'integer'],
        ]);

        $previousCartorioId = $item->cartorio_id;
        $previousCartorioHint = $item->cartorio_hint;

        try {
            $item = $service->assignImportItemCartorio(
                $item,
                tap(Cartorio::query()->findOrFail($data['cartorio_id']), fn (Cartorio $cartorio) => $this->ensureCanAccessCartorio($request->user(), $cartorio)),
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'import_item' => $exception->getMessage(),
            ]);
        }

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'flagrantes.queue_assign_cartorio',
            entityType: 'import_item',
            entityId: $item->id,
            description: 'Sugestao de flagrante vinculada manualmente a um cartorio.',
            metadata: [
                'previous_cartorio_id' => $previousCartorioId,
                'previous_cartorio_hint' => $previousCartorioHint,
                'assigned_cartorio_id' => $item->cartorio_id,
                'assigned_cartorio_hint' => $item->cartorio_hint,
            ],
        );

        return redirect()->route('produtividade.flagrantes.index', [
            'cartorio_id' => $data['filter_cartorio_id'] ?? $item->cartorio_id,
            'year' => $data['filter_year'] ?? null,
            'month' => $data['filter_month'] ?? null,
        ])->with('status', 'Pendencia vinculada ao cartorio e devolvida para a fila de confirmacao.');
    }

    public function storeManual(Request $request, FlagranteWorkflowService $service): RedirectResponse
    {
        $data = $request->validate([
            'cartorio_id' => ['required', 'exists:cartorios,id'],
            'spj' => ['nullable', 'string', 'max:255'],
            'naturezas' => ['nullable', 'string', 'max:1000'],
            'num_ip' => ['nullable', 'string', 'max:255'],
            'num_ipe' => ['nullable', 'string', 'max:255'],
            'num_cnj' => ['nullable', 'string', 'max:255'],
            'data_fato' => ['required', 'date'],
            'lavrado_unidade' => ['required', Rule::in(array_column(LavradoUnidade::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'filter_year' => ['nullable', 'integer'],
            'filter_month' => ['nullable', 'integer'],
        ]);

        try {
            $cartorio = Cartorio::query()->findOrFail($data['cartorio_id']);
            $this->ensureCanAccessCartorio($request->user(), $cartorio);
            $this->ensureCanUseLavradoUnidade($request->user(), $data['lavrado_unidade']);

            $flagrante = $service->createManualFlagrante(
                $cartorio,
                $data,
                $request->user()
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'spj' => $exception->getMessage(),
            ]);
        }

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'flagrantes.manual_create',
            entityType: 'produtividade_flagrante',
            entityId: $flagrante->id,
            description: 'Flagrante registrado manualmente no piloto web.',
            metadata: [
                'cartorio_id' => $data['cartorio_id'],
                'filter_year' => $data['filter_year'] ?? null,
                'filter_month' => $data['filter_month'] ?? null,
            ],
        );

        return redirect()->route('produtividade.flagrantes.index', [
            'cartorio_id' => $data['cartorio_id'],
            'year' => $data['filter_year'] ?? null,
            'month' => $data['filter_month'] ?? null,
        ])->with('status', 'Flagrante registrado com sucesso.');
    }

    public function confirmImportItem(Request $request, ImportItem $item, FlagranteWorkflowService $service): RedirectResponse
    {
        $cartorio = $item->cartorio;

        if (! $cartorio) {
            throw ValidationException::withMessages([
                'import_item' => 'A sugestao informada ainda nao possui cartorio designado.',
            ]);
        }

        try {
            $this->ensureCanAccessCartorio($request->user(), $cartorio);
            $this->ensureCanUseLavradoUnidade($request->user(), (string) $item->lavrado_unidade?->value);
            $flagrante = $service->confirmImportItem($cartorio, $item, $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'import_item' => $exception->getMessage(),
            ]);
        }

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'flagrantes.queue_confirm',
            entityType: 'import_item',
            entityId: $item->id,
            description: 'Sugestao de flagrante confirmada manualmente.',
            metadata: [
                'cartorio_id' => $item->cartorio_id,
                'flagrante_id' => $flagrante->id,
            ],
        );

        return redirect()->route('produtividade.flagrantes.index', [
            'cartorio_id' => $item->cartorio_id,
            'year' => $request->integer('filter_year'),
            'month' => $request->integer('filter_month'),
        ])->with('status', 'Sugestao confirmada e incorporada ao cartorio.');
    }

    public function rejectImportItem(Request $request, ImportItem $item, FlagranteWorkflowService $service): RedirectResponse
    {
        $data = $request->validate([
            'rejected_reason' => ['required', 'string', 'max:500'],
            'filter_year' => ['nullable', 'integer'],
            'filter_month' => ['nullable', 'integer'],
        ]);

        try {
            $this->ensureCanAccessCartorio($request->user(), $item->cartorio ?? throw new InvalidArgumentException('A sugestao informada ainda nao possui cartorio designado.'));
            $this->ensureCanUseLavradoUnidade($request->user(), (string) $item->lavrado_unidade?->value);
            $service->rejectImportItem($item, $request->user(), $data['rejected_reason']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'import_item' => $exception->getMessage(),
            ]);
        }

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'flagrantes.queue_reject',
            entityType: 'import_item',
            entityId: $item->id,
            description: 'Sugestao de flagrante rejeitada manualmente.',
            metadata: [
                'cartorio_id' => $item->cartorio_id,
                'reason' => $data['rejected_reason'],
            ],
        );

        return redirect()->route('produtividade.flagrantes.index', [
            'cartorio_id' => $item->cartorio_id,
            'year' => $data['filter_year'] ?? null,
            'month' => $data['filter_month'] ?? null,
        ])->with('status', 'Sugestao rejeitada.');
    }

    private function resolveCartorio(Request $request, array $validIds): ?Cartorio
    {
        $requested = (string) $request->query('cartorio_id', '');

        if ($requested !== '' && in_array($requested, $validIds, true)) {
            return Cartorio::query()->find($requested);
        }

        return Cartorio::query()
            ->visibleTo($request->user())
            ->orderByDesc('is_active')
            ->orderBy('number')
            ->first();
    }

    private function allowedCartorioIds(?User $user): array
    {
        if (! $user || $user->isSuperAdmin()) {
            return [];
        }

        return $user->scopeKeys('cartorio')->all();
    }

    private function allowedLavradoUnidades(?User $user): array
    {
        if (! $user || $user->isSuperAdmin()) {
            return [];
        }

        return $user->scopeKeys('lavrado_unidade')->all();
    }

    private function ensureCanAccessCartorio(?User $user, Cartorio $cartorio): void
    {
        abort_unless($user, 403);

        $allowedCartorioIds = $this->allowedCartorioIds($user);

        if ($allowedCartorioIds === []) {
            return;
        }

        abort_unless(in_array((string) $cartorio->id, $allowedCartorioIds, true), 403);
    }

    private function ensureCanUseLavradoUnidade(?User $user, string $unidade): void
    {
        abort_unless($user, 403);

        $allowedUnidades = $this->allowedLavradoUnidades($user);

        if ($allowedUnidades === [] || $unidade === '') {
            return;
        }

        abort_unless(in_array($unidade, $allowedUnidades, true), 403);
    }
}
