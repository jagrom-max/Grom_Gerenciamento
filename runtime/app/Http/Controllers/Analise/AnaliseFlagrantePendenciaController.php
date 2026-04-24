<?php

namespace App\Http\Controllers\Analise;

use App\Http\Controllers\Controller;
use App\Models\AnaliseFlagramtePendencia;
use App\Models\Cartorio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnaliseFlagrantePendenciaController extends Controller
{
    /**
     * Lista de flagrantes aguardando auditoria de cartório.
     */
    public function index(Request $request): View
    {
        $status = $request->get('status', 'pending');

        $query = AnaliseFlagramtePendencia::with('cartorio', 'reviewer')
            ->when($status && $status !== 'todos', fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->get('q') . '%';
                $q->where(fn ($sub) => $sub->where('spj', 'LIKE', $term)
                    ->orWhere('naturezas', 'LIKE', $term)
                    ->orWhere('lavrado', 'LIKE', $term));
            })
            ->when($request->filled('ano'), fn ($q) => $q->where('spj_year', $request->get('ano')))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at');

        $pendencias = $query->paginate(30)->withQueryString();

        $cartorios = Cartorio::where('is_active', true)
            ->orderBy('number')
            ->get(['id', 'number', 'code', 'name']);

        $totais = AnaliseFlagramtePendencia::selectRaw("
            SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'approved'  THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'corrected' THEN 1 ELSE 0 END) AS corrected,
            SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) AS dismissed,
            COUNT(*) AS total
        ")->first();

        return view('analise.bos.auditoria-flagrantes', compact(
            'pendencias',
            'cartorios',
            'totais',
            'status',
        ));
    }

    /**
     * Aprova / corrige / dispensa uma pendência.
     */
    public function update(Request $request, AnaliseFlagramtePendencia $pendencia): RedirectResponse
    {
        $request->validate([
            'acao'       => ['required', 'in:approved,corrected,dismissed'],
            'cartorio_id'=> ['required_if:acao,approved,corrected', 'nullable', 'uuid',
                             'exists:cartorios,id'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ], [
            'acao.required'       => 'Selecione uma ação.',
            'cartorio_id.required_if' => 'Informe o cartório ao aprovar ou corrigir.',
        ]);

        $pendencia->update([
            'status'       => $request->acao,
            'cartorio_id'  => in_array($request->acao, ['approved', 'corrected'])
                ? $request->cartorio_id
                : null,
            'reviewed_by'  => auth()->id(),
            'reviewed_at'  => now(),
            'notes'        => $request->notes,
        ]);

        $label = match ($request->acao) {
            'approved'  => 'Cartório confirmado.',
            'corrected' => 'Cartório corrigido.',
            'dismissed' => 'Pendência dispensada.',
        };

        return back()->with('success', "SPJ {$pendencia->spj}: {$label}");
    }

    /**
     * Aprova / corrige / dispensa múltiplos registros em lote.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'         => ['required', 'array', 'min:1'],
            'ids.*'       => ['uuid'],
            'acao'        => ['required', 'in:approved,corrected,dismissed'],
            'cartorio_id' => ['required_if:acao,approved,corrected', 'nullable', 'uuid',
                              'exists:cartorios,id'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $count = AnaliseFlagramtePendencia::whereIn('id', $request->ids)
            ->where('status', 'pending')
            ->count();

        AnaliseFlagramtePendencia::whereIn('id', $request->ids)
            ->where('status', 'pending')
            ->update([
                'status'      => $request->acao,
                'cartorio_id' => in_array($request->acao, ['approved', 'corrected'])
                    ? $request->cartorio_id
                    : null,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'notes'       => $request->notes,
            ]);

        return back()->with('success', "{$count} pendências processadas.");
    }
}
