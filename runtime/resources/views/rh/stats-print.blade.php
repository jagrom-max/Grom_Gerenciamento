@php
    $brasaoSrc = \App\Support\ReportAsset::dataUri('assets/brasao.png');
    $logoSrc = \App\Support\ReportAsset::dataUri('assets/logo_grom.png');
    $watermarkSrc = \App\Support\ReportAsset::dataUri('assets/marca_dagua.png');
@endphp

<x-report.default
    title="Estatísticas RH"
    :period="$hoje->format('d/m/Y')"
    :generatedAt="now()"
    origin="RH / Recursos Humanos"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="$brasaoSrc"
    :logo-src="$logoSrc"
    :watermark-src="$watermarkSrc"
>
    <x-slot:toolbar>
        <button onclick="window.print()">Imprimir / Salvar PDF</button>
        <a href="{{ route('rh.stats') }}">← Voltar</a>
        <span style="color:var(--ink-soft); font-size:.85em;">Estatísticas de {{ $hoje->format('d/m/Y') }}</span>
    </x-slot:toolbar>

    <x-slot:summary>
        <article class="card">
            <small>Efetivo ativo</small>
            <strong>{{ $totalFuncionarios }}</strong>
            <span>{{ $concorremEscala }} na escala</span>
        </article>
        <article class="card">
            <small>Afastados hoje</small>
            <strong>{{ $emAfastamentoHoje }}</strong>
            <span>{{ $totalFuncionarios > 0 ? round($emAfastamentoHoje / $totalFuncionarios * 100) : 0 }}% do efetivo</span>
        </article>
        <article class="card">
            <small>Disponíveis hoje</small>
            <strong>{{ $totalFuncionarios - $emAfastamentoHoje }}</strong>
            <span>em atividade regular</span>
        </article>
        <article class="card">
            <small>Feriados (90 dias)</small>
            <strong>{{ $feriadosProximos->count() }}</strong>
            <span>
                @if ($feriadosProximos->isNotEmpty())
                    Próx: {{ $feriadosProximos->first()->holiday_date->format('d/m') }}
                @else
                    Nenhum no período
                @endif
            </span>
        </article>
        <article class="card">
            <small>Referência</small>
            <strong style="font-size: 12pt;">{{ $hoje->format('d/m/Y') }}</strong>
            <span>{{ $hoje->locale('pt_BR')->dayName }}</span>
        </article>
    </x-slot:summary>

    <style>
        .rh-section {
            margin-top: 4mm;
        }
        .rh-section h2 {
            font-size: 10pt;
            margin: 0 0 2mm;
            padding-bottom: 1.5mm;
            border-bottom: 1px solid var(--line);
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .rh-table {
            table-layout: fixed;
            font-size: 8pt;
            margin-bottom: 4mm;
        }
        .rh-table th {
            font-size: 7.6pt;
            text-transform: none;
            letter-spacing: 0;
        }
        .badge-red  { color: #c0392b; font-weight: 700; }
        .badge-green { color: #27ae60; font-weight: 700; }
        .badge-orange { color: #e67e22; font-weight: 700; }
        .trend-table { margin-bottom: 4mm; }
        .trend-bar-cell { text-align: center; vertical-align: bottom; padding: 2px 4px; }
        .col2 { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; margin-top: 3mm; }
        .summary-inline {
            font-size: 8pt;
            color: var(--ink-soft);
            margin: 2mm 0 1mm;
        }
    </style>

    <section>
        <div class="summary-inline">
            Painel consolidado de efetivo, afastamentos, feriados e tendência mensal.
        </div>

        <div class="col2">
            <div class="rh-section">
                <h2>Efetivo por cargo</h2>
                @if ($headcountPorCargo->isEmpty())
                    <p style="font-size: 8pt; color: var(--ink-soft);">Sem dados.</p>
                @else
                    <table class="rh-table">
                        <thead>
                            <tr>
                                <th>Cargo</th>
                                <th style="text-align:right;">Total</th>
                                <th style="text-align:right;">Escala</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($headcountPorCargo as $item)
                                <tr>
                                    <td>{{ $item['cargo'] }}</td>
                                    <td style="text-align:right; font-weight:bold;">{{ $item['total'] }}</td>
                                    <td style="text-align:right;">{{ $item['escala'] ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="rh-section">
                <h2>Afastamentos hoje — por motivo</h2>
                @if ($porMotivo->isEmpty())
                    <p style="font-size: 8.5pt; color: #27ae60; font-weight: 700;">Efetivo completo em atividade. ✓</p>
                @else
                    <table class="rh-table">
                        <thead>
                            <tr>
                                <th>Motivo</th>
                                <th style="text-align:right;">Qtd.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($porMotivo as $item)
                                <tr>
                                    <td>{{ $item['reason'] }}</td>
                                    <td style="text-align:right; font-weight:bold;">{{ $item['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        @if ($afastados->isNotEmpty())
            <div class="rh-section">
                <h2>Afastados hoje ({{ $afastados->count() }})</h2>
                <table class="rh-table">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Cargo</th>
                            <th>Motivo</th>
                            <th style="text-align:center;">Início</th>
                            <th style="text-align:center;">Término</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($afastados->sortBy('funcionario.name') as $af)
                            <tr>
                                <td>{{ $af->funcionario?->name ?? '—' }}</td>
                                <td>{{ $af->funcionario?->cargo?->name ?? '—' }}</td>
                                <td>{{ $af->reason }}</td>
                                <td style="text-align:center;">{{ $af->start_date->format('d/m/Y') }}</td>
                                <td style="text-align:center;">{{ $af->end_date?->format('d/m/Y') ?? 'Sem previsão' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($agendados->isNotEmpty())
            <div class="rh-section">
                <h2>Agendados — próximos 60 dias ({{ $agendados->count() }})</h2>
                <table class="rh-table">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Cargo</th>
                            <th>Motivo</th>
                            <th style="text-align:center;">Início</th>
                            <th style="text-align:center;">Término</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agendados as $af)
                            <tr>
                                <td>{{ $af->funcionario?->name ?? '—' }}</td>
                                <td>{{ $af->funcionario?->cargo?->name ?? '—' }}</td>
                                <td>{{ $af->reason }}</td>
                                <td style="text-align:center;">{{ $af->start_date->format('d/m/Y') }}</td>
                                <td style="text-align:center;">{{ $af->end_date?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($feriadosProximos->isNotEmpty())
            <div class="rh-section">
                <h2>Feriados — próximos 90 dias</h2>
                <table class="rh-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Dia da semana</th>
                            <th>Feriado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($feriadosProximos as $feriado)
                            <tr>
                                <td>{{ $feriado->holiday_date->format('d/m/Y') }}</td>
                                <td>{{ $feriado->holiday_date->locale('pt_BR')->dayName }}</td>
                                <td>{{ $feriado->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="rh-section">
            <h2>Tendência — afastamentos ativos por mês (últimos 12 meses)</h2>
            @php $maxTrend = collect($trend)->max('count') ?: 1; @endphp
            <table class="rh-table trend-table">
                <thead>
                    <tr>
                        @foreach ($trend as $t)
                            <th style="text-align:center; font-size: 7.5pt; font-weight: normal;">{{ $t['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach ($trend as $t)
                            @php
                                $pct = round($t['count'] / $maxTrend * 100);
                                $color = $pct >= 75 ? '#c0392b' : ($pct >= 40 ? '#e67e22' : '#27ae60');
                            @endphp
                            <td class="trend-bar-cell">
                                <div style="font-weight: bold; font-size: 8pt; color: {{ $color }}; margin-bottom: 2px;">{{ $t['count'] }}</div>
                                <div style="height: {{ max(3, $pct / 3) }}px; background: {{ $color }}; border-radius: 2px 2px 0 0; width: 100%; opacity: 0.8;"></div>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</x-report.default>
