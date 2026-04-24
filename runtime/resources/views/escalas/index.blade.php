@extends('layouts.app')

@section('title', 'Escala Mensal | Grom.Seg')

@section('content')
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        .scale-row-holiday td {
            background: #f7f1dd;
        }

        .scale-holiday-marker {
            background: #f4d03f;
            color: #1f1f1f;
            font-weight: 800;
            letter-spacing: 0.08em;
        }

        .plantao-cell {
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        @media print {
            body {
                background: #fff !important;
            }

            .topbar,
            .no-print,
            .section-head .actions,
            .alert,
            .print-suppress {
                display: none !important;
            }

            .page-card {
                border: 0;
                box-shadow: none;
                padding: 0;
                background: #fff;
            }

            .shell {
                max-width: none;
                padding: 0;
            }

            .print-header {
                display: block !important;
                margin-bottom: 10px;
            }

            .print-header h1 {
                margin: 0;
                font-size: 18px;
            }

            .print-header p {
                margin: 4px 0 0;
                font-size: 11px;
                color: #444;
            }

            .cards {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 10px;
            }

            .cards .card {
                page-break-inside: avoid;
                box-shadow: none;
            }

            .card {
                box-shadow: none;
            }

            details > summary {
                list-style: none;
            }

            details > summary::-webkit-details-marker {
                display: none;
            }

            details[open] > summary + * {
                break-inside: avoid;
            }

            .print-collapse {
                display: block !important;
            }

            .print-collapse > summary {
                display: none;
            }

            table {
                font-size: 10px;
            }

            th, td {
                padding: 6px 8px;
            }
        }
    </style>

    <div class="print-header no-print" style="display: none;">
        <h1>Escala Mensal</h1>
        <p>{{ $filters['mes'] }}/{{ $filters['ano'] }} — DDM / Cartório Central</p>
    </div>

    <div class="section-head">
        <div>
            <h1>Escala Mensal</h1>
            <p class="muted" style="margin: 6px 0 0;">
                @if ($phpDias->isNotEmpty())
                    Fonte: <strong>base PHP</strong> — versão {{ $phpVersao }}
                    @if ($escalaVersao)
                        —
                        @if ($escalaVersao->eh_provisoria)
                            <span style="color:#c07800; font-weight:600;">PROVISÓRIA</span>
                            (em auditoria)
                        @else
                            <span style="color:#1a7a3c; font-weight:600;">DEFINITIVA</span>
                            @if ($escalaVersao->fechada_em)
                                em {{ $escalaVersao->fechada_em->format('d/m/Y H:i') }}
                            @endif
                        @endif
                    @endif
                    — {{ $phpDias->count() }} dias carregados.
                @else
                    Fonte: base legada (somente leitura). Importe para habilitar edição.
                @endif
            </p>
        </div>
        <div class="actions">
            <button type="button" class="secondary no-print" onclick="window.open('{{ route('escalas.print', $filters) }}', '_blank')">Imprimir / PDF A4</button>
            <button type="button" class="secondary no-print" onclick="window.open('{{ route('escalas.prova', $filters) }}', '_blank')">Prova da escala</button>
            <a class="btn secondary" href="{{ route('escalas.plantoes', $filters) }}">Ver plantões</a>
            @if (config('grom_legacy.enabled'))
            @can('permission', 'escalas.manage')
                <button type="button" class="secondary no-print" onclick="document.getElementById('modal-sync-legado').style.display='flex'">Sincronizar Legado</button>
            @endcan
            @endif
        </div>
    </div>

    {{-- ===== PAINEL DE GERAÇÃO — aparece apenas sem escala PHP para o mês ===== --}}
    @can('escalas.manage')
        @if ($phpDias->isEmpty())
            @php $nomeMesGerar = \Carbon\Carbon::create()->month($filters['mes'])->locale('pt_BR')->isoFormat('MMMM'); @endphp
            <div class="card no-print" style="margin-bottom:18px; border:2px dashed var(--line,#ddd); background:var(--surface2,#f9f9f9);">
                <div style="display:flex; align-items:flex-start; gap:16px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:260px;">
                        <h2 style="margin:0 0 6px;">📋 Gerar Escala Provisória</h2>
                        <p class="muted" style="margin:0 0 10px;">
                            Nenhuma escala PHP encontrada para
                            <strong>{{ ucfirst($nomeMesGerar) }}/{{ $filters['ano'] }}</strong>.
                            O sistema irá:
                        </p>
                        <ul class="muted" style="margin:0 0 12px; padding-left:18px; line-height:1.7;">
                            <li>Percorrer todos os dias úteis (seg–sex) do mês</li>
                            <li>Verificar <strong>afastamentos</strong> cadastrados de cada funcionário</li>
                            <li>Aplicar regras de <strong>plantões externos</strong> já lançados
                                (MESMO_DIA, DIA_SEGUINTE, AMBOS)</li>
                            <li>Distribuir por <strong>rotação</strong>: escrivã(o), operacional, quem fecha</li>
                            <li>Se a <strong>Delegada da DDM</strong> estiver impedida → campo fica em branco
                                (combobox de Delegados Externos aparecerá para atribuição manual)</li>
                        </ul>
                        <p class="muted" style="margin:0; font-size:.83rem;">
                            ⚠ Certifique-se de ter lançado os plantões externos do mês
                            <a href="{{ route('escalas.plantoes', $filters) }}">na aba Plantões</a>
                            antes de gerar.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('escalas.gerar') }}"
                          style="display:flex; flex-direction:column; gap:8px; align-self:center; min-width:200px;"
                          onsubmit="return confirm('Gerar escala provisória de {{ ucfirst($nomeMesGerar) }}/{{ $filters['ano'] }}?\n\nOs plantões externos já cadastrados serão considerados.')">
                        @csrf
                        <input type="hidden" name="ano" value="{{ $filters['ano'] }}">
                        <input type="hidden" name="mes" value="{{ $filters['mes'] }}">
                        <button type="submit" style="padding:10px 20px; font-size:1rem; background:var(--primary,#1a5c9e); color:#fff; border:none; border-radius:10px; cursor:pointer;">
                            ⚙ Gerar Escala Provisória
                        </button>
                    </form>
                </div>
            </div>
        @endif
    @endcan

    {{-- ===== BANNER DE VERSÃO / CICLO DE VIDA ===== --}}
    @if ($phpDias->isNotEmpty() && $escalaVersao)
        @php $nomeMesVersao = \Carbon\Carbon::create()->month($filters['mes'])->locale('pt_BR')->isoFormat('MMMM'); @endphp
        @if ($escalaVersao->eh_provisoria)
            <div class="alert warn no-print" style="margin-bottom:12px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <strong>⏳ Escala PROVISÓRIA — v{{ $phpVersao }}</strong>
                    {{ ucfirst($nomeMesVersao) }}/{{ $filters['ano'] }} —
                    Confira cada dia, faça os ajustes necessários e grave como definitiva quando estiver correto.
                </div>
                @can('escalas.manage')
                    <form method="POST" action="{{ route('escalas.fechar') }}" style="display:flex; gap:8px; align-items:center;" onsubmit="return confirm('Gravar a escala de {{ ucfirst($nomeMesVersao) }}/{{ $filters['ano'] }} como DEFINITIVA?\n\nEsta ação congela todos os dias desta versão.\nPara alterações futuras, utilize \"Nova Versão\".')">
                        @csrf
                        <input type="hidden" name="ano" value="{{ $filters['ano'] }}">
                        <input type="hidden" name="mes" value="{{ $filters['mes'] }}">
                        <input type="text" name="obs" placeholder="Obs. (opcional)" style="font-size:.85rem; padding:4px 8px; border-radius:8px; border:1px solid var(--line); min-width:200px;">
                        <button type="submit" class="btn-sm" style="background:var(--success,#1a7a3c); color:#fff; padding:6px 14px;">✔ Gravar como Definitiva</button>
                    </form>
                @endcan
            </div>
        @else
            <div class="alert success no-print" style="margin-bottom:12px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <strong>✅ Escala DEFINITIVA — v{{ $phpVersao }}</strong>
                    {{ ucfirst($nomeMesVersao) }}/{{ $filters['ano'] }}
                    @if ($escalaVersao->fechada_em)
                        · Gravada em {{ $escalaVersao->fechada_em->format('d/m/Y \à\s H:i') }}
                    @endif
                    @if ($escalaVersao->obs)
                        · <em style="color:#555;">{{ $escalaVersao->obs }}</em>
                    @endif
                </div>
                @can('escalas.manage')
                    <form method="POST" action="{{ route('escalas.nova-versao') }}" style="display:inline;" onsubmit="return confirm('Criar versão v{{ $phpVersao + 1 }} para emendas?\n\nDias anteriores a hoje serão copiados e travados.\nDias a partir de hoje serão editáveis.')">
                        @csrf
                        <input type="hidden" name="ano" value="{{ $filters['ano'] }}">
                        <input type="hidden" name="mes" value="{{ $filters['mes'] }}">
                        <button type="submit" class="btn-sm" style="padding:6px 14px;">📋 Nova Versão (Emenda)</button>
                    </form>
                @endcan
            </div>
        @endif
    @endif

    {{-- Banner de status --}}
    @if (session('status-success'))
        <div class="alert success no-print" style="margin-bottom:12px;">{{ session('status-success') }}</div>
    @elseif (session('status-error'))
        <div class="alert danger no-print" style="margin-bottom:12px;">{{ session('status-error') }}</div>
    @elseif (session('status-warning'))
        <div class="alert warn no-print" style="margin-bottom:12px;">{{ session('status-warning') }}</div>
    @endif

    <section class="card no-print" style="margin-bottom: 18px;">
        <form method="GET" action="{{ route('escalas.index') }}" class="actions">
            <div class="field" style="min-width: 140px;">
                <label for="ano">Ano</label>
                <select id="ano" name="ano">
                    @php
                        $anosDisp = !empty($anosPhp) ? $anosPhp : ($snapshot['available_years'] ?? [now()->year]);
                    @endphp
                    @foreach ($anosDisp as $year)
                        <option value="{{ $year }}" @selected($filters['ano'] === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="min-width: 180px;">
                <label for="mes">Mês</label>
                <select id="mes" name="mes">
                    @foreach (range(1, 12) as $month)
                        <option value="{{ $month }}" @selected($filters['mes'] === $month)>{{ str_pad((string)$month, 2, '0', STR_PAD_LEFT) }} - {{ \Carbon\Carbon::create()->month($month)->locale('pt_BR')->isoFormat('MMMM') }}</option>
                    @endforeach
                </select>
            </div>
            @if ($todasVersoes->count() > 1)
                <div class="field" style="min-width: 130px;">
                    <label for="versao">Versão</label>
                    <select id="versao" name="versao">
                        <option value="">Mais recente</option>
                        @foreach ($todasVersoes as $vItem)
                            <option value="{{ $vItem->versao }}" @selected($filters['versao'] === $vItem->versao)>
                                v{{ $vItem->versao }} {{ $vItem->status === 'definitiva' ? '✅' : '⏳' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="actions" style="align-self: end;">
                <button type="submit">Filtrar</button>
            </div>
        </form>
    </section>

    {{-- KPIs --}}
    <div class="cards no-print" style="margin-bottom: 18px;">
        @php
            $diasTotal   = $phpDias->isNotEmpty() ? $phpDias->count() : ($snapshot['summary']['dias_total'] ?? 0);
            $diasEscrivao= $phpDias->isNotEmpty() ? $phpDias->whereNotNull('escrivao')->whereNotIn('escrivao', [''])->count() : ($snapshot['summary']['dias_com_escrivao'] ?? 0);
            $diasDeleg   = $phpDias->isNotEmpty() ? $phpDias->whereNotNull('delegada')->whereNotIn('delegada', [''])->count() : ($snapshot['summary']['dias_com_delegada'] ?? 0);
            $versaoAtual = $phpDias->isNotEmpty() ? $phpVersao : ($snapshot['version'] ?? '–');
        @endphp
        <article class="card">
            <small>Fonte</small>
            <strong>{{ $phpDias->isNotEmpty() ? 'PHP ✓' : 'Legado (somente leitura)' }}</strong>
            <span>{{ $phpDias->isNotEmpty() ? 'Dados sob controle do Grom.Seg' : 'Importação pendente' }}</span>
        </article>
        <article class="card">
            <small>Versão ativa</small>
            <strong>{{ $versaoAtual }}</strong>
            <span>{{ \Carbon\Carbon::create()->month($filters['mes'])->locale('pt_BR')->isoFormat('MMMM') }} / {{ $filters['ano'] }}</span>
        </article>
        <article class="card">
            <small>Dias na escala</small>
            <strong>{{ $diasTotal }}</strong>
            <span>Registros encontrados para o mês.</span>
        </article>
        <article class="card">
            <small>Com escrivão</small>
            <strong>{{ $diasEscrivao }}</strong>
            <span>Dias com preenchimento de escrivão.</span>
        </article>
        <article class="card">
            <small>Com delegada</small>
            <strong>{{ $diasDeleg }}</strong>
            <span>Dias com designação de delegada.</span>
        </article>
        <article class="card">
            <small>Atribuições plantão</small>
            <strong>{{ count($plantoesMes ?? []) > 0 ? collect($plantoesMes ?? [])->flatten()->count() : ($snapshot['summary']['plantoes_atribuicoes'] ?? 0) }}</strong>
            <span>Vínculos funcionário ↔ plantão no mês.</span>
        </article>
        <article class="card">
            <small>Funcionários ativos</small>
            <strong>{{ $phpMirrorSummary['ativos'] }}</strong>
            <span>{{ $phpMirrorSummary['concorrem_escala'] }} concorrem à escala.</span>
        </article>
    </div>

    @if (! empty($snapshot['warnings']))
        <section class="card no-print" style="margin-bottom: 18px;">
            <h2 style="margin-top: 0;">Avisos de leitura</h2>
            <div class="grid">
                @foreach ($snapshot['warnings'] as $warning)
                    <div class="tag warn">{{ $warning }}</div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="card no-print" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Base funcional da escala</h2>
        <p class="muted" style="margin: 0 0 14px;">
            Confronto direto entre a base funcional do Python e o espelho PHP dos funcionarios já migrados.
        </p>
        <p class="muted" style="margin: 0 0 14px;">
            Leitura somente consulta da escala mensal consolidada no legado.
            <span class="tag warn">Fonte legada</span>
            <span class="tag">Dias da escala</span>
            <span class="tag">Feriados do mes</span>
            <span class="tag">Espelho PHP</span>
        </p>

        <div class="cards" style="margin-bottom: 16px;">
            <article class="card">
                <small>Python ativos</small>
                <strong>{{ $snapshot['summary']['funcionarios_total'] }}</strong>
                <span>{{ $snapshot['summary']['funcionarios_ativos'] }} ativos visíveis, {{ $snapshot['summary']['funcionarios_concorrem'] }} aptos à escala.</span>
            </article>
            <article class="card">
                <small>PHP ativos</small>
                <strong>{{ $phpMirrorSummary['total'] }}</strong>
                <span>{{ $phpMirrorSummary['ativos'] }} ativos visíveis, {{ $phpMirrorSummary['concorrem_escala'] }} aptos à escala.</span>
            </article>
            <article class="card">
                <small>Diferença</small>
                <strong>{{ $phpMirrorSummary['total'] - $snapshot['summary']['funcionarios_total'] }}</strong>
                <span>Comparação entre espelho web e base legada.</span>
            </article>
        </div>

        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
            <article class="card">
                <small>Observações do legado</small>
                <strong>{{ collect($snapshot['funcionarios'])->where('ativo', true)->count() }} ativos</strong>
                <span class="muted">Observacoes dos ativos</span>
                <span>Todos aparecem, mesmo quando não concorrem à escala.</span>
            </article>
            <article class="card">
                <small>Observações do PHP</small>
                <strong>{{ $phpAtivos->count() }} ativos</strong>
                <span class="muted">Ativos mantidos na consulta</span>
                <span>Falta de concorre não remove o funcionário da consulta.</span>
            </article>
        </div>

        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 14px;">
            <details open>
                <summary>Funcionarios do legado Python</summary>
                <table style="margin-top: 12px;">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Cargo / Setor</th>
                            <th>Contato / Docs</th>
                            <th>Datas</th>
                            <th>Escala</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($snapshot['funcionarios'] as $funcionario)
                            <tr>
                                <td>
                                    <strong>{{ $funcionario['nome'] ?: $funcionario['nome_simplificado'] }}</strong><br>
                                    <span class="muted">{{ $funcionario['nome_simplificado'] ?: $funcionario['legacy_key'] }}</span>
                                </td>
                                <td>
                                    {{ $funcionario['cargo'] ?: 'Sem cargo' }}<br>
                                    <span class="muted">{{ $funcionario['setor'] ?: 'Sem setor' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $funcionario['telefone'] ?: 'Sem telefone' }}</strong><br>
                                    <span class="muted">RG: {{ $funcionario['rg'] ?: 'N/D' }}</span><br>
                                    <span class="muted">CPF: {{ $funcionario['cpf'] ?: 'N/D' }}</span>
                                </td>
                                <td>
                                    <strong>Nasc.: {{ $funcionario['data_aniversario'] ? \Illuminate\Support\Carbon::parse($funcionario['data_aniversario'])->format('d/m/Y') : 'N/D' }}</strong><br>
                                    <span class="muted">Designacao: {{ $funcionario['data_designacao'] ? \Illuminate\Support\Carbon::parse($funcionario['data_designacao'])->format('d/m/Y') : 'N/D' }}</span><br>
                                    <span class="muted">Remocao: {{ $funcionario['data_remocao'] ? \Illuminate\Support\Carbon::parse($funcionario['data_remocao'])->format('d/m/Y') : 'N/D' }}</span>
                                </td>
                                <td>
                                    <span class="tag {{ $funcionario['ativo'] ? 'good' : 'warn' }}">{{ $funcionario['ativo'] ? 'Ativo' : 'Inativo' }}</span>
                                    <div style="margin-top: 6px;">
                                        <span class="tag {{ $funcionario['concorre_escala'] ? 'good' : 'warn' }}">
                                            {{ $funcionario['concorre_escala'] ? 'Apto à escala' : 'Nao apto à escala' }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if (! empty($funcionario['current_afastamento']))
                                        <div class="muted" style="margin-top: 6px;">Afastamento em vigor: {{ $funcionario['current_afastamento']['tipo'] }}</div>
                                        <div class="muted">Periodo: {{ $funcionario['current_afastamento']['data_inicio'] }} @if (! empty($funcionario['current_afastamento']['data_fim'])) até {{ $funcionario['current_afastamento']['data_fim'] }} @else ate em aberto @endif</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">Nenhum funcionario legado encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </details>

            <details>
                <summary>Espelho PHP do RH</summary>
                <table style="margin-top: 12px;">
                    <thead>
                        <tr>
                            <th>Funcionario</th>
                            <th>Cargo / Setor</th>
                            <th>Contato / Docs</th>
                            <th>Datas</th>
                            <th>Escala</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($phpFuncionarios as $funcionario)
                            @php($currentAfastamento = $funcionario->currentAfastamento())
                            <tr>
                                <td>
                                    <strong>{{ $funcionario->matricula }}</strong><br>
                                    <span class="muted">{{ $funcionario->name }}</span><br>
                                    <span class="muted">{{ $funcionario->short_name ?: 'Sem nome simplificado' }}</span>
                                </td>
                                <td>
                                    {{ $funcionario->cargo?->name ?: 'Sem cargo' }}<br>
                                    <span class="muted">{{ $funcionario->sector ?: 'Sem setor' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $funcionario->phone ?: 'Sem telefone' }}</strong><br>
                                    <span class="muted">RG: {{ $funcionario->rg ?: 'N/D' }}</span><br>
                                    <span class="muted">CPF: {{ $funcionario->cpf ?: 'N/D' }}</span>
                                </td>
                                <td>
                                    <strong>Nasc.: {{ $funcionario->birth_date?->format('d/m/Y') ?: 'N/D' }}</strong><br>
                                    <span class="muted">Designacao: {{ $funcionario->designation_date?->format('d/m/Y') ?: 'N/D' }}</span><br>
                                    <span class="muted">Remocao: {{ $funcionario->removal_date?->format('d/m/Y') ?: 'N/D' }}</span>
                                </td>
                                <td>
                                    <span class="tag {{ $funcionario->is_active ? 'good' : 'warn' }}">{{ $funcionario->is_active ? 'Ativo' : 'Inativo' }}</span>
                                    <div style="margin-top: 6px;">
                                        <span class="tag {{ $funcionario->concorre_escala ? 'good' : 'warn' }}">
                                            {{ $funcionario->concorre_escala ? 'Apto à escala' : 'Nao apto à escala' }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if ($currentAfastamento)
                                        <span class="tag warn">Em afastamento</span><br>
                                        <span class="muted">{{ $currentAfastamento->reason }}</span>
                                        <div class="muted">Periodo: {{ $currentAfastamento->start_date?->format('d/m/Y') }} @if ($currentAfastamento->end_date) até {{ $currentAfastamento->end_date?->format('d/m/Y') }} @else ate em aberto @endif</div>
                                    @else
                                        <span class="tag {{ $funcionario->is_active ? 'good' : 'warn' }}">{{ $funcionario->is_active ? 'Ativo' : 'Inativo' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">Nenhum funcionario espelhado no PHP.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </details>
        </div>
    </section>

    {{-- ===== TABELA DA ESCALA DIÁRIA ===== --}}
    <div class="grid" style="grid-template-columns: 1.15fr .85fr; margin-bottom: 18px;">
        <section class="card print-scope">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px;">
                <h2 style="margin: 0;">
                    Escala diária
                    @if ($phpDias->isNotEmpty())
                        <span class="tag good" style="font-size:.75rem; font-weight:400; vertical-align:middle;">PHP — v{{ $phpVersao }}</span>
                    @else
                        <span class="tag warn" style="font-size:.75rem; font-weight:400; vertical-align:middle;">Legado</span>
                    @endif
                </h2>
                @can('escalas.manage')
                    @if ($phpDias->isNotEmpty())
                        <button class="btn-sm" onclick="document.getElementById('modal-add-dia').style.display='flex'">+ Adicionar dia</button>
                    @endif
                @endcan
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Dia</th>
                        <th>Escrivao</th>
                        <th>Operacional</th>
                        <th>Fechar</th>
                        <th>Delegada</th>
                        <th>Plantao ext.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($escalaLinhas as $row)
                        <tr class="{{ ($row['display_mode'] ?? 'normal') === 'holiday' ? 'scale-row-holiday' : '' }}">
                            <td>
                                <strong>{{ $row['date_label'] }}</strong><br>
                                <span class="muted">{{ $row['date'] }}</span>
                            </td>
                            <td>{{ $row['day_label'] }}</td>
                            @if (($row['source'] ?? 'legacy') === 'php' && ! empty($row['is_weekend']))
                                <td colspan="4" style="text-align:center; color:#999;">Fim de semana</td>
                            @elseif (($row['display_mode'] ?? 'normal') === 'holiday')
                                <td colspan="4" class="scale-holiday-marker" style="text-align:center;">FERIADO</td>
                            @elseif (($row['display_mode'] ?? 'normal') === 'weekend')
                                <td colspan="4"></td>
                            @else
                                <td>{{ $row['escrivao'] ?: '—' }}</td>
                                <td>{{ $row['operacional'] ?: '—' }}</td>
                                <td>{{ $row['fechar'] ?: '—' }}</td>
                                <td>{{ $row['delegada'] ?: '—' }}</td>
                            @endif
                            <td class="plantao-cell">
                                @if (! empty($row['plantao_items']))
                                    {{ implode(', ', $row['plantao_items']) }}
                                @else
                                    {{ $row['plantao_externo'] ?: '—' }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">Nenhuma linha de escala encontrada para o período selecionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card no-print">
            @if (!empty($snapshot['holidays']))
                <h2 style="margin-top: 0;">Feriados do mês</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($snapshot['holidays'] as $holiday)
                            <tr>
                                <td>{{ $holiday['date_label'] }}</td>
                                <td>{{ $holiday['descricao'] }}</td>
                                <td>{{ $holiday['tipo'] ?: 'Não informado' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="margin-top: 18px;"></div>
            @endif

            <h2 style="margin-top: 0;">Resumo do mês</h2>
            <div class="grid">
                @if ($phpDias->isNotEmpty())
                    <div class="tag good">Dias com operacional: {{ $phpDias->whereNotNull('operacional')->whereNotIn('operacional',[''])->count() }}</div>
                    <div class="tag good">Dias com plantão ext.: {{ $phpDias->whereNotNull('plantao_externo')->whereNotIn('plantao_externo',[''])->count() }}</div>
                    <div class="tag good">Versão ativa: v{{ $phpVersao }}</div>
                    <div class="tag good">Plantões ativos no catálogo: {{ $catalogo->count() }}</div>
                    <div class="tag good">Funcionários ativos: {{ $phpMirrorSummary['ativos'] }}</div>
                    <div class="tag {{ $phpMirrorSummary['em_afastamento'] > 0 ? 'warn' : 'good' }}">Em afastamento: {{ $phpMirrorSummary['em_afastamento'] }}</div>
                @elseif (!empty($snapshot['summary']))
                    <div class="tag good">Dias com operacional: {{ $snapshot['summary']['dias_com_operacional'] ?? 0 }}</div>
                    <div class="tag good">Dias com plantão ext.: {{ $snapshot['summary']['dias_com_plantao_externo'] ?? 0 }}</div>
                    <div class="tag good">Atribuições: {{ $snapshot['summary']['plantoes_atribuicoes'] ?? 0 }}</div>
                    <div class="tag good">Catálogo ativos: {{ $snapshot['summary']['plantoes_catalogo_ativos'] ?? 0 }}</div>
                    <div class="tag good">Funcionários ativos: {{ $snapshot['summary']['funcionarios_ativos'] ?? 0 }}</div>
                    <div class="tag warn">Em afastamento: {{ $snapshot['summary']['funcionarios_em_afastamento'] ?? 0 }}</div>
                @else
                    <div class="tag warn">Sem dados para o período selecionado.</div>
                @endif
            </div>
        </section>
    </div>


    {{-- ===== PLANTÕES EXTERNOS DO PERÍODO ===== --}}
    <section class="card no-print">
        <h2 style="margin-top: 0;">Plantões externos do período</h2>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Funcionário</th>
                    <th>Plantão</th>
                    <th>Unidade</th>
                    <th>Regra</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plantaoLinhas as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td>
                            <strong>{{ $row['funcionario'] }}</strong><br>
                            <span class="muted">{{ $row['cargo'] }}</span>
                        </td>
                        <td>{{ $row['plantao'] }}</td>
                        <td>{{ $row['unidade'] }}</td>
                        <td>{{ $row['regra'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Nenhum plantão externo registrado para o período selecionado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    {{-- ===== MODAIS (só para usuários com escalas.manage) ===== --}}
    @can('escalas.manage')

    {{-- Modal: Adicionar dia --}}
    <div id="modal-add-dia" class="grom-overlay">
        <div class="card" style="width:480px; max-width:96vw;">
            <h2 style="margin-top:0;">Adicionar dia à escala</h2>
            <form method="POST" action="{{ route('escalas.dias.store') }}">
                @csrf
                <input type="hidden" name="versao" value="{{ $phpVersao ?? 1 }}">
                <div class="grid" style="grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="field"><label>Data</label><input type="date" name="data" required value="{{ now()->format('Y-m-') }}01"></div>
                    <div class="field"><label>Escrivão</label><input type="text" name="escrivao" maxlength="100"></div>
                    <div class="field"><label>Operacional</label><input type="text" name="operacional" maxlength="100"></div>
                    <div class="field"><label>Fechar</label><input type="text" name="fechar_nome" maxlength="100"></div>
                    <div class="field"><label>Delegada</label><input type="text" name="delegada" maxlength="100"></div>
                    <div class="field"><label>Plantão externo (texto)</label><input type="text" name="plantao_externo" maxlength="200"></div>
                </div>
                <div class="actions" style="margin-top:14px; justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('modal-add-dia').style.display='none'">Cancelar</button>
                    <button type="submit">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Atribuir plantão a funcionário --}}
    <div id="modal-add-plantao-func" class="grom-overlay">
        <div class="card" style="width:440px; max-width:96vw;">
            <h2 style="margin-top:0;">Atribuir plantão a funcionário</h2>
            <form method="POST" action="{{ route('escalas.plantoes-funcionarios.store') }}">
                @csrf
                <div class="field"><label>Funcionário</label>
                    <select name="funcionario_id" required>
                        <option value="">Selecionar...</option>
                        @foreach ($phpFuncionarios as $f)
                            <option value="{{ $f->id }}">{{ $f->short_name ?? $f->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label>Plantão externo</label>
                    <select name="plantao_externo_id" required>
                        <option value="">Selecionar...</option>
                        @foreach ($catalogo as $pe)
                            <option value="{{ $pe->id }}">{{ $pe->sigla }} — {{ $pe->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label>Data</label><input type="date" name="data" required></div>
                <div class="actions" style="margin-top:14px; justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('modal-add-plantao-func').style.display='none'">Cancelar</button>
                    <button type="submit">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Novo plantão externo --}}
    <div id="modal-add-plantao-ext" class="grom-overlay">
        <div class="card" style="width:440px; max-width:96vw;">
            <h2 style="margin-top:0;">Novo plantão externo</h2>
            <form method="POST" action="{{ route('escalas.plantoes-externos.store') }}">
                @csrf
                <div class="grid" style="grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="field"><label>Sigla *</label><input type="text" name="sigla" required maxlength="20"></div>
                    <div class="field"><label>Nome *</label><input type="text" name="nome" required maxlength="120"></div>
                    <div class="field"><label>Unidade</label><input type="text" name="unidade" maxlength="100"></div>
                    <div class="field"><label>Regra</label><input type="text" name="regra" maxlength="200"></div>
                    <div class="field" style="grid-column:1/-1;"><label>Observação</label><textarea name="observacao" maxlength="400" rows="2"></textarea></div>
                </div>
                <div class="actions" style="margin-top:14px; justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('modal-add-plantao-ext').style.display='none'">Cancelar</button>
                    <button type="submit">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Confirmar sync legado --}}
    <div id="modal-sync-legado" class="grom-overlay">
        <div class="card" style="width:420px; max-width:96vw;">
            <h2 style="margin-top:0;">Importar dados do legado</h2>
            <p>Esta operação importa <strong>todos os meses disponíveis</strong> de escalas do banco Python para o banco PHP. Registros já importados serão atualizados sem duplicação.</p>
            <form method="POST" action="{{ route('escalas.sync-legado') }}">
                @csrf
                <div class="actions" style="margin-top:14px; justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('modal-sync-legado').style.display='none'">Cancelar</button>
                    <button type="submit">Confirmar importação</button>
                </div>
            </form>
        </div>
    </div>

    @endcan

    {{-- Datalist de funcionários para autocompletar os campos de texto --}}
    @if ($phpAtivos->isNotEmpty())
        <datalist id="lista-func">
            @foreach ($phpAtivos as $f)
                <option value="{{ $f->name }}">
            @endforeach
        </datalist>
    @endif

    {{-- ===== HELPER JS — edição inline de campos da escala ===== --}}
    <script>
    function escalaEditarCampo(diaId, campo) {
        var editId  = 'escala-edit-' + diaId + '-' + campo;
        var valId   = 'escala-val-' + diaId + '-' + campo;
        var editDiv = document.getElementById(editId);
        var valSpan = document.getElementById(valId);
        if (!editDiv) return;
        var aberto = editDiv.style.display !== 'none';
        editDiv.style.display = aberto ? 'none' : 'flex';
        if (valSpan) valSpan.style.display = aberto ? '' : 'none';
    }
    </script>

@endsection
