<x-report.default
    title="Relatório de Plantões Externos"
    :period="$periodoLabel"
    :generatedAt="now()"
    origin="Escalas / Plantões"
    footer-note="Cartório Central - Gerenciamento"
    :total-pages="1"
>
    <x-slot:toolbar>
        <a href="{{ route('escalas.plantoes') }}" class="btn secondary" style="font-size:0.85rem;">← Voltar</a>

        <form method="GET" action="{{ route('escalas.plantoes.relatorio') }}"
              style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; flex:1;">
            <div>
                <label style="font-size:0.75rem; display:block; margin-bottom:2px; color:#ccc;">Funcionário</label>
                <select name="funcionario_id" style="font-size:0.82rem; padding:3px 6px;">
                    <option value="">Todos</option>
                    @foreach ($funcionarios as $f)
                        <option value="{{ $f->id }}" @selected(($filters['funcionario_id'] ?? '') === $f->id)>{{ $f->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:0.75rem; display:block; margin-bottom:2px; color:#ccc;">Tipo de Plantão</label>
                <select name="plantao_id" style="font-size:0.82rem; padding:3px 6px;">
                    <option value="">Todos</option>
                    @foreach ($catalogo as $c)
                        <option value="{{ $c->id }}" @selected(($filters['plantao_id'] ?? '') === $c->id)>{{ $c->nome }} ({{ $c->sigla }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:0.75rem; display:block; margin-bottom:2px; color:#ccc;">Ano</label>
                <input name="year" type="number" min="2020" max="2100" value="{{ $year }}" style="font-size:0.82rem; padding:3px 6px; width:80px;">
            </div>
            <div>
                <label style="font-size:0.75rem; display:block; margin-bottom:2px; color:#ccc;">Mês</label>
                <select name="month" style="font-size:0.82rem; padding:3px 6px;">
                    <option value="0" @selected($month === 0)>Todos</option>
                    @foreach ([1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'] as $i => $l)
                        <option value="{{ $i }}" @selected($month === $i)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" style="font-size:0.82rem; padding:4px 12px;">Aplicar</button>
        </form>

        <a href="{{ route('escalas.plantoes.relatorio', $filters) }}"
           style="font-size:0.85rem; white-space:nowrap; display:inline-flex; align-items:center;">
            Reabrir
        </a>
    </x-slot:toolbar>

    <x-slot:summary>
        <article class="card">
            <small>Total de plantões</small>
            <strong>{{ $totalGeral }}</strong>
        </article>
        <article class="card">
            <small>Servidores envolvidos</small>
            <strong>{{ $porFuncionario->count() }}</strong>
        </article>
        @foreach ($porTipo as $t)
            <article class="card">
                <small>{{ $t['tipo'] }} ({{ $t['sigla'] }})</small>
                <strong>{{ $t['total'] }}</strong>
            </article>
        @endforeach
    </x-slot:summary>

    <style>
        .report-section {
            display: grid;
            gap: 14px;
        }
        .section-title {
            font-size: 10.5pt;
            font-weight: 700;
            margin: 2mm 0 1mm;
            border-bottom: 0.8px solid #555;
            padding-bottom: 2mm;
        }
        .report-note {
            font-size: 8.5pt;
            color: #555;
            line-height: 1.5;
        }
        .summary-block {
            margin-bottom: 3mm;
        }
        .report-table {
            font-size: 8.5pt;
        }
        .report-table th {
            text-transform: none;
            letter-spacing: 0;
            font-size: 8pt;
        }
        .report-table td {
            vertical-align: top;
        }
        .muted-row {
            color: #666;
            font-style: italic;
            text-align: center;
        }
    </style>

    <section class="report-section">
        <div class="summary-block">
            <div class="section-title">Resumo</div>
            <p class="report-note">
                Período: <strong>{{ $periodoLabel }}</strong>
                @if ($funcionario)
                    · Servidor: <strong>{{ $funcionario->name }}</strong>
                @else
                    · Todos os servidores da DDM
                @endif
                @if ($plantaoFiltro)
                    · Tipo: <strong>{{ $plantaoFiltro->nome }}</strong>
                @endif
                · Emitido em {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
            </p>
        </div>

        @if (! $funcionario)
            <div>
                <div class="section-title">Resumo por Servidor</div>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Servidor</th>
                            <th>Cargo</th>
                            @foreach ($porTipo as $t)
                                <th class="td-center">{{ $t['sigla'] }}</th>
                            @endforeach
                            <th class="td-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porFuncionario as $pf)
                            <tr>
                                <td><strong>{{ $pf['funcionario']?->name ?: '—' }}</strong></td>
                                <td>{{ $pf['funcionario']?->cargo?->name ?: '—' }}</td>
                                @foreach ($porTipo as $t)
                                    <td class="td-center">{{ $pf['por_tipo'][$t['tipo']] ?? '—' }}</td>
                                @endforeach
                                <td class="td-total td-center">{{ $pf['total'] }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="{{ 2 + $porTipo->count() }}" class="td-total td-right">TOTAL GERAL:</td>
                            <td class="td-total td-center">{{ $totalGeral }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        <div>
            <div class="section-title">Detalhe Cronológico {{ $funcionario ? '— ' . $funcionario->name : '' }}</div>

            @forelse ($porFuncionario as $pf)
                @if (! $funcionario)
                    <p style="font-weight:bold; font-size:9pt; margin: 10px 0 4px;">
                        {{ $pf['funcionario']?->name }} — {{ $pf['total'] }} plantão(ões)
                    </p>
                @endif
                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width:14%">Data</th>
                            <th style="width:30%">Tipo de Plantão</th>
                            <th style="width:10%">Sigla</th>
                            <th>Unidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pf['registros'] as $r)
                            <tr>
                                <td class="td-center">{{ $r->data?->format('d/m/Y') }}</td>
                                <td>{{ $r->plantaoExterno?->nome ?: '—' }}</td>
                                <td class="td-center">{{ $r->plantaoExterno?->sigla ?: '—' }}</td>
                                <td>{{ $r->plantaoExterno?->unidade ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @empty
                <p class="muted-row">Nenhum plantão externo registrado no período selecionado.</p>
            @endforelse
        </div>
    </section>
</x-report.default>
