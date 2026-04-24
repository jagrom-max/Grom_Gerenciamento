<?php

namespace App\Http\Controllers\Operacional;

use App\Http\Controllers\Controller;
use App\Models\OperacionalMandado;
use App\Services\Operacional\LegacyMandadosReader;
use App\Services\Operacional\LegacyMandadosSyncService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MandadosController extends Controller
{
    // -------------------------------------------------------
    // Index
    // -------------------------------------------------------
    public function index(Request $request, LegacyMandadosReader $legacyReader): View
    {
        $filters = $request->validate([
            'q'           => ['nullable', 'string', 'max:120'],
            'tipo_sigla'  => ['nullable', 'string', 'max:8'],
            'procedimento'=> ['nullable', 'in:Em Aberto,Cumprido,Revogado,todos'],
            'vencidos'    => ['nullable', 'boolean'],
        ]);

        $query = OperacionalMandado::query()
            ->orderBy('tipo_sigla')
            ->orderBy('cnj_numero')
            ->orderByDesc('created_at');

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $query->where(function ($q) use ($like): void {
                $q->where('nome', 'like', $like)
                  ->orWhere('cpf', 'like', $like)
                  ->orWhere('rg', 'like', $like)
                  ->orWhere('cnj_numero', 'like', $like);
            });
        }

        if (!empty($filters['tipo_sigla']) && $filters['tipo_sigla'] !== 'todos') {
            $query->where('tipo_sigla', $filters['tipo_sigla']);
        }

        $procedimento = $filters['procedimento'] ?? 'todos';
        if ($procedimento && $procedimento !== 'todos') {
            $query->where('procedimento', $procedimento);
        }

        if (!empty($filters['vencidos'])) {
            $query->where('procedimento', 'Em Aberto')
                  ->whereDate('validade', '<', Carbon::today());
        }

        $mandados = $query->get();

        $legacySnapshot = null;
        $legacyWarnings = [];
        try {
            $legacySnapshot = $legacyReader->snapshot();
        } catch (\Throwable $e) {
            $legacyWarnings[] = $e->getMessage();
        }

        $today = Carbon::today();

        return view('operacional.mandados.index', [
            'filters'         => $filters,
            'mandados'        => $mandados,
            'tiposSigla'      => OperacionalMandado::TIPOS_SIGLA,
            'procedimentos'   => OperacionalMandado::PROCEDIMENTOS,
            'cumprido_por'    => OperacionalMandado::CUMPRIDO_POR,
            'regimes'         => OperacionalMandado::REGIMES,
            'leis'            => OperacionalMandado::LEIS,
            'legacySnapshot'  => $legacySnapshot,
            'legacyWarnings'  => $legacyWarnings,
            'summary' => [
                'total'         => OperacionalMandado::query()->count(),
                'em_aberto'     => OperacionalMandado::query()->where('procedimento', 'Em Aberto')->count(),
                'cumpridos'     => OperacionalMandado::query()->where('procedimento', 'Cumprido')->count(),
                'revogados'     => OperacionalMandado::query()->where('procedimento', 'Revogado')->count(),
                'vencidos'      => OperacionalMandado::query()
                    ->where('procedimento', 'Em Aberto')
                    ->whereDate('validade', '<', $today)
                    ->count(),
                'exibidos'      => $mandados->count(),
                'legacy_total'  => $legacySnapshot['total'] ?? 0,
                'legacy_synced' => OperacionalMandado::query()->whereNotNull('legacy_id')->count(),
            ],
        ]);
    }

    // -------------------------------------------------------
    // Store
    // -------------------------------------------------------
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validate($request);
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        $data = $this->deriveTypeFields($data);

        $mandado = OperacionalMandado::query()->create(array_merge($data, [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]));

        AuditLogger::log(
            moduleCode: 'operacional',
            eventType: 'mandados.create',
            entityType: 'operacional_mandado',
            entityId: $mandado->id,
            description: 'Mandado cadastrado.',
            metadata: [
                'tipo_sigla'   => $mandado->tipo_sigla,
                'cnj_numero'   => $mandado->cnj_numero,
                'nome'         => $mandado->nome,
                'procedimento' => $mandado->procedimento,
            ]
        );

        return redirect()
            ->route('operacional.mandados.index')
            ->with('status', "Mandado {$mandado->tipo_sigla} — {$mandado->nome} cadastrado com sucesso.");
    }

    // -------------------------------------------------------
    // Update
    // -------------------------------------------------------
    public function update(Request $request, OperacionalMandado $mandado): RedirectResponse
    {
        $data = $this->validate($request, $mandado->id);
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        $data = $this->deriveTypeFields($data);

        $mandado->update(array_merge($data, [
            'updated_by' => auth()->id(),
        ]));

        AuditLogger::log(
            moduleCode: 'operacional',
            eventType: 'mandados.update',
            entityType: 'operacional_mandado',
            entityId: $mandado->id,
            description: 'Mandado atualizado.',
            metadata: [
                'tipo_sigla'   => $mandado->tipo_sigla,
                'cnj_numero'   => $mandado->cnj_numero,
                'procedimento' => $mandado->procedimento,
            ]
        );

        return redirect()
            ->route('operacional.mandados.index')
            ->with('status', "Mandado atualizado com sucesso.");
    }

    // -------------------------------------------------------
    // Destroy (soft delete)
    // -------------------------------------------------------
    public function destroy(Request $request, OperacionalMandado $mandado): RedirectResponse
    {
        $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $mandado->update([
            'deleted_by'      => auth()->id(),
            'deleted_motivo'  => trim($request->string('motivo')),
        ]);
        $mandado->delete();

        AuditLogger::log(
            moduleCode: 'operacional',
            eventType: 'mandados.delete',
            entityType: 'operacional_mandado',
            entityId: $mandado->id,
            description: 'Mandado excluído (lógico).',
            metadata: [
                'motivo'    => $request->string('motivo'),
                'tipo_sigla'=> $mandado->tipo_sigla,
                'nome'      => $mandado->nome,
            ]
        );

        return redirect()
            ->route('operacional.mandados.index')
            ->with('status', "Mandado excluído com sucesso.");
    }

    // -------------------------------------------------------
    // Sync do legado
    // -------------------------------------------------------
    public function syncLegacy(LegacyMandadosSyncService $syncService): RedirectResponse
    {
        try {
            $result = $syncService->sync(auth()->id());

            AuditLogger::log(
                moduleCode: 'operacional',
                eventType: 'mandados.sync_legacy',
                entityType: 'operacional_mandados',
                entityId: 'batch',
                description: 'Sincronização do legado de mandados concluída.',
                metadata: $result
            );

            $msg = "Legado sincronizado: {$result['inserted']} novos, {$result['updated']} atualizados, {$result['skipped']} sem alteração.";
        } catch (\Throwable $e) {
            $msg = 'Falha na sincronização: ' . $e->getMessage();
            return redirect()->route('operacional.mandados.index')->with('error', $msg);
        }

        return redirect()->route('operacional.mandados.index')->with('status', $msg);
    }

    // -------------------------------------------------------
    // Validação centralizada
    // -------------------------------------------------------
    private function validate(Request $request, ?string $skipId = null): array
    {
        return $request->validate([
            'tipo_sigla'       => ['required', 'string', 'in:' . implode(',', array_keys(OperacionalMandado::TIPOS_SIGLA))],
            'cnj_numero'       => ['nullable', 'string', 'max:30'],
            'vara'             => ['nullable', 'string', 'max:120'],
            'nome'             => ['required', 'string', 'max:255'],
            'cpf'              => ['nullable', 'string', 'regex:/^\d{11}$/'],
            'rg'               => ['nullable', 'string', 'max:30'],
            'data_emissao'     => ['required', 'date'],
            'validade'         => ['required', 'date', 'after_or_equal:data_emissao'],
            'tipificacao_penal'=> ['nullable', 'string', 'max:30'],
            'artigo'           => ['nullable', 'string', 'max:30'],
            'paragrafo'        => ['nullable', 'string', 'max:30'],
            'tipificacoes_extra' => ['nullable', 'string', 'max:5000'],
            'pena_anos'        => ['nullable', 'integer', 'min:0', 'max:999'],
            'pena_meses'       => ['nullable', 'integer', 'min:0', 'max:11'],
            'pena_dias'        => ['nullable', 'integer', 'min:0', 'max:365'],
            'regime'           => ['nullable', 'string', 'in:' . implode(',', OperacionalMandado::REGIMES)],
            'procedimento'     => ['required', 'in:' . implode(',', OperacionalMandado::PROCEDIMENTOS)],
            'cumprido_por'     => ['nullable', 'string', 'required_if:procedimento,Cumprido'],
            'data_cumprimento' => ['nullable', 'date', 'required_if:procedimento,Cumprido'],
            'bo_numero'        => ['nullable', 'string', 'max:20', 'required_if:procedimento,Cumprido'],
            'observacoes'      => ['nullable', 'string', 'max:3000'],
        ], [
            'nome.required'             => 'O nome do alvo é obrigatório.',
            'tipo_sigla.required'       => 'Selecione o tipo de mandado.',
            'tipo_sigla.in'             => 'Tipo de mandado inválido.',
            'data_emissao.required'     => 'Informe a data de emissão.',
            'validade.required'         => 'Informe a data de validade.',
            'validade.after_or_equal'   => 'A validade não pode ser anterior à emissão.',
            'procedimento.required'     => 'Informe o procedimento.',
            'cpf.regex'                 => 'CPF deve conter exatamente 11 dígitos numéricos.',
            'cumprido_por.required_if'  => 'Informe quem cumpriu o mandado.',
            'data_cumprimento.required_if' => 'Informe a data de cumprimento.',
            'bo_numero.required_if'     => 'Informe o número do B.O.',
        ]);
    }

    private function deriveTypeFields(array $data): array
    {
        $mapping = OperacionalMandado::SIGLA_PARA_TIPO;
        [$tipo, $subtipo] = $mapping[$data['tipo_sigla']] ?? ['Mandado de Prisão', null];

        // Normaliza tipificacoes_extra: string JSON → array (ou null)
        if (isset($data['tipificacoes_extra']) && is_string($data['tipificacoes_extra'])) {
            $decoded = json_decode($data['tipificacoes_extra'], true);
            $data['tipificacoes_extra'] = (is_array($decoded) && count($decoded) > 0) ? $decoded : null;
        }

        return array_merge($data, [
            'tipo_mandado'  => $tipo,
            'subtipo_prisao'=> $subtipo,
        ]);
    }
}
