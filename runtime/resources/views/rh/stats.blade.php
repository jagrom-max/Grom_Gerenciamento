@extends('layouts.app')

@section('title', 'Estatísticas RH | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Estatísticas — Recursos Humanos</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Painel analítico de efetivo, afastamentos e feriados. Referência: {{ $hoje->format('d/m/Y') }}.
            </p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('rh.stats.print') }}" target="_blank">Imprimir A4</a>
            <a class="btn secondary" href="{{ route('rh.composicao') }}">Composição</a>
            <a class="btn secondary" href="{{ route('rh.confronto') }}">Confronto</a>
            <a class="btn secondary" href="{{ route('rh.index') }}">← RH/Admin</a>
        </div>
    </div>

    {{-- Cards de resumo --}}
    <div class="cards" style="margin-bottom: 20px;">
        <article class="card">
            <small>Efetivo ativo</small>
            <strong>{{ $totalFuncionarios }}</strong>
            <span>{{ $concorremEscala }} concorrem à escala.</span>
        </article>
        <article class="card">
            <small>Em afastamento hoje</small>
            <strong style="color: {{ $emAfastamentoHoje > 0 ? '#c0392b' : 'inherit' }}">{{ $emAfastamentoHoje }}</strong>
            <span>{{ $totalFuncionarios > 0 ? round($emAfastamentoHoje / $totalFuncionarios * 100) : 0 }}% do efetivo ativo.</span>
        </article>
        <article class="card">
            <small>Disponíveis hoje</small>
            <strong style="color: #27ae60">{{ $totalFuncionarios - $emAfastamentoHoje }}</strong>
            <span>Presentes para serviço regular.</span>
        </article>
        <article class="card">
            <small>Feriados (próx. 90 dias)</small>
            <strong>{{ $feriadosProximos->count() }}</strong>
            @if ($feriadosProximos->isNotEmpty())
                <span>Próximo: {{ $feriadosProximos->first()->holiday_date->format('d/m') }} — {{ $feriadosProximos->first()->name }}</span>
            @else
                <span>Nenhum feriado cadastrado no período.</span>
            @endif
        </article>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 18px;">

        {{-- Headcount por cargo --}}
        <section class="card">
            <h2 style="margin-top: 0; font-size: 1rem;">Efetivo por cargo</h2>
            @if ($headcountPorCargo->isEmpty())
                <p class="muted">Sem dados de efetivo por cargo.</p>
            @else
                <table style="font-size: 0.88rem;">
                    <thead>
                        <tr>
                            <th>Cargo</th>
                            <th style="text-align: right;">Total</th>
                            <th style="text-align: right;">Escala</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($headcountPorCargo as $item)
                            <tr>
                                <td>{{ $item['cargo'] }}</td>
                                <td style="text-align: right; font-weight: 600;">{{ $item['total'] }}</td>
                                <td style="text-align: right;">
                                    @if ($item['escala'] > 0)
                                        <span class="tag good">{{ $item['escala'] }}</span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        {{-- Afastamentos hoje por motivo --}}
        <section class="card">
            <h2 style="margin-top: 0; font-size: 1rem;">Afastamentos hoje — por motivo</h2>
            @if ($porMotivo->isEmpty())
                <p class="muted" style="color: #27ae60; font-weight: 600;">
                    Nenhum afastamento em vigor hoje. ✓
                </p>
            @else
                <table style="font-size: 0.88rem;">
                    <thead>
                        <tr>
                            <th>Motivo</th>
                            <th style="text-align: right;">Qtd.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porMotivo as $item)
                            <tr>
                                <td>{{ $item['reason'] }}</td>
                                <td style="text-align: right; font-weight: 600;">{{ $item['count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 18px;">

        {{-- Afastados hoje (detalhado) --}}
        <section class="card">
            <h2 style="margin-top: 0; font-size: 1rem;">Afastados hoje ({{ $afastados->count() }})</h2>
            @if ($afastados->isEmpty())
                <p class="muted" style="color: #27ae60;">Efetivo completo em atividade. ✓</p>
            @else
                <div style="overflow-x: auto;">
                    <table style="font-size: 0.82rem;">
                        <thead>
                            <tr>
                                <th>Funcionário</th>
                                <th>Cargo</th>
                                <th>Motivo</th>
                                <th>Até</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($afastados->sortBy('funcionario.name') as $af)
                                <tr>
                                    <td>{{ $af->funcionario?->short_name ?? $af->funcionario?->name ?? '—' }}</td>
                                    <td>{{ $af->funcionario?->cargo?->name ?? '—' }}</td>
                                    <td>{{ $af->reason }}</td>
                                    <td>
                                        @if ($af->end_date)
                                            {{ $af->end_date->format('d/m/Y') }}
                                        @else
                                            <span class="muted">Sem previsão</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Afastamentos agendados (próximos 60 dias) --}}
        <section class="card">
            <h2 style="margin-top: 0; font-size: 1rem;">Agendados — próximos 60 dias ({{ $agendados->count() }})</h2>
            @if ($agendados->isEmpty())
                <p class="muted">Nenhum afastamento agendado para os próximos 60 dias.</p>
            @else
                <div style="overflow-x: auto;">
                    <table style="font-size: 0.82rem;">
                        <thead>
                            <tr>
                                <th>Funcionário</th>
                                <th>Cargo</th>
                                <th>Motivo</th>
                                <th>Início</th>
                                <th>Fim</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($agendados as $af)
                                <tr>
                                    <td>{{ $af->funcionario?->short_name ?? $af->funcionario?->name ?? '—' }}</td>
                                    <td>{{ $af->funcionario?->cargo?->name ?? '—' }}</td>
                                    <td>{{ $af->reason }}</td>
                                    <td>{{ $af->start_date->format('d/m/Y') }}</td>
                                    <td>
                                        @if ($af->end_date)
                                            {{ $af->end_date->format('d/m/Y') }}
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 18px;">

        {{-- Feriados próximos --}}
        <section class="card">
            <h2 style="margin-top: 0; font-size: 1rem;">Feriados — próximos 90 dias</h2>
            @if ($feriadosProximos->isEmpty())
                <p class="muted">Nenhum feriado cadastrado no período.</p>
            @else
                <table style="font-size: 0.85rem;">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Dia</th>
                            <th>Feriado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($feriadosProximos as $feriado)
                            <tr>
                                <td>{{ $feriado->holiday_date->format('d/m/Y') }}</td>
                                <td class="muted">{{ $feriado->holiday_date->locale('pt_BR')->dayName }}</td>
                                <td>{{ $feriado->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        {{-- Setores com afastamentos hoje --}}
        <section class="card">
            <h2 style="margin-top: 0; font-size: 1rem;">Afastamentos por setor hoje</h2>
            @if ($setoresAfastados->isEmpty())
                <p class="muted" style="color: #27ae60;">Todos os setores com efetivo completo. ✓</p>
            @else
                <table style="font-size: 0.85rem;">
                    <thead>
                        <tr>
                            <th>Setor</th>
                            <th style="text-align: right;">Afastados</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($setoresAfastados as $item)
                            <tr>
                                <td>{{ $item['setor'] }}</td>
                                <td style="text-align: right; font-weight: 600;">{{ $item['count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

    </div>

    {{-- Tendência mensal --}}
    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0; font-size: 1rem;">Tendência — afastamentos ativos por mês (últimos 12 meses)</h2>
        <div style="overflow-x: auto;">
            <table style="font-size: 0.85rem; min-width: 560px;">
                <thead>
                    <tr>
                        @foreach ($trend as $t)
                            <th style="text-align: center; font-weight: normal; font-size: 0.78rem;">{{ $t['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @php $maxTrend = collect($trend)->max('count') ?: 1; @endphp
                        @foreach ($trend as $t)
                            @php
                                $pct = round($t['count'] / $maxTrend * 100);
                                $color = $pct >= 75 ? '#c0392b' : ($pct >= 40 ? '#e67e22' : '#27ae60');
                            @endphp
                            <td style="text-align: center; vertical-align: bottom; padding-bottom: 4px;">
                                <div style="font-weight: 600; margin-bottom: 4px; color: {{ $color }};">{{ $t['count'] }}</div>
                                <div style="height: {{ max(4, $pct) }}px; background: {{ $color }}; border-radius: 3px 3px 0 0; margin: 0 auto; width: 28px; opacity: 0.7;"></div>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection
