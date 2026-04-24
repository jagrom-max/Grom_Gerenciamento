<?php

namespace App\Http\Controllers\Produtividade;

use App\Enums\LavradoUnidade;
use App\Http\Controllers\Controller;
use App\Models\Cartorio;
use App\Models\ProductivityBoletim;
use App\Support\Produtividade\BoletimQueryFilters;
use App\Models\User;
use App\Services\Produtividade\FlagranteImportService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class BoletimController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(BoletimQueryFilters::validatedRules());

        $user = $request->user();
        $cartorios = Cartorio::query()->visibleTo($user)->orderBy('number')->get();

        $year = (int) ($filters['year'] ?? now()->year);
        $month = array_key_exists('month', $filters) ? (int) $filters['month'] : 0;

        $selectedCartorio = isset($filters['cartorio_id'])
            ? $cartorios->firstWhere('id', $filters['cartorio_id'])
            : null;

        $scopeCartorioIds = $selectedCartorio
            ? [$selectedCartorio->id]
            : $cartorios->pluck('id')->all();

        $baseQuery = BoletimQueryFilters::apply(
            ProductivityBoletim::query()->with(['cartorio', 'productivityFlagrante']),
            $filters,
            $scopeCartorioIds,
        );

        $boletins = (clone $baseQuery)
            ->orderBy('reference_month')
            ->orderByDesc('data_fato')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $totalBoletins = (clone $baseQuery)->count();
        $totalFlagrantes = (clone $baseQuery)->where('is_flagrante', true)->count();
        $totalDdm = (clone $baseQuery)->where('is_flagrante', true)->where('lavrado_unidade', LavradoUnidade::Ddm->value)->count();
        $totalComMpu = (clone $baseQuery)->whereNotNull('mpu_numero')->where('mpu_numero', '!=', '')->count();
        $totalSemIp = (clone $baseQuery)->where(function ($query): void {
            $query->whereNull('num_ip')->orWhere('num_ip', '');
        })->count();
        $totalMpuSemIp = (clone $baseQuery)
            ->whereNotNull('mpu_numero')
            ->where('mpu_numero', '!=', '')
            ->where(function ($query): void {
                $query->whereNull('num_ip')->orWhere('num_ip', '');
            })
            ->count();

        $pendenciasCriticas = (clone $baseQuery)
            ->whereNotNull('mpu_numero')
            ->where('mpu_numero', '!=', '')
            ->where('encaminhado_outra_unidade', false)
            ->where(function ($query): void {
                $query->whereNull('mpu_decisao')->orWhere('mpu_decisao', '!=', 'INDEFERIDA');
            })
            ->where('despacho_fundamentado', false)
            ->where(function ($query): void {
                $query->whereNull('num_ip')->orWhere('num_ip', '');
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('data_fato')
            ->limit(120)
            ->get();

        return view('produtividade.boletins.index', [
            'filters' => $filters,
            'cartorios' => $cartorios,
            'selectedCartorio' => $selectedCartorio,
            'year' => $year,
            'month' => $month,
            'boletins' => $boletins,
            'totalBoletins' => $totalBoletins,
            'totalFlagrantes' => $totalFlagrantes,
            'totalNaoFlagrantes' => max($totalBoletins - $totalFlagrantes, 0),
            'totalDdm' => $totalDdm,
            'totalOutras' => max($totalFlagrantes - $totalDdm, 0),
            'totalComMpu' => $totalComMpu,
            'totalSemIp' => $totalSemIp,
            'totalMpuSemIp' => $totalMpuSemIp,
            'pendenciasCriticas' => $pendenciasCriticas,
        ]);
    }

    public function import(Request $request, FlagranteImportService $service): RedirectResponse
    {
        $data = $request->validate([
            'source_file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:12288'],
            'cartorio_id' => ['nullable', 'exists:cartorios,id'],
            'year' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer'],
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

        $batch = $result['batch'];
        $summary = $result['summary'];

        AuditLogger::log(
            moduleCode: 'produtividade',
            eventType: 'boletins.import_batch',
            entityType: 'import_batch',
            entityId: $batch->id,
            description: 'Lote importado para consolidacao unificada de boletins.',
            metadata: $summary,
        );

        return redirect()->route('produtividade.boletins.index', [
            'cartorio_id' => $data['cartorio_id'] ?? null,
            'year' => $data['year'] ?? null,
            'month' => $data['month'] ?? null,
        ])->with(
            'status',
            sprintf(
                'Importacao concluida. %d BO(s) consolidados, %d flagrante(s) enviados para a fila, %d pendencias substituidas, %d ignorados e %d erros.',
                (int) ($summary['bos_total'] ?? 0),
                (int) ($summary['rows_staged'] ?? 0),
                (int) ($summary['rows_updated'] ?? 0),
                (int) ($summary['rows_skipped'] ?? 0),
                (int) ($summary['error_count'] ?? 0),
            ),
        );
    }

    public function edit(ProductivityBoletim $boletim, Request $request): View
    {
        $this->ensureCanAccessCartorio($request->user(), $boletim->cartorio);

        return view('produtividade.boletins.edit', [
            'boletim' => $boletim,
        ]);
    }

    public function update(ProductivityBoletim $boletim, Request $request): RedirectResponse
    {
        $this->ensureCanAccessCartorio($request->user(), $boletim->cartorio);

        $data = $request->validate([
            'is_flagrante' => ['required', 'boolean'],
            'lavrado_unidade' => ['required', 'in:DDM,OUTRAS_UNIDADES'],
            'mpu_numero' => ['nullable', 'string', 'max:120'],
            'mpu_decisao' => ['nullable', 'in:DEFERIDA,INDEFERIDA'],
            'despacho_fundamentado' => ['nullable', 'boolean'],
            'encaminhado_outra_unidade' => ['nullable', 'boolean'],
            'encaminhado_para_unidade' => ['nullable', 'string', 'max:200'],
            'num_ipe' => ['nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer'],
            'cartorio_id' => ['nullable', 'string'],
        ]);

        $boletim->update([
            'is_flagrante' => (bool) $data['is_flagrante'],
            'lavrado_unidade' => $data['lavrado_unidade'],
            'mpu_numero' => $this->cleanNullable($data['mpu_numero'] ?? null),
            'mpu_decisao' => $this->cleanNullable($data['mpu_decisao'] ?? null),
            'despacho_fundamentado' => (bool) ($data['despacho_fundamentado'] ?? false),
            'encaminhado_outra_unidade' => (bool) ($data['encaminhado_outra_unidade'] ?? false),
            'encaminhado_para_unidade' => $this->cleanNullable($data['encaminhado_para_unidade'] ?? null),
            'num_ip' => $this->cleanNullable($data['num_ip'] ?? null),
            'num_ipe' => $this->cleanNullable($data['num_ipe'] ?? null),
            'num_cnj' => $this->cleanNullable($data['num_cnj'] ?? null),
            'notes' => $this->cleanNullable($data['notes'] ?? null),
        ]);

        return redirect()->route('produtividade.boletins.index', [
            'cartorio_id' => $data['cartorio_id'] ?? null,
            'year' => $data['year'] ?? null,
            'month' => $data['month'] ?? null,
        ])->with('status', 'Boletim atualizado com sucesso.');
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

    private function cleanNullable(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
