<?php

namespace App\Http\Controllers\Produtividade;

use App\Http\Controllers\Controller;
use App\Models\Cartorio;
use App\Models\ImportItem;
use App\Models\ProductivityFlagrante;
use App\Models\User;
use App\Services\Produtividade\FlagranteWorkflowService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CartorioFlagranteController extends Controller
{
    public function __construct(
        private readonly FlagranteWorkflowService $workflow,
    ) {
    }

    public function index(Cartorio $cartorio, Request $request): RedirectResponse
    {
        return redirect()->route('produtividade.flagrantes.index', [
            'cartorio_id' => $cartorio->id,
            'year' => $request->integer('year', (int) now()->format('Y')),
            'month' => $request->integer('month', 0),
        ]);
    }

    public function storeManual(Cartorio $cartorio, Request $request): RedirectResponse
    {
        $this->ensureCanAccessCartorio($request->user(), $cartorio);

        $data = $request->validate([
            'spj' => ['nullable', 'string', 'max:255'],
            'naturezas' => ['nullable', 'string', 'max:2000'],
            'num_ip' => ['nullable', 'string', 'max:255'],
            'num_ipe' => ['nullable', 'string', 'max:255'],
            'num_cnj' => ['nullable', 'string', 'max:255'],
            'data_fato' => ['required', 'date'],
            'lavrado_unidade' => ['required', 'string', 'in:DDM,OUTRAS_UNIDADES'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->ensureIdentifiers($data);

        try {
            $flagrante = $this->workflow->createManualFlagrante($cartorio, $data, $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['spj' => $exception->getMessage()]);
        }

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'flagrantes.manual_create',
            entityType: 'productivity_flagrante',
            entityId: $flagrante->id,
            description: 'Flagrante registrado manualmente no cartorio.',
            metadata: ['cartorio_id' => $cartorio->id]
        );

        return $this->backToIndex($cartorio, $request)->with('status', 'Flagrante registrado com sucesso.');
    }

    public function enqueueSuggestion(Cartorio $cartorio, Request $request): RedirectResponse
    {
        $this->ensureCanAccessCartorio($request->user(), $cartorio);

        $data = $request->validate([
            'source_process_key' => ['required', 'string', 'max:255'],
            'spj' => ['nullable', 'string', 'max:255'],
            'naturezas' => ['nullable', 'string', 'max:2000'],
            'num_ip' => ['nullable', 'string', 'max:255'],
            'num_ipe' => ['nullable', 'string', 'max:255'],
            'num_cnj' => ['nullable', 'string', 'max:255'],
            'data_fato' => ['required', 'date'],
            'lavrado_unidade' => ['required', 'string', 'in:DDM,OUTRAS_UNIDADES'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->ensureIdentifiers($data);

        try {
            $item = $this->workflow->enqueueManualSuggestion($cartorio, $data, $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['source_process_key' => $exception->getMessage()]);
        }

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'flagrantes.queue_enqueue',
            entityType: 'import_item',
            entityId: $item->id,
            description: 'Sugestao de importacao registrada para confirmacao manual.',
            metadata: ['cartorio_id' => $cartorio->id]
        );

        return $this->backToIndex($cartorio, $request)->with('status', 'Sugestao registrada na fila do cartorio.');
    }

    public function confirmImport(Cartorio $cartorio, ImportItem $item, Request $request): RedirectResponse
    {
        $this->ensureCanAccessCartorio($request->user(), $cartorio);
        $this->ensurePendingItemBelongsToCartorio($cartorio, $item);

        $flagrante = $this->workflow->confirmImportItem($cartorio, $item, $request->user());

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'flagrantes.queue_confirm',
            entityType: 'import_item',
            entityId: $item->id,
            description: 'Sugestao de importacao confirmada no cartorio.',
            metadata: [
                'cartorio_id' => $cartorio->id,
                'flagrante_id' => $flagrante->id,
            ]
        );

        return $this->backToIndex($cartorio, $request)->with('status', 'Sugestao confirmada e incorporada ao cartorio.');
    }

    public function rejectImport(Cartorio $cartorio, ImportItem $item, Request $request): RedirectResponse
    {
        $this->ensureCanAccessCartorio($request->user(), $cartorio);
        $this->ensurePendingItemBelongsToCartorio($cartorio, $item);

        $this->workflow->rejectImportItem($item, $request->user());

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'flagrantes.queue_reject',
            entityType: 'import_item',
            entityId: $item->id,
            description: 'Sugestao de importacao rejeitada no cartorio.',
            metadata: ['cartorio_id' => $cartorio->id]
        );

        return $this->backToIndex($cartorio, $request)->with('status', 'Sugestao rejeitada.');
    }

    public function deactivate(Cartorio $cartorio, ProductivityFlagrante $flagrante, Request $request): RedirectResponse
    {
        $this->ensureCanAccessCartorio($request->user(), $cartorio);
        abort_unless($flagrante->cartorio_id === $cartorio->id, 404);

        $this->workflow->deactivateFlagrante($flagrante, $request->user());

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'flagrantes.deactivate',
            entityType: 'productivity_flagrante',
            entityId: $flagrante->id,
            description: 'Flagrante inativado manualmente.',
            metadata: ['cartorio_id' => $cartorio->id]
        );

        return $this->backToIndex($cartorio, $request)->with('status', 'Flagrante inativado.');
    }

    private function ensureCanAccessCartorio(?User $user, Cartorio $cartorio): void
    {
        abort_unless($user !== null, 403);

        $scopedIds = $user->scopeKeys('cartorio_id')->all();
        if ($scopedIds === []) {
            return; // sem restricao de escopo: acesso irrestrito
        }

        abort_unless(in_array((string) $cartorio->id, $scopedIds, true), 403);
    }

    private function ensureIdentifiers(array $data): void
    {
        $identifiers = [
            trim((string) ($data['spj'] ?? '')),
            trim((string) ($data['num_ip'] ?? '')),
            trim((string) ($data['num_ipe'] ?? '')),
            trim((string) ($data['num_cnj'] ?? '')),
        ];

        if (! array_filter($identifiers)) {
            throw ValidationException::withMessages([
                'spj' => 'Informe ao menos um identificador: SPJ, IP, IP-e ou CNJ.',
            ]);
        }
    }

    private function ensurePendingItemBelongsToCartorio(Cartorio $cartorio, ImportItem $item): void
    {
        abort_unless($item->import_status === ImportItem::STATUS_PENDING, 404);
        abort_unless($item->cartorio_id === null || $item->cartorio_id === $cartorio->id, 404);
    }

    private function backToIndex(Cartorio $cartorio, Request $request): RedirectResponse
    {
        return redirect()->route('produtividade.flagrantes.index', [
            'cartorio' => $cartorio,
            'year' => $request->input('year', now()->year),
            'month' => $request->input('month', 0),
        ]);
    }
}
