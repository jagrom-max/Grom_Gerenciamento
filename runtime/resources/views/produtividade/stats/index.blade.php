@extends('layouts.app')

@section('title', 'Estatisticas de Produtividade | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Estatisticas operacionais de produtividade</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Visao consolidada da base de cartorios, fechamento mensal e fila pendente para apoiar a operacao e a migracao.
            </p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('produtividade.stats.export', request()->query()) }}">Exportar CSV</a>
            <a class="btn secondary" href="{{ route('produtividade.flagrantes.index') }}">Abrir flagrantes</a>
            <a class="btn secondary" href="{{ route('produtividade.cartorios.index') }}">Abrir cartorios</a>
        </div>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Filtros</h2>
        <form method="GET" action="{{ route('produtividade.stats.index') }}" class="form-grid">
            <div class="field">
                <label for="year">Ano</label>
                <input id="year" name="year" type="number" min="2020" max="2100" value="{{ $year }}">
            </div>
            <div class="field">
                <label for="month">Mes</label>
                <select id="month" name="month">
                    <option value="0" @selected($month === 0)>Ano inteiro</option>
                    @foreach ([
                        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Marco', 4 => 'Abril',
                        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
                    ] as $index => $label)
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
                    <a class="btn secondary" href="{{ route('produtividade.stats.index') }}">Limpar</a>
                </div>
            </div>
        </form>
    </section>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Cartorios visiveis</small>
            <strong>{{ $summary['cartorios_visiveis'] }}</strong>
            <span>Base de acesso conforme RBAC e escopo.</span>
        </article>
        <article class="card">
            <small>Registros no periodo</small>
            <strong>{{ $summary['stats_registros'] }}</strong>
            <span>Linhas consolidadas no fechamento selecionado.</span>
        </article>
        <article class="card">
            <small>Pendencias abertas</small>
            <strong>{{ $summary['pendencias_abertas'] }}</strong>
            <span>Fila ativa do fluxo de produtividade.</span>
        </article>
        <article class="card">
            <small>Pendencias 7d</small>
            <strong>{{ $summary['pendencias_7d'] }}</strong>
            <span>Itens que merecem saneamento prioritario.</span>
        </article>
        <article class="card">
            <small>Lotes 30d</small>
            <strong>{{ $summary['lotes_30d'] }}</strong>
            <span>Entradas recentes da consolidacao.</span>
        </article>
        <article class="card">
            <small>Lotes com erro</small>
            <strong>{{ $summary['lotes_com_erro_30d'] }}</strong>
            <span>Lotes que precisam revisao ou reprocessamento.</span>
        </article>
        <article class="card">
            <small>Cotas</small>
            <strong>{{ $selectedStats['cotas'] }}</strong>
            <span>Campo existente do fechamento mensal.</span>
        </article>
        <article class="card">
            <small>Despachos</small>
            <strong>{{ $selectedStats['despachos'] }}</strong>
            <span>Movimentacao consolidada no banco.</span>
        </article>
        <article class="card">
            <small>Concluidos</small>
            <strong>{{ $selectedStats['concluidos'] }}</strong>
            <span>Resultado efetivado do periodo selecionado.</span>
        </article>
        <article class="card">
            <small>Registros</small>
            <strong>{{ $selectedStats['registros'] }}</strong>
            <span>Volume pratico alimentado pela base.</span>
        </article>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Cartorios reais espelhados</h2>
        <p class="muted" style="margin: 0 0 14px;">
            Lista consolidada dos cartorios visiveis com os valores do periodo selecionado, incluindo cartorio sem movimento quando houver.
        </p>
        <table>
            <thead>
                <tr>
                    <th>Cartorio</th>
                    <th>Responsavel</th>
                    <th>Periodo</th>
                    <th>IP</th>
                    <th>Relatados</th>
                    <th>Concluidos</th>
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
                            {{ $row['cartorio']->manager_name ?: 'Nao informado' }}<br>
                            <span class="muted">{{ $row['cartorio']->designacao ?: 'Sem designacao' }}</span>
                        </td>
                        <td>
                            {{ $row['period_label'] }}<br>
                            <span class="tag {{ $row['has_stats'] ? 'good' : 'warn' }}">{{ $row['has_stats'] ? 'Com estatistica' : 'Sem estatistica' }}</span>
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

    <div class="grid" style="grid-template-columns: 1.12fr .88fr; margin-bottom: 18px;">
        <section class="card">
            <h2 style="margin-top: 0;">Ranking operacional</h2>
            <table>
                <thead>
                    <tr>
                        <th>Cartorio</th>
                        <th>IP instaurados</th>
                        <th>Relatados</th>
                        <th>Concluidos</th>
                        <th>Registros</th>
                        <th>Andamento</th>
                        <th>Flagrantes</th>
                        <th>Pendencias</th>
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
                            <td>
                                {{ $row['flagrantes_total'] }}<br>
                                <span class="muted">DDM {{ $row['flagrantes_ddm'] }} | Outras {{ $row['flagrantes_outras'] }}</span>
                            </td>
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
            <h2 style="margin-top: 0;">Resumo do periodo</h2>
            <div class="grid">
                <div class="tag good">IP instaurados: {{ $selectedStats['ip_instaurados'] }}</div>
                <div class="tag good">IP relatados: {{ $selectedStats['ip_relatados'] }}</div>
                <div class="tag good">Concluidos: {{ $selectedStats['concluidos'] }}</div>
                <div class="tag good">Registros: {{ $selectedStats['registros'] }}</div>
                <div class="tag good">IPs em andamento: {{ $selectedStats['ips_andamento'] }}</div>
                <div class="tag good">Flagrantes totais: {{ $selectedStats['flagrantes_total'] }}</div>
            </div>

            <h3 style="margin: 18px 0 10px;">Evolucao mensal</h3>
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

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Base de apoio operacional</h2>
        <p class="muted" style="margin: 0 0 14px;">
            Funcionários, afastamentos e feriados já consolidados para apoiar a leitura da produtividade.
        </p>

        <div class="cards" style="margin-bottom: 16px;">
            <article class="card">
                <small>Funcionários RH</small>
                <strong>{{ $rhSummary['funcionarios_total'] }}</strong>
                <span>{{ $rhSummary['funcionarios_ativos'] }} ativos, {{ $rhSummary['funcionarios_concorrem'] }} concorrendo.</span>
            </article>
            <article class="card">
                <small>Afastamentos</small>
                <strong>{{ $rhSummary['afastamentos_ativos'] }}</strong>
                <span>{{ $rhSummary['afastamentos_em_vigor'] }} em vigor hoje.</span>
            </article>
            <article class="card">
                <small>Feriados</small>
                <strong>{{ $rhSummary['feriados_ativos'] }}</strong>
                <span>{{ $rhSummary['feriados_proximos'] }} próximos no calendário.</span>
            </article>
        </div>

        <div class="grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
            <details open>
                <summary>Funcionários</summary>
                <table style="margin-top: 12px;">
                    <thead>
                        <tr>
                            <th>Funcionario</th>
                            <th>Cargo / Setor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rhFuncionariosPreview as $funcionario)
                            @php($currentAfastamento = $funcionario->currentAfastamento())
                            <tr>
                                <td>
                                    <strong>{{ $funcionario->matricula }}</strong><br>
                                    <span class="muted">{{ $funcionario->name }}</span>
                                </td>
                                <td>
                                    {{ $funcionario->cargo?->name ?: 'Sem cargo' }}<br>
                                    <span class="muted">{{ $funcionario->sector ?: 'Sem setor' }}</span>
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
                                <td colspan="3">Nenhum funcionario carregado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </details>

            <details open>
                <summary>Afastamentos</summary>
                <table style="margin-top: 12px;">
                    <thead>
                        <tr>
                            <th>Funcionario</th>
                            <th>Motivo</th>
                            <th>Vigência</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rhAfastamentosPreview as $afastamento)
                            <tr>
                                <td>{{ $afastamento->funcionario?->matricula }} - {{ $afastamento->funcionario?->name }}</td>
                                <td>{{ $afastamento->reason }}</td>
                                <td>
                                    {{ $afastamento->start_date?->format('d/m/Y') }}<br>
                                    <span class="muted">até {{ $afastamento->end_date?->format('d/m/Y') ?: 'aberto' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">Nenhum afastamento carregado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </details>

            <details open>
                <summary>Feriados</summary>
                <table style="margin-top: 12px;">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Nome</th>
                            <th>Escopo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rhHolidaysPreview as $holiday)
                            <tr>
                                <td>{{ $holiday->holiday_date?->format('d/m/Y') }}</td>
                                <td>{{ $holiday->name }}</td>
                                <td>{{ $holiday->scope }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">Nenhum feriado carregado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </details>
        </div>
    </section>

    <div class="grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 18px;">
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
                    @forelse ($pendingItems->take(12) as $row)
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
                            <td colspan="4">Nenhuma pendencia aberta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Lotes recentes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Arquivo</th>
                        <th>Staged</th>
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
                            <td>{{ (int) $batch->rows_staged }}</td>
                            <td>{{ (int) $batch->rows_updated }}</td>
                            <td>{{ (int) $batch->error_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Nenhum lote recente encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection
