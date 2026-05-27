<?php

namespace App\Http\Controllers\Escalas;

use App\Http\Controllers\Controller;
use App\Models\EscalaDelegadoExterno;
use App\Models\EscalaDia;
use App\Models\EscalaPlantaoExterno;
use App\Models\EscalaPlantaoFuncionario;
use App\Models\EscalaVersao;

use App\Models\RhFuncionario;
use App\Models\RhHoliday;
use App\Services\Escalas\GeradorEscalaMensalService;
use App\Services\Escalas\LegacyEscalasReader;
use App\Services\Escalas\LegacyEscalasSyncService;
use App\Support\AuditLogger;
use App\Support\SqliteDatabaseBackup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EscalasController extends Controller
{
    // -------------------------------------------------------
    // Visualização — Escala mensal
    // -------------------------------------------------------

    /**
     * Retorna as substituições da Delegada DDM para o mês/ano informado.
     */
    protected function getSubstituicoesDdm(array $filters)
    {
        // Busca substituições que tenham interseção com o mês/ano
        $inicioMes = sprintf('%04d-%02d-01', $filters['ano'], $filters['mes']);
        $fimMes = date('Y-m-t', strtotime($inicioMes));
        return \App\Models\EscalaSubstituicaoDdm::query()
            ->where(function($q) use ($inicioMes, $fimMes) {
                $q->whereBetween('data_inicio', [$inicioMes, $fimMes])
                  ->orWhereBetween('data_fim', [$inicioMes, $fimMes])
                  ->orWhere(function($q2) use ($inicioMes, $fimMes) {
                      $q2->where('data_inicio', '<=', $inicioMes)
                         ->where('data_fim', '>=', $fimMes);
                  });
            })
            ->orderBy('data_inicio')
            ->get();
    }

    public function index(Request $request): View
    {
        $filters = $this->resolveFilters($request);

        // Todas as versões PHP do mês (para o histórico)
        $todasVersoes = EscalaVersao::query()
            ->where('ano', $filters['ano'])
            ->where('mes', $filters['mes'])
            ->orderBy('versao')
            ->get();

        // Versão requisitada (parâmetro opcional) ou a mais recente
        // SEMPRE forca leitura da versao mais recente
        $phpDias   = EscalaDia::diasDoMes($filters['ano'], $filters['mes']);
        $phpVersao = $phpDias->max('versao');
        $this->marcarConferenciaSePossivel($filters['ano'], $filters['mes'], $phpDias);
        if ($phpDias->isEmpty()) {
            $this->tryAutoSyncLegacy($request->user()?->id);
            $phpDias   = EscalaDia::diasDoMes($filters['ano'], $filters['mes']);
            $phpVersao = $phpDias->max('versao');
        }

        $snapshot = $phpDias->isEmpty()
            ? $this->loadLegacySnapshot($request, $filters['ano'], $filters['mes'])
            : $this->emptyLegacySnapshot();
        $snapshot['year'] = $filters['ano'];
        $snapshot['month'] = $filters['mes'];

        $phpHolidays = $this->buildMonthHolidays($filters['ano'], $filters['mes']);
        if (! empty($phpHolidays) || empty($snapshot['holidays'])) {
            $snapshot['holidays'] = $phpHolidays;
        }

        $phpFuncionarios = RhFuncionario::query()
            ->with(['cargo', 'afastamentos' => fn ($q) => $q->orderByDesc('start_date')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $catalogo = EscalaPlantaoExterno::query()->ativos()->get();
        $catalogoTodos = EscalaPlantaoExterno::query()->orderBy('id')->get();

        // Plantões do mês por data (PHP)
        $plantoesMes = $this->carregarPlantoesMes($filters['ano'], $filters['mes']);
        $feriadosPorData = collect($phpHolidays)->keyBy('date');

        // Anos disponíveis no PHP
        $anosPhp = EscalaDia::query()
            ->selectRaw('DISTINCT ano')
            ->orderByDesc('ano')
            ->pluck('ano')
            ->toArray();
        $snapshot['available_years'] = ! empty($anosPhp)
            ? array_values(array_unique(array_merge($anosPhp, $snapshot['available_years'] ?? [])))
            : ($snapshot['available_years'] ?? [$filters['ano']]);

        $delegadosExternos = EscalaDelegadoExterno::ativos()->get();

        // Cabeçalho da versão exibida
        $escalaVersao = null;
        if ($phpDias->isNotEmpty() && $phpVersao) {
            $escalaVersao = EscalaVersao::ativaOuCriar(
                $filters['ano'],
                $filters['mes'],
                $phpVersao,
                optional($request->user())->id
            );
        }

        $escalaLinhas = collect();
        if ($phpDias->isNotEmpty()) {
            $escalaLinhas = $phpDias->map(function ($dia) use ($plantoesMes, $feriadosPorData) {
                $dtCarbon = Carbon::parse($dia->data);
                $dataRef = $dtCarbon->toDateString();
                $plantaoTextoAtual = $this->formatarPlantaoTexto($plantoesMes[$dataRef] ?? []);
                $plantaoTexto = $plantaoTextoAtual !== ''
                    ? $plantaoTextoAtual
                    : trim((string) ($dia->plantao_externo ?? ''));

                return [
                    'source' => 'php',
                    'id' => $dia->id,
                    'date_label' => $dtCarbon->format('d/m'),
                    'date' => $dtCarbon->toDateString(),
                    'day_label' => $dtCarbon->locale('pt_BR')->isoFormat('ddd'),
                    'display_mode' => $feriadosPorData->has($dataRef) ? 'holiday' : 'normal',
                    'is_weekend' => $dtCarbon->isWeekend(),
                    'is_fechada' => (bool) $dia->is_fechada,
                    'escrivao' => trim((string) ($dia->escrivao ?? '')),
                    'operacional' => trim((string) ($dia->operacional ?? '')),
                    'fechar' => trim((string) ($dia->fechar_nome ?? '')),
                    'delegada' => trim((string) ($dia->delegada ?? '')),
                    'plantao_externo' => $plantaoTexto,
                    'plantao_items' => $this->splitPlantaoTexto($plantaoTexto),
                ];
            })->values();
        } elseif (! empty($snapshot['scale_rows'])) {
            $escalaLinhas = collect($snapshot['scale_rows'])->map(function ($row) {
                $plantaoTexto = trim((string) ($row['plantao_externo'] ?? ''));

                return [
                    'source' => 'legacy',
                    'date_label' => (string) ($row['date_label'] ?? ''),
                    'date' => (string) ($row['date'] ?? ''),
                    'day_label' => (string) ($row['day_label'] ?? ''),
                    'display_mode' => (string) ($row['display_mode'] ?? 'normal'),
                    'is_weekend' => (($row['display_mode'] ?? 'normal') === 'weekend'),
                    'escrivao' => trim((string) ($row['escrivao'] ?? '')),
                    'operacional' => trim((string) ($row['operacional'] ?? '')),
                    'fechar' => trim((string) ($row['fechar'] ?? '')),
                    'delegada' => trim((string) ($row['delegada'] ?? '')),
                    'plantao_externo' => $plantaoTexto,
                    'plantao_items' => $this->splitPlantaoTexto($plantaoTexto),
                ];
            })->values();
        }

        $escalaLinhas = $this->incluirLinhasDePlantoesSemExpediente(
            $escalaLinhas,
            $plantoesMes,
            $feriadosPorData
        );

        $plantaoLinhas = collect();
        if (! empty($plantoesMes)) {
            $plantaoLinhas = collect($plantoesMes)
                ->flatten(1)
                ->take(20)
                ->map(function ($pf) {
                    return [
                        'source' => 'php',
                        'date' => Carbon::parse($pf->data)->format('d/m/Y'),
                        'funcionario' => $pf->funcionario?->short_name ?? $pf->funcionario?->name ?? '—',
                        'cargo' => $pf->funcionario?->cargo?->name ?? 'Cargo não informado',
                        'plantao' => $pf->plantaoExterno?->sigla ?? '—',
                        'unidade' => $pf->plantaoExterno?->unidade ?? 'N/A',
                        'regra' => $pf->plantaoExterno?->regra ?? 'N/A',
                    ];
                })->values();
        } elseif (! empty($snapshot['plantoes'])) {
            $plantaoLinhas = collect(array_slice($snapshot['plantoes'], 0, 20))->map(function ($plantao) {
                return [
                    'source' => 'legacy',
                    'date' => (string) ($plantao['date_label'] ?? ''),
                    'funcionario' => (string) ($plantao['funcionario_nome'] ?? '—'),
                    'cargo' => (string) ($plantao['funcionario_cargo'] ?? 'Cargo não informado'),
                    'plantao' => trim((string) ($plantao['plantao_sigla'] ?? '')) ?: trim((string) ($plantao['plantao_nome'] ?? '')) ?: '—',
                    'unidade' => trim((string) ($plantao['plantao_unidade'] ?? '')) ?: 'N/A',
                    'regra' => trim((string) ($plantao['plantao_regra'] ?? '')) ?: 'N/A',
                ];
            })->values();
        }

        $snapshot['summary'] = [
            'dias_total' => $phpDias->count(),
            'dias_com_escrivao' => $phpDias->whereNotNull('escrivao')->whereNotIn('escrivao', [''])->count(),
            'dias_com_delegada' => $phpDias->whereNotNull('delegada')->whereNotIn('delegada', [''])->count(),
            'dias_com_operacional' => $phpDias->whereNotNull('operacional')->whereNotIn('operacional', [''])->count(),
            'dias_com_plantao_externo' => $escalaLinhas->filter(fn (array $row): bool => trim((string) ($row['plantao_externo'] ?? '')) !== '')->count(),
            'plantoes_atribuicoes' => collect($plantoesMes)->flatten(1)->count(),
            'plantoes_catalogo_ativos' => $catalogo->count(),
            'funcionarios_total' => $phpFuncionarios->count(),
            'funcionarios_ativos' => $phpFuncionarios->count(),
            'funcionarios_concorrem' => $phpFuncionarios->where('concorre_escala', true)->count(),
            'funcionarios_em_afastamento' => $phpFuncionarios->filter(fn (RhFuncionario $f): bool => $f->currentAfastamento() !== null)->count(),
        ];

        return view('escalas.index', [
            'filters'           => $filters,
            'snapshot'          => $snapshot,
            'phpDias'           => $phpDias,
            'phpVersao'         => $phpVersao,
            'phpFuncionarios'   => $phpFuncionarios,
            'phpAtivos'         => $phpFuncionarios->where('is_active', true)->values(),
            'catalogo'          => $catalogo,
            'catalogoTodos'     => $catalogoTodos,
            'plantoesMes'       => $plantoesMes,
            'anosPhp'           => $anosPhp,
            'delegadosExternos' => $delegadosExternos,
            'escalaVersao'      => $escalaVersao,
            'todasVersoes'      => $todasVersoes,
            'escalaLinhas'      => $escalaLinhas,
            'plantaoLinhas'     => $plantaoLinhas,
            'phpMirrorSummary'  => [
                'total'               => $phpFuncionarios->count(),
                'ativos'              => $phpFuncionarios->count(),
                'concorrem_escala'    => $phpFuncionarios->where('concorre_escala', true)->count(),
                'em_afastamento'      => $phpFuncionarios->filter(fn (RhFuncionario $f): bool => $f->currentAfastamento() !== null)->count(),
            ],
        ]);
    }

    // -------------------------------------------------------
    // Visualização — Plantões
    // -------------------------------------------------------

    public function plantoes(Request $request): View
    {
        $filters = $this->resolveFilters($request);



        // Busca todos os plantões externos do mês/ano diretamente da tabela de plantões
        $atribs = \App\Models\EscalaPlantaoFuncionario::query()
            ->with(['funcionario', 'plantaoExterno'])
            ->whereYear('data', $filters['ano'])
            ->whereMonth('data', $filters['mes'])
            ->orderBy('data')
            ->get();

        $phpDias = collect();
        $legacySnapshot = $this->loadLegacySnapshot($request, $filters['ano'], $filters['mes']);

        $snapshot = $this->emptyLegacySnapshot();
        $snapshot['year'] = $filters['ano'];
        $snapshot['month'] = $filters['mes'];

        $phpHolidays = $this->buildMonthHolidays($filters['ano'], $filters['mes']);
        if (! empty($phpHolidays) || empty($snapshot['holidays'])) {
            $snapshot['holidays'] = $phpHolidays;
        }

        $phpFuncionarios = RhFuncionario::query()
            ->with(['cargo', 'afastamentos' => fn ($q) => $q->orderByDesc('start_date')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $catalogo = EscalaPlantaoExterno::query()->ativos()->get();

        // Plantões do mês agrupados por funcionário
        $plantoesPorFuncionario = [];
        foreach ($atribs as $a) {
            $fId = $a->funcionario_id;
            $plantoesPorFuncionario[$fId][] = $a;
        }

        $snapshot['plantoes'] = $atribs->map(function (\App\Models\EscalaPlantaoFuncionario $item): array {
            return [
                'id' => $item->id,
                'date_label' => $item->data?->format('d/m/Y') ?? 'N/A',
                'funcionario_nome' => $item->funcionario?->short_name ?? $item->funcionario?->name ?? '—',
                'funcionario_setor' => $item->funcionario?->sector ?? 'Setor não informado',
                'plantao_sigla' => $item->plantaoExterno?->sigla ?? '',
                'plantao_nome' => $item->plantaoExterno?->nome ?? '',
                'plantao_unidade' => $item->plantaoExterno?->unidade ?? 'N/A',
                'plantao_regra' => $item->plantaoExterno?->regra ?? 'N/A',
            ];
        })->values()->all();

        if (empty($snapshot['plantoes']) && ! empty($legacySnapshot['plantoes'])) {
            $snapshot['plantoes'] = $legacySnapshot['plantoes'];
        }

        $anosPhp = EscalaDia::query()->selectRaw('DISTINCT ano')->orderByDesc('ano')->pluck('ano')->toArray();
        $snapshot['available_years'] = ! empty($anosPhp)
            ? array_values(array_unique(array_merge($anosPhp, $snapshot['available_years'] ?? [])))
            : ($snapshot['available_years'] ?? [$filters['ano']]);
        $anosDisp = ! empty($anosPhp) ? $anosPhp : ($snapshot['available_years'] ?? [$filters['ano']]);

        if (empty($snapshot['plantao_catalog']) && ! empty($legacySnapshot['plantao_catalog'])) {
            $snapshot['plantao_catalog'] = $legacySnapshot['plantao_catalog'];
        }

        if (empty($snapshot['available_months']) && ! empty($legacySnapshot['available_months'])) {
            $snapshot['available_months'] = $legacySnapshot['available_months'];
        }
        $snapshot['plantao_catalog'] = $catalogo->map(function (EscalaPlantaoExterno $item): array {
            return [
                'sigla' => $item->sigla,
                'nome' => $item->nome,
                'unidade' => $item->unidade,
                'ativo' => (bool) $item->is_active,
            ];
        })->values()->all();
        $snapshot['version'] = $phpDias->max('versao') ?: '—';
        $snapshot['source_name'] = 'Base PHP';
        $snapshot['summary'] = [
            'dias_total' => $phpDias->count(),
            'dias_com_escrivao' => $phpDias->whereNotNull('escrivao')->whereNotIn('escrivao', [''])->count(),
            'dias_com_delegada' => $phpDias->whereNotNull('delegada')->whereNotIn('delegada', [''])->count(),
            'dias_com_operacional' => $phpDias->whereNotNull('operacional')->whereNotIn('operacional', [''])->count(),
            'dias_com_plantao_externo' => $phpDias->whereNotNull('plantao_externo')->whereNotIn('plantao_externo', [''])->count(),
            'plantoes_atribuicoes' => count($snapshot['plantoes']),
            'plantoes_catalogo_ativos' => $catalogo->count(),
            'funcionarios_total' => $phpFuncionarios->count(),
            'funcionarios_ativos' => $phpFuncionarios->count(),
            'funcionarios_concorrem' => $phpFuncionarios->where('concorre_escala', true)->count(),
            'funcionarios_em_afastamento' => $phpFuncionarios->filter(fn (RhFuncionario $f): bool => $f->currentAfastamento() !== null)->count(),
        ];

        return view('escalas.plantoes', [
            'filters'                 => $filters,
            'snapshot'                => $snapshot,
            'phpDias'                 => $phpDias,
            'phpFuncionarios'         => $phpFuncionarios,
            'phpAtivos'               => $phpFuncionarios->where('is_active', true)->values(),
            'catalogo'                => $catalogo,
            'plantoesPorFuncionario'  => $plantoesPorFuncionario,
            'anosPhp'                 => $anosDisp,
            'phpMirrorSummary'        => [
                'total'            => $phpFuncionarios->count(),
                'ativos'           => $phpFuncionarios->count(),
                'concorrem_escala' => $phpFuncionarios->where('concorre_escala', true)->count(),
                'em_afastamento'   => $phpFuncionarios->filter(fn (RhFuncionario $f): bool => $f->currentAfastamento() !== null)->count(),
            ],
        ]);
    }

    // -------------------------------------------------------
    // CRUD — Dia da escala
    // -------------------------------------------------------

    /** Cria ou atualiza um dia na escala do PHP. */
    public function storeDia(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'data'           => ['required', 'date'],
            'mes'            => ['required', 'integer', 'min:1', 'max:12'],
            'ano'            => ['required', 'integer', 'min:2020', 'max:2100'],
            'versao'         => ['required', 'integer', 'min:1', 'max:99'],
            'escrivao'       => ['nullable', 'string', 'max:100'],
            'operacional'    => ['nullable', 'string', 'max:100'],
            'fechar_nome'    => ['nullable', 'string', 'max:100'],
            'delegada'       => ['nullable', 'string', 'max:100'],
            'plantao_externo'=> ['nullable', 'string', 'max:500'],
        ]);

        $userId = $request->user()->id;

        $dia = EscalaDia::query()->firstOrNew([
            'data'   => $data['data'],
            'versao' => $data['versao'],
        ]);

        $isNew = ! $dia->exists;

        $dia->fill(array_merge($data, [
            'created_by' => $isNew ? $userId : $dia->created_by,
            'updated_by' => $userId,
        ]));
        $dia->save();

        AuditLogger::log(
            'escalas', $isNew ? 'escala_dia_criado' : 'escala_dia_atualizado',
            'EscalaDia', $dia->id,
            "Dia {$data['data']} versao {$data['versao']} — {$data['ano']}/{$data['mes']}"
        );

        return back()->with('status-success', 'Dia salvo com sucesso.');
    }

    /** Exclui (soft) um dia da escala. */
    public function destroyDia(EscalaDia $dia, Request $request): RedirectResponse
    {
        $dia->update(['updated_by' => $request->user()->id]);
        $dia->delete();

        AuditLogger::log('escalas', 'escala_dia_excluido', 'EscalaDia', $dia->id,
            "Dia {$dia->data->toDateString()} (versao {$dia->versao}) excluído");

        return back()->with('status-success', 'Dia removido da escala.');
    }

    // -------------------------------------------------------
    // CRUD — Plantão de funcionário
    // -------------------------------------------------------

    public function storePlantaoFuncionario(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'funcionario_id'     => ['required', 'uuid', 'exists:rh_funcionarios,id'],
            'plantao_externo_id' => ['required', 'integer', 'exists:escalas_plantoes_externos,id'],
            'data'               => ['nullable', 'date', 'required_without:datas'],
            'datas'              => ['nullable', 'array', 'required_without:data'],
            'datas.*'            => ['required', 'date'],
        ]);

        $userId = $request->user()->id;
        $datas = collect($data['datas'] ?? [$data['data'] ?? null])
            ->filter()
            ->map(fn (string $date): string => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        $criados = 0;
        $ignorados = 0;

        foreach ($datas as $dataPlantao) {
            $existing = EscalaPlantaoFuncionario::query()
                ->where('funcionario_id', $data['funcionario_id'])
                ->where('plantao_externo_id', $data['plantao_externo_id'])
                ->whereDate('data', $dataPlantao)
                ->first();

            if ($existing) {
                $ignorados++;
                continue;
            }

            $p = EscalaPlantaoFuncionario::query()->create([
                'funcionario_id' => $data['funcionario_id'],
                'plantao_externo_id' => $data['plantao_externo_id'],
                'data' => $dataPlantao,
                'created_by' => $userId,
            ]);

            $this->refreshPlantaoTextoByDate($dataPlantao, $userId);

            AuditLogger::log('escalas', 'plantao_func_criado', 'EscalaPlantaoFuncionario', (string) $p->id,
                "Plantão id={$p->plantao_externo_id} para funcionario {$data['funcionario_id']} em {$dataPlantao}");

            $criados++;
        }

        if ($criados === 0 && $ignorados > 0) {
            return back()->with('status-warning', 'Os plantões selecionados já estavam registrados para este funcionário.');
        }

        $msg = $criados === 1 ? '1 plantão registrado.' : "{$criados} plantões registrados.";
        if ($ignorados > 0) {
            $msg .= " {$ignorados} duplicado(s) ignorado(s).";
        }

        return back()->with('status-success', $msg);
    }

    public function destroyPlantaoFuncionario(EscalaPlantaoFuncionario $plantao, Request $request): RedirectResponse
    {
        $userId = $request->user()->id;
        $dataRef = $plantao->data;
        $desc   = "func={$plantao->funcionario_id} data={$plantao->data->toDateString()}";
        $plantao->delete();

        $this->refreshPlantaoTextoByDate($dataRef->toDateString(), $userId);

        AuditLogger::log('escalas', 'plantao_func_excluido', 'EscalaPlantaoFuncionario', (string) $plantao->id, $desc);

        return back()->with('status-success', 'Plantão removido.');
    }

    // -------------------------------------------------------
    // CRUD — Catálogo de plantões externos
    // -------------------------------------------------------

    public function storePlantaoExterno(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome'      => ['required', 'string', 'max:80'],
            'sigla'     => ['nullable', 'string', 'max:20', 'unique:escalas_plantoes_externos,sigla'],
            'unidade'   => ['nullable', 'string', 'max:80'],
            'regra'     => ['nullable', 'in:AMBOS,MESMO_DIA,DIA_SEGUINTE'],
            'observacao'=> ['nullable', 'string', 'max:500'],
        ]);

        $p = EscalaPlantaoExterno::query()->create(array_merge($data, ['is_active' => true]));

        AuditLogger::log('escalas', 'plantao_externo_criado', 'EscalaPlantaoExterno', (string) $p->id, $p->nome);

        return back()->with('status-success', "Plantão \"{$p->nome}\" criado.");
    }

    public function togglePlantaoExterno(EscalaPlantaoExterno $plantao, Request $request): RedirectResponse
    {
        $plantao->update(['is_active' => ! $plantao->is_active]);
        $estado = $plantao->is_active ? 'ativado' : 'desativado';

        AuditLogger::log('escalas', 'plantao_externo_toggle', 'EscalaPlantaoExterno', (string) $plantao->id, "'{$plantao->sigla}' {$estado}");

        return back()->with('status-success', "Plantão \"{$plantao->nome}\" {$estado}.");
    }

    public function updatePlantaoExterno(Request $request, EscalaPlantaoExterno $plantao): RedirectResponse
    {
        $data = $request->validate([
            'nome'       => ['required', 'string', 'max:80'],
            'sigla'      => ['nullable', 'string', 'max:20', "unique:escalas_plantoes_externos,sigla,{$plantao->id}"],
            'unidade'    => ['nullable', 'string', 'max:80'],
            'regra'      => ['nullable', 'in:AMBOS,MESMO_DIA,DIA_SEGUINTE'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ]);

        $plantao->update($data);

        $datasAfetadas = EscalaPlantaoFuncionario::query()
            ->where('plantao_externo_id', $plantao->id)
            ->selectRaw('DISTINCT data')
            ->pluck('data');

        foreach ($datasAfetadas as $dataAfetada) {
            $this->refreshPlantaoTextoByDate((string) $dataAfetada, $request->user()->id);
        }

        AuditLogger::log('escalas', 'plantao_externo_editado', 'EscalaPlantaoExterno', (string) $plantao->id,
            "'{$plantao->sigla}' atualizado");

        return back()->with('status-success', "Plantão \"{$plantao->nome}\" atualizado.");
    }

    // -------------------------------------------------------
    // Sync do legado
    // -------------------------------------------------------

    public function syncLegado(Request $request, LegacyEscalasSyncService $sync): RedirectResponse
    {
        $userId = $request->user()->id;

        try {
            $result = $sync->syncAll($userId);
        } catch (\Throwable $e) {
            return back()->with('status-error', 'Falha na sincronização: ' . $e->getMessage());
        }

        $d  = $result['dias'];
        $pe = $result['plantoes_externos'];
        $pf = $result['plantoes_funcionarios'];

        $msg = sprintf(
            'Sync concluído — Dias: +%d / ~%d / =%d | Plantões externos: +%d / =%d | Atribuições: +%d / =%d',
            $d['inserted'], $d['updated'], $d['skipped'],
            $pe['inserted'] + $pe['updated'], $pe['skipped'],
            $pf['inserted'], $pf['skipped']
        );

        AuditLogger::log('escalas', 'sync_legado', 'Sistema', $userId, $msg);

        if (! empty($result['errors'])) {
            $msg .= ' | Avisos: ' . implode('; ', array_slice($result['errors'], 0, 3));
        }

        return back()->with('status-success', $msg);
    }

    // -------------------------------------------------------
    // Impressão A4 institucional
    // -------------------------------------------------------

    public function printView(Request $request): View
    {
        $filters   = $this->resolveFilters($request);
        $phpDias   = EscalaDia::diasDoMes($filters['ano'], $filters['mes']);
        $temPlantoesMes = $this->temPlantoesNoMes($filters['ano'], $filters['mes']);
        if ($phpDias->isEmpty() && ! $temPlantoesMes) {
            $this->tryAutoSyncLegacy($request->user()?->id);
            $phpDias = EscalaDia::diasDoMes($filters['ano'], $filters['mes']);
        }
        $phpVersao = $phpDias->max('versao');
        $this->marcarConferenciaSePossivel($filters['ano'], $filters['mes'], $phpDias);
        $primeiroDia = Carbon::create($filters['ano'], $filters['mes'], 1);
        $ultimoDia = $primeiroDia->copy()->endOfMonth();

        $snapshot = $phpDias->isEmpty()
            ? $this->loadLegacySnapshot($request, $filters['ano'], $filters['mes'])
            : $this->emptyLegacySnapshot();
        $snapshot['year'] = $filters['ano'];
        $snapshot['month'] = $filters['mes'];
        $feriados = $this->buildMonthHolidays($filters['ano'], $filters['mes']);
        if (! empty($feriados) || empty($snapshot['holidays'])) {
            $snapshot['holidays'] = $feriados;
        } else {
            $feriados = $snapshot['holidays'];
        }

        // Todos os ativos (inclui quem não concorre) para exibir afastamentos
        $phpFuncionarios = RhFuncionario::query()
            ->with(['cargo', 'afastamentos' => fn ($q) => $q->where('is_active', true)->orderBy('start_date')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Plantões do mês por data (inclui feriados/finais de semana sem linha de escala)
        $plantoesMes = $this->carregarPlantoesMes($filters['ano'], $filters['mes']);

        return view('escalas.print', [
            'filters'         => $filters,
            'snapshot'        => $snapshot,
            'phpDias'         => $phpDias,
            'phpVersao'       => $phpVersao,
            'phpFuncionarios' => $phpFuncionarios,
            'feriados'        => $feriados,
            'plantoesMes'     => $plantoesMes,
            'escalaVersao'    => $phpDias->isNotEmpty()
                ? EscalaVersao::maisRecente($filters['ano'], $filters['mes'])
                : null,
            'substituicoesDdm' => $this->getSubstituicoesDdm($filters),
            'delegadosExternos' => EscalaDelegadoExterno::ativos()->get(),
        ]);
    }

    public function proofView(Request $request): View
    {
        $filters   = $this->resolveFilters($request);
        $phpDias   = EscalaDia::diasDoMes($filters['ano'], $filters['mes']);
        $temPlantoesMes = $this->temPlantoesNoMes($filters['ano'], $filters['mes']);
        if ($phpDias->isEmpty() && ! $temPlantoesMes) {
            $this->tryAutoSyncLegacy($request->user()?->id);
            $phpDias = EscalaDia::diasDoMes($filters['ano'], $filters['mes']);
        }
        $phpVersao = $phpDias->max('versao');
        $this->marcarConferenciaSePossivel($filters['ano'], $filters['mes'], $phpDias);
        $primeiroDia = Carbon::create($filters['ano'], $filters['mes'], 1);
        $ultimoDia = $primeiroDia->copy()->endOfMonth();

        $snapshot = $phpDias->isEmpty()
            ? $this->loadLegacySnapshot($request, $filters['ano'], $filters['mes'])
            : $this->emptyLegacySnapshot();
        $snapshot['year'] = $filters['ano'];
        $snapshot['month'] = $filters['mes'];
        $feriados = $this->buildMonthHolidays($filters['ano'], $filters['mes']);
        if (! empty($feriados) || empty($snapshot['holidays'])) {
            $snapshot['holidays'] = $feriados;
        } else {
            $feriados = $snapshot['holidays'];
        }

        // Todos os ativos (inclui quem não concorre) para exibir afastamentos
        $phpFuncionarios = RhFuncionario::query()
            ->with(['cargo', 'afastamentos' => fn ($q) => $q->where('is_active', true)->orderBy('start_date')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Plantões do mês por data (inclui feriados/finais de semana sem linha de escala)
        $plantoesMes = $this->carregarPlantoesMes($filters['ano'], $filters['mes']);

        $data = [
            'filters'         => $filters,
            'snapshot'        => $snapshot,
            'phpDias'         => $phpDias,
            'phpVersao'       => $phpVersao,
            'phpFuncionarios' => $phpFuncionarios,
            'feriados'        => $feriados,
            'plantoesMes'     => $plantoesMes,
            'escalaVersao'    => $phpDias->isNotEmpty()
                ? EscalaVersao::maisRecente($filters['ano'], $filters['mes'])
                : null,
            'substituicoesDdm' => $this->getSubstituicoesDdm($filters),
            'delegadosExternos' => EscalaDelegadoExterno::ativos()->get(),
        ];

        $data['previewUrl'] = route('escalas.print', array_merge($filters, ['preview' => 1]));
        $data['proofChecks'] = $this->buildProofChecks();
        $data['referenceRow'] = $this->buildProofReferenceRow($data);

        return view('escalas.prova', $data);
    }

    // -------------------------------------------------------
    // CRUD — Delegado do dia (atribuição rápida inline)
    // -------------------------------------------------------

    /** Atribui (ou limpa) o delegado de um dia da escala. */
    public function updateDiaDelegado(Request $request, EscalaDia $dia): RedirectResponse
    {
        $data = $request->validate([
            'delegada' => ['nullable', 'string', 'max:100'],
        ]);

        $nomeAnterior = $dia->delegada ?? '';
        $nomeNovo     = trim((string) ($data['delegada'] ?? ''));

        $dia->update(['delegada' => $nomeNovo ?: null]);

        AuditLogger::log(
            'escalas',
            'delegado_atribuido',
            'EscalaDia',
            (string) $dia->id,
            "Delegado alterado de '{$nomeAnterior}' para '{$nomeNovo}' no dia {$dia->data->toDateString()}"
        );

        $msg = $nomeNovo
            ? "Delegado(a) \"{$nomeNovo}\" atribuído(a) ao dia {$dia->data->format('d/m/Y')}."
            : "Delegado(a) removido(a) do dia {$dia->data->format('d/m/Y')}.";

        return back()->with('status-success', $msg);
    }

    // -------------------------------------------------------
    // Geração automática da escala provisória
    // -------------------------------------------------------

    /**
     * Gera a escala provisória do mês aplicando:
     *   - afastamentos dos funcionários
     *   - regras (MESMO_DIA / DIA_SEGUINTE / AMBOS) dos plantões externos
     *   - rotação circular por cargo para cada slot (delegada, escrivão, operacional, fechar)
     *
     * Disponível apenas quando não há escala PHP provisória para o período.
     */
    public function gerarProvisoria(Request $request, GeradorEscalaMensalService $gerador): RedirectResponse
    {
        // Validação explícita dos plantões externos antes de gerar
        try {
            $validador = new \App\Services\Escalas\ValidadorPlantoesExternosService();
            $data = $request->validate([
                'ano' => ['required', 'integer', 'min:2020', 'max:2100'],
                'mes' => ['required', 'integer', 'min:1', 'max:12'],
            ]);
            $ano = (int) $data['ano'];
            $mes = (int) $data['mes'];
            $conflitos = $validador->validar($ano, $mes);
            if (!empty($conflitos)) {
                $msgs = collect($conflitos)->map(function($c) {
                    return ($c['data'] ?? '') . ' — ' . ($c['funcionario'] ?? '') . ': ' . ($c['msg'] ?? '');
                })->implode("\n");
                return redirect()
                    ->route('escalas.plantoes', ['ano' => $ano, 'mes' => $mes])
                    ->with('status-error', "Conflitos nos plantões externos:\n" . $msgs);
            }
        } catch (\Throwable $e) {
            return redirect()
                ->route('escalas.plantoes', ['ano' => $ano ?? null, 'mes' => $mes ?? null])
                ->with('status-error', 'Erro ao validar plantões externos: ' . $e->getMessage());
        }

        try {
            SqliteDatabaseBackup::backup("escala-gerar-{$ano}-{$mes}");

            $resultado = $gerador->gerar($ano, $mes, $request->user()->id);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('escalas.index', ['ano' => $ano, 'mes' => $mes])
                ->with('status-warning', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()
                ->route('escalas.index', ['ano' => $ano, 'mes' => $mes])
                ->with('status-error', 'Erro ao gerar escala: ' . $e->getMessage());
        }

        $nomeMes = Carbon::create()->month($mes)->locale('pt_BR')->isoFormat('MMMM');
        $msg = sprintf(
            'Escala provisória de %s/%d gerada (v%d): %d dias úteis criados.',
            ucfirst($nomeMes),
            $ano,
            $resultado['versao'],
            $resultado['dias_criados']
        );

        if (! empty($resultado['alertas'])) {
            $msg .= ' | Atenção: ' . implode('; ', array_slice($resultado['alertas'], 0, 5));
            if (count($resultado['alertas']) > 5) {
                $msg .= ' (e mais ' . (count($resultado['alertas']) - 5) . ' alertas)';
            }
        }


        return redirect()
            ->route('escalas.index', ['ano' => $ano, 'mes' => $mes])
            ->with('status-success', $msg);
    }

    // -------------------------------------------------------
    // Ciclo de vida da versão — Provisória → Definitiva
    // -------------------------------------------------------

    /**
     * Grava a versão atual como DEFINITIVA.
     *
     * - Marca o cabeçalho da versão como "definitiva".
     * - Seta is_fechada = true em todos os dias da versão.
     * - NÃO cria nova versão automaticamente: o usuário decide quando emenda.
     */
    public function fecharVersao(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ano'  => ['required', 'integer', 'min:2020', 'max:2100'],
            'mes'  => ['required', 'integer', 'min:1', 'max:12'],
            'obs'  => ['nullable', 'string', 'max:500'],
        ]);

        $userId  = $request->user()->id;
        $versao  = EscalaDia::query()
            ->where('ano', $data['ano'])
            ->where('mes', $data['mes'])
            ->max('versao');

        if (! $versao) {
            return back()->with('status-error', 'Nenhuma escala encontrada para o período.');
        }

        $header = EscalaVersao::ativaOuCriar($data['ano'], $data['mes'], $versao, $userId);

        if ($header->status === 'definitiva') {
            return back()->with('status-warning', 'Esta versão já é definitiva. Crie uma nova versão para emendas.');
        }

        // TRAVA DE CONFERÊNCIA OBRIGATÓRIA
        if (empty($header->conferida_em)) {
            return back()->with('status-error', 'É obrigatório realizar a conferência da escala (visualização tela ou PDF) antes de aprovar.');
        }

        SqliteDatabaseBackup::backup("escala-fechar-{$data['ano']}-{$data['mes']}");

        $header->update([
            'status'      => 'definitiva',
            'obs'         => $data['obs'] ?? null,
            'fechada_por' => $userId,
            'fechada_em'  => now(),
        ]);

        // Congela todos os dias desta versão
        EscalaDia::query()
            ->where('ano', $data['ano'])
            ->where('mes', $data['mes'])
            ->where('versao', $versao)
            ->update(['is_fechada' => true, 'updated_by' => $userId]);

        $nomeMes = Carbon::create()->month((int) $data['mes'])->locale('pt_BR')->isoFormat('MMMM');
        $msg = "Escala de {$nomeMes}/{$data['ano']} v{$versao} gravada como DEFINITIVA.";

        AuditLogger::log('escalas', 'versao_fechada', 'EscalaVersao', (string) $header->id, $msg);

        return back()->with('status-success', $msg);
    }

    /**
     * Cria uma nova versão (emenda parcial).
     *
     * - Copia todos os dias da última versão definitiva para versao+1.
     * - Dias com data < hoje → is_fechada = true (somente leitura).
     * - Dias com data >= hoje → is_fechada = false (editáveis).
     * - Cria cabeçalho provisório para a nova versão.
     */
    public function novaVersao(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ano' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $userId = $request->user()->id;
        $hoje   = Carbon::today();

        $ultimaVersao = EscalaDia::query()
            ->where('ano', $data['ano'])
            ->where('mes', $data['mes'])
            ->max('versao');

        if (! $ultimaVersao) {
            return back()->with('status-error', 'Nenhuma escala encontrada para o período.');
        }

        $header = EscalaVersao::maisRecente($data['ano'], $data['mes']);
        if (! $header || $header->status !== 'definitiva') {
            return back()->with('status-warning', 'Grave a escala como DEFINITIVA antes de criar uma nova versão.');
        }

        $novaVersao = $ultimaVersao + 1;

        // Copia os dias
        $diasOriginais = EscalaDia::query()
            ->where('ano', $data['ano'])
            ->where('mes', $data['mes'])
            ->where('versao', $ultimaVersao)
            ->orderBy('data')
            ->get();

        SqliteDatabaseBackup::backup("escala-nova-versao-{$data['ano']}-{$data['mes']}");

        foreach ($diasOriginais as $diaOrig) {
            $ehPassado = Carbon::parse($diaOrig->data)->lt($hoje);
            EscalaDia::query()->create([
                'data'             => $diaOrig->data,
                'mes'              => $diaOrig->mes,
                'ano'              => $diaOrig->ano,
                'versao'           => $novaVersao,
                'is_fechada'       => $ehPassado,   // passado = congelado
                'escrivao'         => $diaOrig->escrivao,
                'operacional'      => $diaOrig->operacional,
                'fechar_nome'      => $diaOrig->fechar_nome,
                'delegada'         => $diaOrig->delegada,
                'plantao_externo'  => $diaOrig->plantao_externo,
                'created_by'       => $userId,
                'updated_by'       => $userId,
            ]);
        }

        // Cabeçalho provisório da nova versão
        EscalaVersao::create([
            'ano'        => $data['ano'],
            'mes'        => $data['mes'],
            'versao'     => $novaVersao,
            'status'     => 'provisoria',
            'created_by' => $userId,
        ]);

        $nomeMes = Carbon::create()->month((int) $data['mes'])->locale('pt_BR')->isoFormat('MMMM');
        $msg = "Nova versão v{$novaVersao} criada para {$nomeMes}/{$data['ano']}. Dias anteriores a hoje estão travados.";

        AuditLogger::log('escalas', 'nova_versao_criada', 'EscalaVersao', "{$data['ano']}-{$data['mes']}-v{$novaVersao}", $msg);

        return redirect()
            ->route('escalas.index', $data)
            ->with('status-success', $msg);
    }

    /**
     * Atualiza um campo de texto de um dia da escala (escrivao, operacional, fechar_nome, delegada).
     *
     * Proteção: dia deve estar com is_fechada = false.
     */
    public function updateDia(Request $request, EscalaDia $dia): RedirectResponse
    {
        $allowed = ['escrivao', 'operacional', 'fechar_nome', 'delegada'];

        $data = $request->validate([
            'campo' => ['required', 'string', 'in:' . implode(',', $allowed)],
            'valor' => ['nullable', 'string', 'max:100'],
        ]);

        if ($dia->is_fechada) {
            return back()->with('status-error', "O dia {$dia->data->format('d/m/Y')} está travado (versão definitiva/passado).");
        }

        $campo      = $data['campo'];
        $valorNovo  = trim((string) ($data['valor'] ?? '')) ?: null;
        $valorAnt   = $dia->{$campo};

        if ($campo === 'fechar_nome' && $valorNovo !== null) {
            $fecharPermitidos = array_values(array_filter([
                trim((string) ($dia->escrivao ?? '')),
                trim((string) ($dia->operacional ?? '')),
            ]));

            if (! in_array($valorNovo, $fecharPermitidos, true)) {
                return back()->with('status-error', "O campo Fechar deve ser um dos dois funcionarios escalados no dia {$dia->data->format('d/m/Y')}.");
            }
        }

        $dia->update([$campo => $valorNovo, 'updated_by' => $request->user()->id]);
        // Auditoria explícita
        AuditLogger::log(
            'escalas',
            'dia_campo_alterado',
            'EscalaDia',
            (string) $dia->id,
            "Campo '{$campo}': '{$valorAnt}' → '{$valorNovo}' — dia {$dia->data->toDateString()} (versao: {$dia->versao})"
        );
        // Confirma persistência
        $dia->refresh();
        if ($dia->{$campo} !== $valorNovo) {
            return back()->with('status-error', "Falha ao salvar alteração. Tente novamente ou contate o suporte.");
        }
        return back()->with('status-success', "{$campo} atualizado para o dia {$dia->data->format('d/m/Y')}.");
    }

    // -------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------

    private function resolveFilters(Request $request): array
    {
        $data = $request->validate([
            'ano'    => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'mes'    => ['nullable', 'integer', 'min:1', 'max:12'],
            'versao' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        return [
            'ano'    => (int) ($data['ano'] ?? now()->year),
            'mes'    => (int) ($data['mes'] ?? now()->month),
            'versao' => isset($data['versao']) ? (int) $data['versao'] : null,
        ];
    }

    private function emptyLegacySnapshot(): array
    {
        return [
            'source_name' => 'N/D',
            'year' => now()->year,
            'month' => now()->month,
            'summary' => [
                'dias_total' => 0,
                'dias_com_escrivao' => 0,
                'dias_com_delegada' => 0,
                'dias_com_operacional' => 0,
                'dias_com_plantao_externo' => 0,
                'plantoes_atribuicoes' => 0,
                'plantoes_catalogo_ativos' => 0,
                'funcionarios_total' => 0,
                'funcionarios_ativos' => 0,
                'funcionarios_concorrem' => 0,
                'funcionarios_em_afastamento' => 0,
            ],
            'funcionarios' => [],
            'plantoes' => [],
            'plantao_catalog' => [],
            'holidays' => [],
            'warnings' => [],
            'available_years' => [now()->year],
            'available_months' => range(1, 12),
            'scale_rows' => [],
            'version' => '–',
        ];
    }

    private function loadLegacySnapshot(Request $request, int $ano, int $mes): array
    {
        try {
            return app(LegacyEscalasReader::class)->snapshotForMonth($request->user(), $ano, $mes);
        } catch (\Throwable $e) {
            $snapshot = $this->emptyLegacySnapshot();
            $snapshot['year'] = $ano;
            $snapshot['month'] = $mes;
            $snapshot['warnings'][] = 'Nao foi possivel consultar o espelho legado da escala.';

            return $snapshot;
        }
    }

    private function buildMonthHolidays(int $ano, int $mes): array
    {
        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $fim = $inicio->copy()->endOfMonth();

        return RhHoliday::query()
            ->where('is_active', true)
            ->whereDate('holiday_date', '>=', $inicio->toDateString())
            ->whereDate('holiday_date', '<=', $fim->toDateString())
            ->orderBy('holiday_date')
            ->get()
            ->map(function (RhHoliday $holiday): array {
                $data = $holiday->holiday_date?->toDateString() ?? '';

                return [
                    'date' => $data,
                    'date_label' => $holiday->holiday_date?->format('d/m') ?? '',
                    'descricao' => $holiday->name,
                    'tipo' => $holiday->scope,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Divide o texto de plantões externos em linhas legíveis.
     *
     * Aceita tanto a forma antiga com vírgulas quanto a forma nova com quebras de linha.
     */
    private function splitPlantaoTexto(string $texto): array
    {
        $texto = trim($texto);

        if ($texto === '') {
            return [];
        }

        $partes = preg_split('/\s*(?:\r\n|\r|\n|,\s*)\s*/u', $texto, -1, PREG_SPLIT_NO_EMPTY);

        if ($partes === false) {
            return [$texto];
        }

        return array_values(array_filter(array_map('trim', $partes), static fn (string $item): bool => $item !== ''));
    }

    private function refreshPlantaoTextoByDate(string $data, string $userId): void
    {
        $dataCarbon = Carbon::parse($data);

        $atribs = EscalaPlantaoFuncionario::query()
            ->with(['funcionario', 'plantaoExterno'])
            ->whereDate('data', $dataCarbon->toDateString())
            ->orderBy('funcionario_id')
            ->get();

        $partes = [];
        foreach ($atribs as $atrib) {
            $nome = trim((string) ($atrib->funcionario?->short_name ?: $atrib->funcionario?->name ?: ''));
            $sigla = trim((string) ($atrib->plantaoExterno?->sigla ?: $atrib->plantaoExterno?->nome ?: ''));

            if ($nome === '' && $sigla === '') {
                continue;
            }

            $partes[] = $sigla === '' ? $nome : "{$nome} ({$sigla})";
        }

        sort($partes, SORT_NATURAL | SORT_FLAG_CASE);
        $texto = implode(PHP_EOL, array_values(array_unique($partes)));

        EscalaDia::query()
            ->whereDate('data', $dataCarbon->toDateString())
            ->update([
                'plantao_externo' => $texto !== '' ? $texto : null,
                'updated_by' => $userId,
            ]);
    }

    private function tryAutoSyncLegacy(?string $userId): void
    {
        if (! config('grom_legacy.enabled') || empty($userId)) {
            return;
        }

        try {
            app(LegacyEscalasSyncService::class)->syncAll($userId);
        } catch (\Throwable) {
            // Mantém leitura por fallback legado quando sincronização não estiver disponível.
        }
    }

    private function marcarConferenciaSePossivel(int $ano, int $mes, $phpDias): void
    {
        if ($phpDias->isEmpty()) {
            return;
        }

        $escalaVersao = EscalaVersao::maisRecente($ano, $mes);

        if ($escalaVersao && empty($escalaVersao->conferida_em)) {
            $escalaVersao->marcarConferida();
        }
    }

    private function temPlantoesNoMes(int $ano, int $mes): bool
    {
        return DB::table('escalas_plantoes_funcionarios')
            ->where('data', 'like', sprintf('%04d-%02d-%%', $ano, $mes))
            ->exists();
    }

    private function carregarPlantoesMes(int $ano, int $mes): array
    {
        $ids = DB::table('escalas_plantoes_funcionarios')
            ->where('data', 'like', sprintf('%04d-%02d-%%', $ano, $mes))
            ->orderBy('data')
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            return [];
        }

        $plantoesMes = [];
        $atribs = EscalaPlantaoFuncionario::query()
            ->with(['funcionario.cargo', 'plantaoExterno'])
            ->whereIn('id', $ids)
            ->orderBy('data')
            ->get();

        foreach ($atribs as $a) {
            $plantoesMes[$a->data->toDateString()][] = $a;
        }

        return $plantoesMes;
    }

    private function formatarPlantaoTexto(array $atribs): string
    {
        $partes = [];

        foreach ($atribs as $atribuicao) {
            $funcionario = $atribuicao->funcionario;
            $plantao = $atribuicao->plantaoExterno;

            $nome = trim((string) ($funcionario?->short_name ?: $funcionario?->name ?: ''));
            $sigla = trim((string) ($plantao?->sigla ?: $plantao?->nome ?: ''));

            if ($nome === '' && $sigla === '') {
                continue;
            }

            $partes[] = $sigla === '' ? $nome : "{$nome} ({$sigla})";
        }

        $partes = array_values(array_unique(array_filter($partes)));
        sort($partes, SORT_NATURAL | SORT_FLAG_CASE);

        return implode(PHP_EOL, $partes);
    }

    private function incluirLinhasDePlantoesSemExpediente($escalaLinhas, array $plantoesMes, $feriadosPorData)
    {
        if (empty($plantoesMes)) {
            return $escalaLinhas;
        }

        $datasComLinha = $escalaLinhas->pluck('date')->filter()->flip();

        foreach ($plantoesMes as $dataRef => $atribs) {
            if ($datasComLinha->has($dataRef)) {
                continue;
            }

            $plantaoTexto = $this->formatarPlantaoTexto($atribs);

            if ($plantaoTexto === '') {
                continue;
            }

            $dtCarbon = Carbon::parse($dataRef);
            $displayMode = $feriadosPorData->has($dataRef)
                ? 'holiday'
                : ($dtCarbon->isWeekend() ? 'weekend' : 'normal');

            $escalaLinhas->push([
                'source' => 'php',
                'id' => null,
                'date_label' => $dtCarbon->format('d/m'),
                'date' => $dataRef,
                'day_label' => $dtCarbon->locale('pt_BR')->isoFormat('ddd'),
                'display_mode' => $displayMode,
                'is_weekend' => $dtCarbon->isWeekend(),
                'is_fechada' => true,
                'escrivao' => '',
                'operacional' => '',
                'fechar' => '',
                'delegada' => '',
                'plantao_externo' => $plantaoTexto,
                'plantao_items' => $this->splitPlantaoTexto($plantaoTexto),
            ]);
        }

        return $escalaLinhas->sortBy('date')->values();
    }

    private function buildProofChecks(): array
    {
        return [
            [
                'label' => 'Timbrado consolidado',
                'value' => 'OK',
                'detail' => 'Brasão, logo e estrutura institucional seguem o componente padrão.',
            ],
            [
                'label' => 'Formato A4',
                'value' => 'OK',
                'detail' => 'A pré-visualização usa a mesma composição prevista para impressão.',
            ],
            [
                'label' => 'Rodapé consolidado',
                'value' => 'Cartório Central - Gerenciamento',
                'detail' => 'Rodapé institucional único para todas as saídas do sistema.',
            ],
        ];
    }

    private function buildProofReferenceRow(array $data): ?array
    {
        if (($data['phpDias'] ?? collect())->isEmpty()) {
            return null;
        }

        $fallback = null;

        foreach ($data['phpDias'] as $dia) {
            $plantaoTexto = trim((string) ($dia->plantao_externo ?? ''));
            $items = $this->splitPlantaoTexto($plantaoTexto);

            if ($plantaoTexto === '') {
                continue;
            }

            $row = [
                'date' => Carbon::parse($dia->data)->format('d/m/Y'),
                'day' => Carbon::parse($dia->data)->locale('pt_BR')->isoFormat('dddd'),
                'plantao_text' => implode(', ', $items),
                'items' => $items,
            ];

            if (count($items) >= 2) {
                return $row;
            }

            $fallback ??= $row;
        }

        return $fallback;
    }
}
