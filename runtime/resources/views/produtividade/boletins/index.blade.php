@extends('layouts.app')

@section('title', 'Boletins de Ocorrencia | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Boletins de ocorrencia (consolidado)</h1>
            <p class="muted" style="margin: 6px 0 0;">Controle unificado de todos os BOs (flagrante e nao-flagrante), com separacao explicita por tipo. Este e o ponto unico de upload do arquivo de consolidacao.</p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('produtividade.boletins.export', array_filter(['cartorio_id' => $selectedCartorio?->id, 'year' => $year, 'month' => $month, 'is_flagrante' => $filters['is_flagrante'] ?? null, 'has_mpu' => $filters['has_mpu'] ?? null, 'without_ip' => $filters['without_ip'] ?? null, 'lavrado_unidade' => $filters['lavrado_unidade'] ?? null], fn ($value) => $value !== null && $value !== '')) }}">Exportar CSV</a>
            <a class="btn secondary" href="{{ route('produtividade.boletins.relatorio', array_filter(['cartorio_id' => $selectedCartorio?->id, 'year' => $year, 'month' => $month, 'is_flagrante' => $filters['is_flagrante'] ?? null, 'has_mpu' => $filters['has_mpu'] ?? null, 'without_ip' => $filters['without_ip'] ?? null, 'lavrado_unidade' => $filters['lavrado_unidade'] ?? null], fn ($value) => $value !== null && $value !== '')) }}">Relatorio A4 (Timbrado Consolidado)</a>
            <a class="btn secondary" href="{{ route('produtividade.flagrantes.index') }}">Fila de flagrantes</a>
        </div>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <form method="GET" action="{{ route('produtividade.boletins.index') }}" class="form-grid">
            <div class="field">
                <label for="cartorio_id">Cartorio</label>
                <select id="cartorio_id" name="cartorio_id">
                    <option value="">Todos os cartorios</option>
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
                    @foreach ([1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Marco', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'] as $index => $label)
                        <option value="{{ $index }}" @selected($month === $index)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="is_flagrante">Tipo</label>
                <select id="is_flagrante" name="is_flagrante">
                    <option value="">Todos</option>
                    <option value="1" @selected(($filters['is_flagrante'] ?? null) === '1')>Somente flagrantes</option>
                    <option value="0" @selected(($filters['is_flagrante'] ?? null) === '0')>Somente nao-flagrantes</option>
                </select>
            </div>
            <div class="field">
                <label for="has_mpu">MPU</label>
                <select id="has_mpu" name="has_mpu">
                    <option value="">Todos</option>
                    <option value="1" @selected(($filters['has_mpu'] ?? null) === '1')>Somente com MPU</option>
                    <option value="0" @selected(($filters['has_mpu'] ?? null) === '0')>Somente sem MPU</option>
                </select>
            </div>
            <div class="field">
                <label for="without_ip">IP</label>
                <select id="without_ip" name="without_ip">
                    <option value="">Todos</option>
                    <option value="1" @selected(($filters['without_ip'] ?? null) === '1')>Somente sem IP</option>
                    <option value="0" @selected(($filters['without_ip'] ?? null) === '0')>Somente com IP</option>
                </select>
            </div>
            <div class="field">
                <label for="lavrado_unidade">Lavrado</label>
                <select id="lavrado_unidade" name="lavrado_unidade">
                    <option value="">Todos</option>
                    <option value="DDM" @selected(($filters['lavrado_unidade'] ?? null) === 'DDM')>DDM</option>
                    <option value="OUTRAS_UNIDADES" @selected(($filters['lavrado_unidade'] ?? null) === 'OUTRAS_UNIDADES')>Outras Unidades</option>
                </select>
            </div>
            <div class="field" style="align-self: end;">
                <button type="submit">Aplicar filtros</button>
            </div>
        </form>
    </section>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Total BOs</small>
            <strong>{{ $totalBoletins }}</strong>
            <span>boletins no recorte atual.</span>
        </article>
        <article class="card">
            <small>Flagrantes</small>
            <strong>{{ $totalFlagrantes }}</strong>
            <span>procedimentos flagranciais.</span>
        </article>
        <article class="card">
            <small>Nao-flagrantes</small>
            <strong>{{ $totalNaoFlagrantes }}</strong>
            <span>ocorrencias sem flagrante.</span>
        </article>
        <article class="card">
            <small>Flagrantes por lavratura</small>
            <strong>DDM {{ $totalDdm }}</strong>
            <span>Outras {{ $totalOutras }}.</span>
        </article>
        <article class="card">
            <small>Com MPU</small>
            <strong>{{ $totalComMpu }}</strong>
            <span>boletins com registro MPU.</span>
        </article>
        <article class="card">
            <small>Sem IP</small>
            <strong>{{ $totalSemIp }}</strong>
            <span>boletins sem numero de IP.</span>
        </article>
        <article class="card">
            <small>MPU sem IP</small>
            <strong>{{ $totalMpuSemIp }}</strong>
            <span>prioridade de saneamento.</span>
        </article>
    </div>

    @if (auth()->user()->hasPermission('produtividade.boletins.manage'))
        <section class="card" style="margin-bottom: 18px;">
            <h2 style="margin-top: 0;">Importar arquivo unico de consolidacao</h2>
            <form method="POST" action="{{ route('produtividade.boletins.import') }}" enctype="multipart/form-data" class="grid">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="cartorio_id" value="{{ $selectedCartorio?->id }}">
                <div class="form-grid">
                    <div class="field">
                        <label for="source_file">Arquivo</label>
                        <input id="source_file" name="source_file" type="file" accept=".csv,.txt,.xlsx" required>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit">Importar</button>
                    <span class="muted">Use sempre este ponto de upload. Os BOs sao consolidados automaticamente; apenas os flagrantes entram na fila de homologacao.</span>
                </div>
            </form>
        </section>
    @endif

    <section class="card" style="margin-bottom: 18px; border-left: 4px solid #8a1f1f;">
        <h2 style="margin-top: 0;">Pendencias criticas (MPU sem IP)</h2>
        <p class="muted" style="margin: 6px 0 14px;">Esta secao destaca boletins com solicitacao de MPU e sem numero de IP. Sao pendencias de alta prioridade para saneamento operacional.</p>
        <table>
            <thead>
                <tr>
                    <th>Mes</th>
                    <th>Data fato</th>
                    <th>Cartorio</th>
                    <th>SPJ</th>
                    <th>MPU</th>
                    <th>Decisao MPU</th>
                    <th>Desp. fund.</th>
                    <th>Encaminhado?</th>
                    <th>IP</th>
                    <th>Atualizado em</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pendenciasCriticas as $pendencia)
                    <tr>
                        <td>{{ str_pad((string) $pendencia->reference_month, 2, '0', STR_PAD_LEFT) }}/{{ $pendencia->reference_year }}</td>
                        <td>{{ $pendencia->data_fato?->format('d/m/Y') ?: '—' }}</td>
                        <td>{{ $pendencia->cartorio ? str_pad((string) $pendencia->cartorio->number, 3, '0', STR_PAD_LEFT).' - '.$pendencia->cartorio->name : '—' }}</td>
                        <td>{{ $pendencia->spj ?: '—' }}</td>
                        <td>{{ $pendencia->mpu_numero ?: '—' }}</td>
                        <td>{{ $pendencia->mpu_decisao ?: '—' }}</td>
                        <td>{{ $pendencia->despacho_fundamentado ? 'Sim' : 'Nao' }}</td>
                        <td>{{ $pendencia->encaminhado_outra_unidade ? 'Sim' : 'Nao' }}</td>
                        <td>{{ $pendencia->num_ip ?: '—' }}</td>
                        <td>{{ $pendencia->updated_at?->format('d/m/Y H:i') ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">Nenhuma pendencia critica (MPU sem IP) no recorte atual.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2 style="margin-top: 0;">Boletins no periodo</h2>
        <p class="muted" style="margin: 6px 0 18px;">Exibindo os 500 registros mais recentes para manter a tela responsiva.</p>
        <table>
            <thead>
                <tr>
                    <th>Mes</th>
                    <th>Data fato</th>
                    <th>Cartorio</th>
                    <th>SPJ</th>
                    <th>Tipo</th>
                    <th>Lavrado</th>
                    <th>MPU</th>
                    <th>Decisao MPU</th>
                    <th>Desp. fund.</th>
                    <th>Encaminhado?</th>
                    <th>IP</th>
                    <th>CNJ</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($boletins as $boletim)
                    <tr>
                        <td>{{ str_pad((string) $boletim->reference_month, 2, '0', STR_PAD_LEFT) }}/{{ $boletim->reference_year }}</td>
                        <td>{{ $boletim->data_fato?->format('d/m/Y') ?: '—' }}</td>
                        <td>{{ $boletim->cartorio ? str_pad((string) $boletim->cartorio->number, 3, '0', STR_PAD_LEFT).' - '.$boletim->cartorio->name : '—' }}</td>
                        <td>{{ $boletim->spj ?: '—' }}</td>
                        <td>
                            @if ($boletim->is_flagrante)
                                <span class="badge danger">Flagrante</span>
                            @else
                                <span class="badge">Nao-flagrante</span>
                            @endif
                        </td>
                        <td>{{ $boletim->lavrado_unidade?->label() ?: '—' }}</td>
                        <td>{{ $boletim->mpu_numero ?: '—' }}</td>
                        <td>{{ $boletim->mpu_decisao ?: '—' }}</td>
                        <td>{{ $boletim->despacho_fundamentado ? 'Sim' : 'Nao' }}</td>
                        <td>{{ $boletim->encaminhado_outra_unidade ? 'Sim' : 'Nao' }}</td>
                        <td>{{ $boletim->num_ip ?: '—' }}</td>
                        <td>{{ $boletim->num_cnj ?: '—' }}</td>
                        <td>
                            @if (auth()->user()->hasPermission('produtividade.boletins.manage'))
                                <a class="btn secondary" href="{{ route('produtividade.boletins.edit', ['boletim' => $boletim->id, 'cartorio_id' => $selectedCartorio?->id, 'year' => $year, 'month' => $month]) }}">Editar</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12">Nenhum boletim encontrado para os filtros selecionados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
