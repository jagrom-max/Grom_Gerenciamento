@extends('layouts.app')

@php
    $yearTotal = (int) $yearBreakdown->sum('flagrantes_total');
    $yearDdm = (int) $yearBreakdown->sum('flagrantes_ddm');
    $yearOutras = (int) $yearBreakdown->sum('flagrantes_outras');
@endphp

@section('title', 'Flagrantes | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Flagrantes e fila de confirmacao</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Fila de sugestoes da Analise de Dados, cadastro manual e fechamento mensal separado entre DDM e outras unidades.
            </p>
        </div>
        <div class="actions">
            @if (auth()->user()->hasPermission('produtividade.stats.view'))
                <a class="btn secondary" href="{{ route('produtividade.stats.index') }}">Estatisticas</a>
            @endif
            <a class="btn secondary" href="{{ route('relatorios.index') }}">Relatorios</a>
        </div>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <form method="GET" action="{{ route('produtividade.flagrantes.index') }}" class="form-grid">
            <div class="field">
                <label for="cartorio_id">Cartorio</label>
                <select id="cartorio_id" name="cartorio_id">
                    @foreach ($cartorios as $cartorio)
                        <option value="{{ $cartorio->id }}" @selected($selectedCartorio?->id === $cartorio->id)>
                            {{ str_pad((string) $cartorio->number, 3, '0', STR_PAD_LEFT) }} - {{ $cartorio->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="year">Ano</label>
                <input id="year" name="year" type="number" min="2020" max="2100" value="{{ $year }}">
            </div>
            <div class="field">
                <label for="month">Mes</label>
                <select id="month" name="month">
                    <option value="0" @selected($month === 0)>Todos</option>
                    @foreach ([
                        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Marco', 4 => 'Abril',
                        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
                    ] as $index => $label)
                        <option value="{{ $index }}" @selected($month === $index)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="align-self: end;">
                <button type="submit">Aplicar filtros</button>
            </div>
        </form>
    </section>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Fila pendente</small>
            <strong>{{ $pendingItems->count() }}</strong>
            <span>Global do cartorio selecionado, sem depender do periodo filtrado.</span>
        </article>
        <article class="card">
            <small>Saneamento pendente</small>
            <strong>{{ $unassignedPendingCount }}</strong>
            <span>Sugestoes sem cartorio mapeado aguardando vinculacao manual.</span>
        </article>
        <article class="card">
            <small>Periodo selecionado</small>
            <strong>{{ $selectedStats['total'] }}</strong>
            <span>DDM {{ $selectedStats['ddm'] }} | Outras {{ $selectedStats['outras'] }}.</span>
        </article>
        <article class="card">
            <small>Fechamento anual</small>
            <strong>{{ $yearTotal }}</strong>
            <span>DDM {{ $yearDdm }} | Outras {{ $yearOutras }}.</span>
        </article>
    </div>

    @if (auth()->user()->hasPermission('produtividade.flagrantes.manage'))
        <section class="card" style="margin-bottom: 18px;">
            <h2 style="margin-top: 0;">Ingestao de arquivo unico</h2>
            <p class="muted" style="margin: 6px 0 14px;">
                O upload do arquivo de consolidacao agora fica centralizado em <strong>Boletins</strong>.
                A fila de flagrantes continua sendo alimentada automaticamente a partir desse mesmo arquivo unico.
            </p>
            <div class="actions" style="margin-bottom: 12px;">
                <a class="btn" href="{{ route('produtividade.boletins.index', ['cartorio_id' => $selectedCartorio?->id, 'year' => $year, 'month' => $month]) }}">Ir para upload em Boletins</a>
            </div>
            @if (config('grom_legacy.enabled'))
            <form method="POST" action="{{ route('produtividade.flagrantes.sync-legacy') }}">
                @csrf
                <input type="hidden" name="filter_year" value="{{ $year }}">
                <input type="hidden" name="filter_month" value="{{ $month }}">
                <div class="actions">
                    <button type="submit" class="secondary">Sincronizar base legada</button>
                    <span class="muted">Opcional: leitura direta e somente leitura do SQLite legado da Analise de Dados.</span>
                </div>
            </form>
            @endif
        </section>
    @endif

    @if (auth()->user()->hasPermission('produtividade.flagrantes.manage'))
        <section class="card" style="margin-bottom: 18px;">
            <h2 style="margin-top: 0;">Pendencias sem cartorio mapeado</h2>
            <p class="muted" style="margin: 6px 0 18px;">
                Aqui entram as sugestoes que chegaram da consolidacao, mas nao conseguiram identificar o cartorio automaticamente.
                Exibindo as 25 mais recentes.
            </p>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>SPJ</th>
                        <th>Natureza(s)</th>
                        <th>Cartorio informado</th>
                        <th>Lavrado</th>
                        <th>IP</th>
                        <th>IP-e</th>
                        <th>CNJ</th>
                        <th>Designar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($unassignedPendingItems as $item)
                        <tr>
                            <td>{{ $item->data_fato?->format('d/m/Y') }}</td>
                            <td>{{ $item->spj ?: $item->source_process_key }}</td>
                            <td>{{ $item->naturezas }}</td>
                            <td>{{ $item->cartorio_hint ?: 'Nao informado' }}</td>
                            <td>{{ $item->lavrado_unidade?->label() }}</td>
                            <td>{{ $item->num_ip }}</td>
                            <td>{{ $item->num_ipe }}</td>
                            <td>{{ $item->num_cnj }}</td>
                            <td>
                                <form method="POST" action="{{ route('produtividade.flagrantes.assign-cartorio', $item) }}">
                                    @csrf
                                    <input type="hidden" name="filter_cartorio_id" value="{{ $selectedCartorio?->id }}">
                                    <input type="hidden" name="filter_year" value="{{ $year }}">
                                    <input type="hidden" name="filter_month" value="{{ $month }}">
                                    <div class="actions">
                                        <select name="cartorio_id" required>
                                            <option value="">Selecionar</option>
                                            @foreach ($cartorios as $cartorio)
                                                <option value="{{ $cartorio->id }}">
                                                    {{ str_pad((string) $cartorio->number, 3, '0', STR_PAD_LEFT) }} - {{ $cartorio->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit">Vincular</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">Nenhuma pendencia sem cartorio mapeado no momento.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if ($unassignedPendingCount > $unassignedPendingItems->count())
                <p class="muted" style="margin: 12px 0 0;">
                    Existem {{ $unassignedPendingCount - $unassignedPendingItems->count() }} pendencias adicionais fora desta amostra.
                </p>
            @endif
        </section>
    @endif

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Ultimos lotes importados</h2>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Arquivo</th>
                    <th>Total</th>
                    <th>Staged</th>
                    <th>Atualizados</th>
                    <th>Ignorados</th>
                    <th>Erros</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentBatches as $batch)
                    <tr>
                        <td>{{ $batch->imported_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $batch->source_name }}</td>
                        <td>{{ (int) $batch->total_rows }}</td>
                        <td>{{ (int) $batch->rows_staged }}</td>
                        <td>{{ (int) $batch->rows_updated }}</td>
                        <td>{{ (int) $batch->rows_skipped }}</td>
                        <td>{{ (int) $batch->error_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">Nenhum lote importado ate o momento.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @if ($selectedCartorio && auth()->user()->hasPermission('produtividade.flagrantes.manage'))
        <section class="card" style="margin-bottom: 18px;">
            <h2 style="margin-top: 0;">Cadastro manual de flagrante</h2>
            <form method="POST" action="{{ route('produtividade.flagrantes.store-manual') }}" class="grid">
                @csrf
                <input type="hidden" name="cartorio_id" value="{{ $selectedCartorio->id }}">
                <input type="hidden" name="filter_year" value="{{ $year }}">
                <input type="hidden" name="filter_month" value="{{ $month }}">
                <div class="form-grid">
                    <div class="field">
                        <label for="spj">N SPJ</label>
                        <input id="spj" name="spj" type="text">
                    </div>
                    <div class="field">
                        <label for="naturezas">Natureza(s)</label>
                        <input id="naturezas" name="naturezas" type="text" placeholder="Separe por ; quando houver mais de uma">
                    </div>
                    <div class="field">
                        <label for="num_ip">N IP</label>
                        <input id="num_ip" name="num_ip" type="text">
                    </div>
                    <div class="field">
                        <label for="num_ipe">N IP-e</label>
                        <input id="num_ipe" name="num_ipe" type="text">
                    </div>
                    <div class="field">
                        <label for="num_cnj">N CNJ</label>
                        <input id="num_cnj" name="num_cnj" type="text">
                    </div>
                    <div class="field">
                        <label for="data_fato">Data do fato</label>
                        <input id="data_fato" name="data_fato" type="date" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="field">
                        <label for="lavrado_unidade">Lavrado</label>
                        <select id="lavrado_unidade" name="lavrado_unidade" required>
                            <option value="DDM">DDM</option>
                            <option value="OUTRAS_UNIDADES">Outras Unidades</option>
                        </select>
                    </div>
                    <div class="field full">
                        <label for="notes">Observacoes</label>
                        <input id="notes" name="notes" type="text">
                    </div>
                </div>
                <div class="actions">
                    <button type="submit">Registrar flagrante</button>
                    <span class="muted">Se o mesmo SPJ, IP ou CNJ ja existir no cartorio, o sistema complementa o registro em vez de duplicar.</span>
                </div>
            </form>
        </section>
    @endif

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Sugestoes pendentes da Analise de Dados</h2>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>SPJ</th>
                    <th>Natureza(s)</th>
                    <th>Lavrado</th>
                    <th>IP</th>
                    <th>IP-e</th>
                    <th>CNJ</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pendingItems as $item)
                    <tr>
                        <td>{{ $item->data_fato?->format('d/m/Y') }}</td>
                        <td>{{ $item->spj ?: $item->source_process_key }}</td>
                        <td>{{ $item->naturezas }}</td>
                        <td>{{ $item->lavrado_unidade?->label() }}</td>
                        <td>{{ $item->num_ip }}</td>
                        <td>{{ $item->num_ipe }}</td>
                        <td>{{ $item->num_cnj }}</td>
                        <td>
                            <div class="actions">
                                @if (auth()->user()->hasPermission('produtividade.flagrantes.confirm'))
                                    <form method="POST" action="{{ route('produtividade.flagrantes.confirm', $item) }}">
                                        @csrf
                                        <input type="hidden" name="filter_year" value="{{ $year }}">
                                        <input type="hidden" name="filter_month" value="{{ $month }}">
                                        <button type="submit">Confirmar</button>
                                    </form>
                                    <form method="POST" action="{{ route('produtividade.flagrantes.reject', $item) }}">
                                        @csrf
                                        <input type="hidden" name="filter_year" value="{{ $year }}">
                                        <input type="hidden" name="filter_month" value="{{ $month }}">
                                        <input type="hidden" name="rejected_reason" value="Rejeitado manualmente no piloto web de flagrantes.">
                                        <button type="submit" class="secondary">Rejeitar</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">Nenhuma sugestao pendente para o cartorio selecionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Flagrantes confirmados no periodo</h2>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Origem</th>
                    <th>SPJ</th>
                    <th>Natureza(s)</th>
                    <th>IP</th>
                    <th>IP-e</th>
                    <th>CNJ</th>
                    <th>Entrada</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($confirmedFlagrantes as $flagrante)
                    <tr>
                        <td>{{ $flagrante->data_fato?->format('d/m/Y') }}</td>
                        <td>{{ $flagrante->lavrado_unidade?->label() }}</td>
                        <td>{{ $flagrante->spj }}</td>
                        <td>{{ $flagrante->naturezas }}</td>
                        <td>{{ $flagrante->num_ip }}</td>
                        <td>{{ $flagrante->num_ipe }}</td>
                        <td>{{ $flagrante->num_cnj }}</td>
                        <td>{{ $flagrante->source_item_id ? 'Fila de importacao' : 'Manual' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">Nenhum flagrante confirmado no periodo selecionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2 style="margin-top: 0;">Fechamento mensal do ano</h2>
        <table>
            <thead>
                <tr>
                    <th>Mes</th>
                    <th>DDM</th>
                    <th>Outras</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ([
                    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Marco', 4 => 'Abril',
                    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
                ] as $index => $label)
                    @php($row = $yearBreakdown->get($index))
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ (int) ($row?->flagrantes_ddm ?? 0) }}</td>
                        <td>{{ (int) ($row?->flagrantes_outras ?? 0) }}</td>
                        <td>{{ (int) ($row?->flagrantes_total ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection
