<?php

namespace App\Http\Controllers\Analise;

use App\Http\Controllers\Controller;
use App\Models\AnaliseFlagramtePendencia;
use App\Services\Analise\AnaliseBoImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnaliseBoImportController extends Controller
{
    public function create(): View
    {
        return view('analise.bos.import');
    }

    public function store(Request $request, AnaliseBoImportService $service): RedirectResponse
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:20480'],
        ], [
            'arquivo.required' => 'Selecione um arquivo para importar.',
            'arquivo.mimes'    => 'Formato inválido. Envie XLSX, CSV ou TXT.',
            'arquivo.max'      => 'O arquivo não pode ser maior que 20 MB.',
        ]);

        try {
            $result = $service->importUploadedFile($request->file('arquivo'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['arquivo' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('analise.bos.import.resultado')
            ->with('import_result', $result);
    }

    /**
     * Exibe o resultado do último import com estatísticas de flagrantes por cartório
     * e alerta sobre pendências de auditoria criadas.
     */
    public function resultado(Request $request): View|RedirectResponse
    {
        $result = session('import_result');

        if (! $result) {
            return redirect()->route('analise.bos.import');
        }

        $source = $result['source'];

        // Estatísticas do arquivo recém-importado — agrupadas por cartório
        $porCartorio = DB::table('analise_bos')
            ->select(
                DB::raw("COALESCE(NULLIF(TRIM(cartorio_ip), ''), 'Sem cartório') AS cartorio"),
                DB::raw('COUNT(*) AS total'),
                DB::raw('SUM(flagrante) AS flagrantes'),
                DB::raw('SUM(ato_infracional) AS atos_infracionais'),
            )
            ->where('import_source', $source)
            ->groupBy('cartorio')
            ->orderByDesc('total')
            ->get();

        // Período coberto pelo arquivo
        $periodo = DB::table('analise_bos')
            ->where('import_source', $source)
            ->selectRaw("MIN(data_ocorrencia) AS inicio, MAX(data_ocorrencia) AS fim")
            ->first();

        // Pendências criadas por este import
        $pendenciasImport = AnaliseFlagramtePendencia::where('import_source', $source)
            ->where('status', 'pending')
            ->count();

        // Total geral de pendências no sistema
        $totalPendentes = AnaliseFlagramtePendencia::where('status', 'pending')->count();

        return view('analise.bos.import-resultado', compact(
            'result',
            'source',
            'porCartorio',
            'periodo',
            'pendenciasImport',
            'totalPendentes',
        ));
    }
}
