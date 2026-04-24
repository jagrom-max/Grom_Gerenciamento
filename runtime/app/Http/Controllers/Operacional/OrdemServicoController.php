<?php

namespace App\Http\Controllers\Operacional;

use App\Http\Controllers\Controller;
use App\Models\OperacionalOrdemServico;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrdemServicoController extends Controller
{
    public function index(Request $request): View
    {
        $q       = $request->input('q');
        $status  = $request->input('status');
        $tipo    = $request->input('tipo');
        $vencidas = $request->boolean('vencidas');

        $query = OperacionalOrdemServico::query();

        if ($q) {
            $query->where(function ($sub) use ($q): void {
                $sub->where('assunto', 'like', "%{$q}%")
                    ->orWhere('numero', 'like', "%{$q}%")
                    ->orWhere('solicitante', 'like', "%{$q}%")
                    ->orWhere('responsavel', 'like', "%{$q}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($tipo) {
            $query->where('tipo', $tipo);
        }

        if ($vencidas) {
            $query->whereDate('data_prazo', '<', today())
                  ->whereNotIn('status', ['Concluída', 'Cancelada']);
        }

        $ordens = $query->orderBy('data_emissao', 'desc')->get();

        $summary = [
            'total'         => OperacionalOrdemServico::count(),
            'abertas'       => OperacionalOrdemServico::where('status', 'Aberta')->count(),
            'em_andamento'  => OperacionalOrdemServico::where('status', 'Em andamento')->count(),
            'concluidas'    => OperacionalOrdemServico::where('status', 'Concluída')->count(),
            'vencidas'      => OperacionalOrdemServico::whereDate('data_prazo', '<', today())
                                   ->whereNotIn('status', ['Concluída', 'Cancelada'])->count(),
        ];

        return view('operacional.ordens.index', compact('ordens', 'summary'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateOs($request);

        $os = OperacionalOrdemServico::create([
            ...$data,
            'created_by' => auth()->user()->name,
        ]);

        AuditLogger::log('os.store', "OS #{$os->numero}: {$os->assunto}", $os->id);

        return back()->with('status', "Ordem de Serviço \"{$os->assunto}\" registrada.");
    }

    public function update(Request $request, OperacionalOrdemServico $ordem): RedirectResponse
    {
        $data = $this->validateOs($request, $ordem->id);

        $ordem->update([
            ...$data,
            'updated_by' => auth()->user()->name,
        ]);

        AuditLogger::log('os.update', "OS #{$ordem->numero}: {$ordem->assunto}", $ordem->id);

        return back()->with('status', "Ordem de Serviço atualizada.");
    }

    public function destroy(Request $request, OperacionalOrdemServico $ordem): RedirectResponse
    {
        $motivo = trim($request->input('motivo', ''));

        if (! $motivo) {
            return back()->withErrors(['motivo' => 'Informe o motivo para excluir a OS.']);
        }

        $ordem->update([
            'deleted_by'     => auth()->user()->name,
            'deleted_motivo' => $motivo,
        ]);
        $ordem->delete();

        AuditLogger::log('os.destroy', "OS excluída: {$ordem->assunto}", $ordem->id);

        return back()->with('status', 'Ordem de Serviço excluída.');
    }

    // ─── Validação centralizada ───────────────────────────────────────────────

    private function validateOs(Request $request, ?string $skipId = null): array
    {
        return $request->validate([
            'numero'          => 'nullable|string|max:30',
            'data_emissao'    => 'nullable|date',
            'data_prazo'      => 'nullable|date',
            'cartorio_id'     => 'nullable|string|max:60',
            'solicitante'     => 'nullable|string|max:120',
            'tipo'            => 'nullable|string|max:80',
            'assunto'         => 'required|string|max:255',
            'descricao'       => 'nullable|string',
            'status'          => 'required|string|in:' . implode(',', OperacionalOrdemServico::STATUSES),
            'data_conclusao'  => 'nullable|date',
            'responsavel'     => 'nullable|string|max:120',
            'resultado'       => 'nullable|string',
        ]);
    }
}
