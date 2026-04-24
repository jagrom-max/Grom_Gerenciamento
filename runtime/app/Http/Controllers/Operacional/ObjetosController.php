<?php

namespace App\Http\Controllers\Operacional;

use App\Http\Controllers\Controller;
use App\Models\OperacionalObjeto;
use App\Models\OperacionalObjetoLocal;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ObjetosController extends Controller
{
    // -------------------------------------------------------
    // Index
    // -------------------------------------------------------
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q'        => ['nullable', 'string', 'max:120'],
            'situacao' => ['nullable', 'string', 'max:40'],
            'local_id' => ['nullable', 'integer'],
            'ano'      => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $query = OperacionalObjeto::query()
            ->with('localCustodia')
            ->orderByDesc('created_at');

        if (! empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $query->where(function ($q) use ($like): void {
                $q->where('objeto', 'like', $like)
                  ->orWhere('rdo_num', 'like', $like)
                  ->orWhere('ip_tc_ddm', 'like', $like)
                  ->orWhere('lacre', 'like', $like)
                  ->orWhere('numero_serie', 'like', $like);
            });
        }

        if (! empty($filters['situacao']) && $filters['situacao'] !== 'todas') {
            $query->where('situacao', $filters['situacao']);
        }

        if (! empty($filters['local_id'])) {
            $query->where('local_custodia_id', $filters['local_id']);
        }

        if (! empty($filters['ano'])) {
            $query->where('ano', $filters['ano']);
        }

        $objetos = $query->get();
        $locais  = OperacionalObjetoLocal::ativos()->get();

        return view('operacional.objetos.index', [
            'filters'    => $filters,
            'objetos'    => $objetos,
            'locais'     => $locais,
            'situacoes'  => OperacionalObjeto::SITUACOES,
            'destStatus' => OperacionalObjeto::DEST_STATUSES,
            'summary' => [
                'total'            => OperacionalObjeto::query()->count(),
                'em_custodia'      => OperacionalObjeto::query()->whereIn('situacao', ['Em Custódia', 'Enviado IC'])->count(),
                'aguard_destinacao'=> OperacionalObjeto::query()->where('situacao', 'Aguardando Destinação')->count(),
                'restituidos'      => OperacionalObjeto::query()->where('situacao', 'Restituído')->count(),
                'destruidos'       => OperacionalObjeto::query()->where('situacao', 'Destruído')->count(),
                'exibidos'         => $objetos->count(),
                'locais_ativos'    => $locais->count(),
            ],
        ]);
    }

    // -------------------------------------------------------
    // Store
    // -------------------------------------------------------
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateObjeto($request);

        $objeto = OperacionalObjeto::query()->create(array_merge($data, [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]));

        AuditLogger::log(
            moduleCode: 'operacional',
            eventType: 'objetos.create',
            entityType: 'operacional_objeto',
            entityId: $objeto->id,
            description: 'Objeto apreendido cadastrado.',
            metadata: [
                'rdo_num'  => $objeto->rdo_num,
                'objeto'   => $objeto->objeto,
                'situacao' => $objeto->situacao,
            ]
        );

        return redirect()
            ->route('operacional.objetos.index')
            ->with('status', "Objeto '{$objeto->objeto}' cadastrado com sucesso.");
    }

    // -------------------------------------------------------
    // Update
    // -------------------------------------------------------
    public function update(Request $request, OperacionalObjeto $objeto): RedirectResponse
    {
        $data = $this->validateObjeto($request, $objeto->id);

        $objeto->update(array_merge($data, ['updated_by' => auth()->id()]));

        AuditLogger::log(
            moduleCode: 'operacional',
            eventType: 'objetos.update',
            entityType: 'operacional_objeto',
            entityId: $objeto->id,
            description: 'Objeto apreendido atualizado.',
            metadata: ['situacao' => $objeto->situacao, 'rdo_num' => $objeto->rdo_num]
        );

        return redirect()
            ->route('operacional.objetos.index')
            ->with('status', 'Objeto atualizado com sucesso.');
    }

    // -------------------------------------------------------
    // Destroy (soft delete)
    // -------------------------------------------------------
    public function destroy(Request $request, OperacionalObjeto $objeto): RedirectResponse
    {
        $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $objeto->update([
            'deleted_by'     => auth()->id(),
            'deleted_motivo' => trim($request->string('motivo')),
        ]);
        $objeto->delete();

        AuditLogger::log(
            moduleCode: 'operacional',
            eventType: 'objetos.delete',
            entityType: 'operacional_objeto',
            entityId: $objeto->id,
            description: 'Objeto excluído (lógico).',
            metadata: ['motivo' => $request->string('motivo'), 'objeto' => $objeto->objeto]
        );

        return redirect()
            ->route('operacional.objetos.index')
            ->with('status', 'Objeto excluído com sucesso.');
    }

    // -------------------------------------------------------
    // Toggle situação
    // -------------------------------------------------------
    public function toggleSituacao(Request $request, OperacionalObjeto $objeto): RedirectResponse
    {
        $data = $request->validate([
            'situacao' => ['required', 'in:' . implode(',', OperacionalObjeto::SITUACOES)],
        ]);

        $anterior = $objeto->situacao;
        $objeto->update(['situacao' => $data['situacao'], 'updated_by' => auth()->id()]);

        AuditLogger::log(
            moduleCode: 'operacional',
            eventType: 'objetos.situacao',
            entityType: 'operacional_objeto',
            entityId: $objeto->id,
            description: "Situação alterada de '{$anterior}' para '{$data['situacao']}'.",
            metadata: ['anterior' => $anterior, 'nova' => $data['situacao']]
        );

        return redirect()
            ->route('operacional.objetos.index')
            ->with('status', "Situação alterada para '{$data['situacao']}'.");
    }

    // -------------------------------------------------------
    // Locais (AJAX-like para combobox dinâmico, se necessário)
    // -------------------------------------------------------
    public function storeLocal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:100', 'unique:operacional_objetos_locais,nome'],
        ]);

        $local = OperacionalObjetoLocal::query()->create([
            'nome'      => trim($data['nome']),
            'is_active' => true,
        ]);

        AuditLogger::log(
            moduleCode: 'operacional',
            eventType: 'objetos.local.create',
            entityType: 'operacional_objeto_local',
            entityId: (string) $local->id,
            description: "Local de custódia '{$local->nome}' cadastrado.",
        );

        return redirect()
            ->route('operacional.objetos.index')
            ->with('status', "Local '{$local->nome}' cadastrado.");
    }

    public function toggleLocal(OperacionalObjetoLocal $local): RedirectResponse
    {
        $local->update(['is_active' => ! $local->is_active]);

        AuditLogger::log(
            moduleCode: 'operacional',
            eventType: 'objetos.local.toggle',
            entityType: 'operacional_objeto_local',
            entityId: (string) $local->id,
            description: "Local '{$local->nome}' " . ($local->is_active ? 'ativado' : 'desativado') . '.',
        );

        return redirect()
            ->route('operacional.objetos.index')
            ->with('status', "Local '{$local->nome}' " . ($local->is_active ? 'ativado' : 'desativado') . '.');
    }

    // -------------------------------------------------------
    // Validação centralizada
    // -------------------------------------------------------
    private function validateObjeto(Request $request, ?string $skipId = null): array
    {
        return $request->validate([
            'rdo_num'               => ['nullable', 'string', 'max:30'],
            'ano'                   => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'lacre'                 => ['nullable', 'string', 'max:40'],
            'ip_tc_ddm'             => ['nullable', 'string', 'max:60'],
            'ip_externo'            => ['nullable', 'string', 'max:60'],
            'tipo_objeto'           => ['nullable', 'string', 'max:120'],
            'objeto'                => ['required', 'string', 'max:5000'],
            'quantidade'            => ['nullable', 'integer', 'min:1', 'max:9999'],
            'unidade'               => ['nullable', 'string', 'max:30'],
            'marca'                 => ['nullable', 'string', 'max:80'],
            'modelo'                => ['nullable', 'string', 'max:80'],
            'cor'                   => ['nullable', 'string', 'max:50'],
            'numero_serie'          => ['nullable', 'string', 'max:80'],
            'ic_remessa'            => ['nullable', 'string', 'max:60'],
            'ic_retorno'            => ['nullable', 'string', 'max:60'],
            'lacre_ic'              => ['nullable', 'string', 'max:40'],
            'laudo'                 => ['nullable', 'string', 'max:80'],
            'local_custodia_id'     => ['nullable', 'integer', 'exists:operacional_objetos_locais,id'],
            'caixa'                 => ['nullable', 'string', 'max:30'],
            'situacao'              => ['required', 'in:' . implode(',', OperacionalObjeto::SITUACOES)],
            'dest_solicitado'       => ['nullable', 'string', 'max:80'],
            'dest_data_solicitado'  => ['nullable', 'date'],
            'dest_autorizado'       => ['nullable', 'string', 'max:80'],
            'dest_data_autorizado'  => ['nullable', 'date'],
            'dest_status'           => ['nullable', 'in:' . implode(',', OperacionalObjeto::DEST_STATUSES)],
            'dest_data'             => ['nullable', 'date'],
            'observacoes'           => ['nullable', 'string', 'max:5000'],
        ], [
            'objeto.required'   => 'Descreva o objeto apreendido.',
            'situacao.required' => 'Informe a situação do objeto.',
        ]);
    }
}
