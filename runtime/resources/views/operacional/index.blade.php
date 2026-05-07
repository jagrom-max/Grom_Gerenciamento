@extends('layouts.app')

@section('title', 'Painel Operacional | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Painel operacional</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Central de entrada da produtividade, reunindo cartorios, flagrantes, fechamento mensal e pendencias em uma unica visao.
            </p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('produtividade.cartorios.index') }}">Cartorios</a>
            <a class="btn secondary" href="{{ route('produtividade.flagrantes.index') }}">Flagrantes</a>
            <a class="btn secondary" href="{{ route('produtividade.stats.index') }}">Estatisticas</a>
        </div>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <form method="GET" action="{{ route('operacional.index') }}" class="form-grid">
            <div class="field">
                <label for="year">Ano</label>
                <input id="year" name="year" type="number" min="2020" max="2100" value="{{ $year }}">
            </div>
            <div class="field">
                <label for="month">Mes</label>
                <select id="month" name="month">
                    <option value="0" @selected($month === 0)>Ano inteiro</option>
                    @foreach ([1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Marco', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'] as $index => $label)
                        <option value="{{ $index }}" @selected($month === $index)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field full">
                <label for="cartorio_id">Cartorio</label>
                <select id="cartorio_id" name="cartorio_id">
                    <option value="">Todos os cartorios visiveis</option>
                    @foreach ($cartorios as $cartorio)
                        <option value="{{ $cartorio->id }}" @selected(($filters['cartorio_id'] ?? null) === $cartorio->id)>
                            {{ str_pad((string) $cartorio->number, 3, '0', STR_PAD_LEFT) }} - {{ $cartorio->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field full">
                <div class="actions">
                    <button type="submit">Aplicar filtros</button>
                    <a class="btn secondary" href="{{ route('operacional.index') }}">Limpar</a>
                </div>
            </div>
        </form>
    </section>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Cartorios visiveis</small>
            <strong>{{ $summary['cartorios_visiveis'] }}</strong>
            <span>Escopo atual da consulta operacional.</span>
        </article>
        <article class="card">
            <small>Pendencias abertas</small>
            <strong>{{ $summary['pendencias_abertas'] }}</strong>
            <span>Fila pronta para saneamento e confirmacao.</span>
        </article>
        <article class="card">
            <small>Pendencias 7d</small>
            <strong>{{ $summary['pendencias_7d'] }}</strong>
            <span>Itens que exigem atencao imediata.</span>
        </article>
        <article class="card">
            <small>Lotes 30d</small>
            <strong>{{ $summary['lotes_30d'] }}</strong>
            <span>Consolidacoes recentes do periodo.</span>
        </article>
        <article class="card">
            <small>Lotes com erro</small>
            <strong>{{ $summary['lotes_com_erro_30d'] }}</strong>
            <span>Entradas que precisam revisao.</span>
        </article>
        <article class="card">
            <small>Flagrantes do periodo</small>
            <strong>{{ $selectedStats['flagrantes_total'] }}</strong>
            <span>Base consolidada para o filtro aplicado.</span>
        </article>
        <article class="card">
            <small>Registros</small>
            <strong>{{ $selectedStats['registros'] }}</strong>
            <span>Volume operacional ja consolidado.</span>
        </article>
        <article class="card">
            <small>IP instaurados</small>
            <strong>{{ $selectedStats['ip_instaurados'] }}</strong>
            <span>Indicador pratico de producao.</span>
        </article>
        <article class="card">
            <small>Espelho RH</small>
            <strong>{{ $phpFuncionariosSummary['total'] }}</strong>
            <span>{{ $phpFuncionariosSummary['ativos'] }} ativos visiveis, {{ $phpFuncionariosSummary['concorrem_escala'] }} aptos a escala.</span>
        </article>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Cartorios reais do periodo</h2>
        <p class="muted" style="margin: 0 0 14px;">
            Cartorios visiveis com os valores consolidados do periodo selecionado, sem esconder unidades sem estatistica.
        </p>
        <table>
            <thead>
                <tr>
                    <th>Cartório</th>
                    <th>Responsável</th>
                    <th>Período</th>
                    <th>IP</th>
                    <th>Relatados</th>
                    <th>Concluídos</th>
                    <th>Registros</th>
                    <th>Andamento</th>
                    <th>Flagrantes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cartoriosPreview as $row)
                    <tr>
                        <td>
                            <strong>{{ str_pad((string) $row['cartorio']->number, 3, '0', STR_PAD_LEFT) }} - {{ $row['cartorio']->name }}</strong><br>
                            <span class="muted">{{ $row['cartorio']->code }}</span><br>
                            <span class="tag {{ $row['cartorio']->is_active ? 'good' : 'warn' }}">{{ $row['cartorio']->is_active ? 'Ativo' : 'Inativo' }}</span>
                        </td>
                        <td>
                            {{ $row['cartorio']->manager_name ?: 'Não informado' }}<br>
                            <span class="muted">{{ $row['cartorio']->designacao ?: 'Sem designação' }}</span>
                        </td>
                        <td>
                            {{ $row['period_label'] }}<br>
                            <span class="tag {{ $row['has_stats'] ? 'good' : 'warn' }}">{{ $row['has_stats'] ? 'Com estatística' : 'Sem estatística' }}</span>
                        </td>
                        <td>{{ $row['ip_instaurados'] }}</td>
                        <td>{{ $row['ip_relatados'] }}</td>
                        <td>{{ $row['concluidos'] }}</td>
                        <td>{{ $row['registros'] }}</td>
                        <td>{{ $row['ips_andamento'] }}</td>
                        <td>
                            {{ $row['flagrantes_total'] }}<br>
                            <span class="muted">DDM {{ $row['flagrantes_ddm'] }} | Outras {{ $row['flagrantes_outras'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">Nenhum cartorio visivel para o periodo selecionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Base operacional ligada</h2>
        <p class="muted" style="margin: 0 0 14px;">
            Funcionários cadastrados no espelho PHP com situação de escala e afastamentos.
        </p>

        <div class="cards" style="margin-bottom: 16px;">
            <article class="card">
                <small>PHP espelho</small>
                <strong>{{ $phpFuncionariosSummary['total'] }}</strong>
                <span>{{ $phpFuncionariosSummary['ativos'] }} ativos, {{ $phpFuncionariosSummary['concorrem_escala'] }} concorrendo.</span>
            </article>
            <article class="card">
                <small>Pendencias abertas</small>
                <strong>{{ $summary['pendencias_abertas'] }}</strong>
                <span>{{ $summary['pendencias_7d'] }} com 7 dias ou mais.</span>
            </article>
        </div>

        <details open>
            <summary>Funcionarios do espelho PHP</summary>
                <table style="margin-top: 12px;">
                    <thead>
                        <tr>
                            <th>Funcionario</th>
                            <th>Cargo / Setor</th>
                            <th>Contato / Docs</th>
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
                                    @if ($currentAfastamento)
                                        <span class="tag warn">Em afastamento</span><br>
                                        <span class="muted">{{ $currentAfastamento->reason }}</span>
                                    @else
                                        <span class="tag {{ $funcionario->is_active ? 'good' : 'warn' }}">{{ $funcionario->is_active ? 'Ativo' : 'Inativo' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">Nenhum funcionario espelhado no PHP.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </details>
    </section>

    <div class="grid" style="grid-template-columns: 1.1fr .9fr; margin-bottom: 18px;">
        <section class="card">
            <h2 style="margin-top: 0;">Ranking operacional</h2>
            <table>
                <thead>
                    <tr>
                        <th>Cartório</th>
                        <th>IP instaurados</th>
                        <th>Relatados</th>
                        <th>Concluídos</th>
                        <th>Registros</th>
                        <th>Andamento</th>
                        <th>Flagrantes</th>
                        <th>Pendências</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ranking as $row)
                        <tr>
                            <td>
                                <strong>{{ str_pad((string) $row['cartorio']->number, 3, '0', STR_PAD_LEFT) }} - {{ $row['cartorio']->name }}</strong><br>
                                <span class="muted">{{ $row['cartorio']->code }}</span>
                            </td>
                            <td>{{ $row['ip_instaurados'] }}</td>
                            <td>{{ $row['ip_relatados'] }}</td>
                            <td>{{ $row['concluidos'] }}</td>
                            <td>{{ $row['registros'] }}</td>
                            <td>{{ $row['ips_andamento'] }}</td>
                            <td>{{ $row['flagrantes_total'] }}</td>
                            <td>{{ $row['pending_items'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">Nenhum registro consolidado para o filtro atual.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Ação rápida</h2>
            <div class="grid">
                <a class="btn secondary" href="{{ route('produtividade.cartorios.index') }}">Abrir cartórios</a>
                <a class="btn secondary" href="{{ route('produtividade.flagrantes.index') }}">Abrir fila de flagrantes</a>
                <a class="btn secondary" href="{{ route('produtividade.stats.index', request()->query()) }}">Abrir estatísticas</a>
                <a class="btn secondary" href="{{ route('analise.index') }}">Abrir análise</a>
                <a class="btn secondary" href="{{ route('calendarios.index') }}">Abrir calendários</a>
            </div>

            <h2 style="margin-top: 18px;">Resumo do periodo</h2>
            <div class="grid">
                <div class="tag good">IP relatados: {{ $selectedStats['ip_relatados'] }}</div>
                <div class="tag good">Concluídos: {{ $selectedStats['concluidos'] }}</div>
                <div class="tag good">Despachos: {{ $selectedStats['despachos'] }}</div>
                <div class="tag good">Cotas: {{ $selectedStats['cotas'] }}</div>
                <div class="tag good">IPs em andamento: {{ $selectedStats['ips_andamento'] }}</div>
                <div class="tag good">Flagrantes DDM: {{ $selectedStats['flagrantes_ddm'] }}</div>
            </div>

            <h3 style="margin: 18px 0 10px;">Últimos lotes</h3>
            <table>
                <thead>
                    <tr>
                        <th>Arquivo</th>
                        <th>Atualizados</th>
                        <th>Erros</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentBatches as $batch)
                        <tr>
                            <td>
                                <strong>{{ $batch->source_name }}</strong><br>
                                <span class="muted">{{ $batch->imported_at?->format('d/m/Y H:i') }}</span>
                            </td>
                            <td>{{ (int) $batch->rows_updated }}</td>
                            <td>{{ (int) $batch->error_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">Nenhum lote recente encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <div class="grid" style="grid-template-columns: 1fr 1fr;">
        <section class="card">
            <h2 style="margin-top: 0;">Pendencias envelhecidas</h2>
            <table>
                <thead>
                    <tr>
                        <th>SPJ</th>
                        <th>Cartorio</th>
                        <th>Lavrado</th>
                        <th>Idade</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendingItems->take(10) as $row)
                        <tr>
                            <td>
                                <strong>{{ $row['item']->spj ?: $row['item']->source_process_key }}</strong><br>
                                <span class="muted">{{ $row['item']->batch?->source_name }}</span>
                            </td>
                            <td>{{ $row['item']->cartorio?->name ?: 'Sem cartorio' }}</td>
                            <td>{{ $row['item']->lavrado_unidade?->label() }}</td>
                            <td>{{ $row['age_days'] }} dias</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Nenhuma pendência aberta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Evolucao mensal</h2>
            <table>
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Flagrantes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($monthlyBreakdown as $monthRow)
                        <tr>
                            <td>{{ $monthRow['label'] }}</td>
                            <td>{{ $monthRow['flagrantes_total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>
@endsection
