@extends('layouts.app')

@section('title', 'Estatísticas de BOs | Grom.Seg')

@push('styles')
<style>
/* ─── Barras de progresso ─────────────────────────────── */
.stat-bar-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.stat-bar-track {
    flex: 1;
    height: 8px;
    background: #e8edf2;
    border-radius: 4px;
    overflow: hidden;
    min-width: 60px;
}
.stat-bar-fill {
    height: 100%;
    border-radius: 4px;
    background: #3b82f6;
    transition: width .3s ease;
}
.stat-bar-fill.danger  { background: #ef4444; }
.stat-bar-fill.warn    { background: #f59e0b; }
.stat-bar-fill.success { background: #10b981; }
.stat-bar-fill.purple  { background: #8b5cf6; }
.stat-bar-fill.teal    { background: #0d9488; }

/* ─── Bar chart mensal ───────────────────────────────── */
.bar-chart {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    height: 80px;
    overflow-x: auto;
    padding-bottom: 4px;
}
.bar-chart-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    flex: 1;
    min-width: 22px;
    max-width: 44px;
}
.bar-chart-bar {
    width: 100%;
    background: #3b82f6;
    border-radius: 3px 3px 0 0;
    min-height: 2px;
    position: relative;
    cursor: default;
}
.bar-chart-bar .bar-flag {
    display: block;
    background: #ef4444;
    border-radius: 3px 3px 0 0;
    width: 100%;
    position: absolute;
    bottom: 0;
    min-height: 2px;
}
.bar-chart-label {
    font-size: 0.62rem;
    color: #9ca3af;
    white-space: nowrap;
    transform: rotate(-45deg);
    transform-origin: top center;
    margin-top: 6px;
}

/* ─── Dia semana chart ───────────────────────────────── */
.dia-chart {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    align-items: flex-end;
    height: 60px;
}
.dia-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
}
.dia-bar {
    width: 100%;
    background: #8b5cf6;
    border-radius: 3px 3px 0 0;
    min-height: 3px;
}
.dia-label {
    font-size: 0.68rem;
    color: #6b7280;
    text-align: center;
}

/* ─── Cards de KPI ───────────────────────────────────── */
.kpi-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    color: #111827;
}
.kpi-value.blue   { color: #2563eb; }
.kpi-value.green  { color: #059669; }
.kpi-value.red    { color: #dc2626; }
.kpi-value.amber  { color: #d97706; }

/* ─── Tabelas compactas ──────────────────────────────── */
.table-compact td, .table-compact th {
    padding: 5px 10px;
    font-size: 0.82rem;
}
.pct-badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
    background: #eff6ff;
    color: #2563eb;
}
.pct-badge.high  { background: #fef2f2; color: #dc2626; }
.pct-badge.mid   { background: #fffbeb; color: #d97706; }
.pct-badge.low   { background: #f0fdf4; color: #16a34a; }
</style>
@endpush

@section('content')

<div class="section-head">
    <div>
        <h1>Estatísticas Avançadas — Boletins de Ocorrência</h1>
        <p class="muted" style="margin: 6px 0 0;">
            Análise criminal da base legada. Leitura somente em tempo real.
            @if (($sumario['total'] ?? 0) === 0)
                <strong style="color:#dc2626;"> — base legada indisponível no momento.</strong>
            @else
                Base: <strong>{{ number_format($sumario['total']) }}</strong> BOs registrados.
            @endif
        </p>
    </div>
    <div class="actions">
        <a class="btn secondary" href="{{ route('analise.index') }}">← Análise de Dados</a>
        <a class="btn secondary" href="{{ route('analise.bos.search') }}">Pesquisar vítima/autor</a>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     SEÇÃO 1: KPIs principais
     ══════════════════════════════════════════════════════════ --}}
<div class="cards" style="margin-bottom: 18px;">
    <article class="card" style="text-align: center; padding: 18px;">
        <small>Total de BOs</small>
        <div class="kpi-value blue">{{ number_format($sumario['total'] ?? 0) }}</div>
        <span class="muted" style="font-size:0.78rem;">ocorrências registradas</span>
    </article>
    <article class="card" style="text-align: center; padding: 18px;">
        <small>Flagrantes lavrados</small>
        <div class="kpi-value red">{{ number_format($sumario['flagrantes'] ?? 0) }}</div>
        <span class="muted" style="font-size:0.78rem;">prisões em flagrante</span>
    </article>
    <article class="card" style="text-align: center; padding: 18px;">
        <small>Taxa de flagrante</small>
        <div class="kpi-value {{ $taxaFlagrante >= 20 ? 'red' : ($taxaFlagrante >= 10 ? 'amber' : 'green') }}">
            {{ number_format($taxaFlagrante, 1) }}%
        </div>
        <span class="muted" style="font-size:0.78rem;">do total de BOs</span>
    </article>
    <article class="card" style="text-align: center; padding: 18px;">
        <small>Com número IP</small>
        <div class="kpi-value" style="color:#0d9488;">{{ number_format($sumario['com_ip'] ?? 0) }}</div>
        <span class="muted" style="font-size:0.78rem;">inquéritos vinculados</span>
    </article>
    <article class="card" style="text-align: center; padding: 18px;">
        <small>Com número MPU</small>
        <div class="kpi-value" style="color:#7c3aed;">{{ number_format($sumario['com_mpu'] ?? 0) }}</div>
        <span class="muted" style="font-size:0.78rem;">processos no MP/Tribunal</span>
    </article>
</div>

{{-- ══════════════════════════════════════════════════════════
     SEÇÃO 2: Evolução mensal (bar chart com overlay de flagrantes)
     ══════════════════════════════════════════════════════════ --}}
@if (!empty($evolucaoMensal))
<section class="card" style="margin-bottom: 18px;">
    <h2 style="margin-top: 0; margin-bottom: 16px;">Evolução mensal — últimos {{ count($evolucaoMensal) }} meses</h2>

    {{-- Bar chart CSS --}}
    <div class="bar-chart" style="height: 100px; margin-bottom: 28px;">
        @foreach ($evolucaoMensal as $mes)
            @php
                $pct   = $maxMensal > 0 ? round(($mes['total'] / $maxMensal) * 100) : 0;
                $pctF  = $mes['total']  > 0 ? round(($mes['flagrantes'] / $mes['total']) * 100) : 0;
                $barH  = max($pct, 2);
                $flagH = max(round($pctF * $barH / 100), 1);
                $label = strlen($mes['periodo']) === 7
                    ? substr($mes['periodo'], 5, 2) . '/' . substr($mes['periodo'], 2, 2)
                    : $mes['periodo'];
            @endphp
            <div class="bar-chart-col" title="{{ $mes['periodo'] }}: {{ number_format($mes['total']) }} BOs, {{ $mes['flagrantes'] }} flagrantes">
                <div class="bar-chart-bar" style="height: {{ $barH }}px;">
                    <span class="bar-flag" style="height: {{ $flagH }}px;"></span>
                </div>
                <div class="bar-chart-label">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <div style="display: flex; gap: 16px; font-size: 0.78rem; color: #6b7280; margin-top: 8px;">
        <span style="display:flex; align-items:center; gap:5px;">
            <span style="width:12px; height:12px; background:#3b82f6; border-radius:2px; display:inline-block;"></span> Total de BOs
        </span>
        <span style="display:flex; align-items:center; gap:5px;">
            <span style="width:12px; height:12px; background:#ef4444; border-radius:2px; display:inline-block;"></span> Flagrantes
        </span>
    </div>

    {{-- Tabela resumo do período --}}
    <div style="overflow-x: auto; margin-top: 16px;">
        <table class="table-compact">
            <thead>
                <tr>
                    <th>Período</th>
                    <th style="text-align:right;">BOs</th>
                    <th style="text-align:right;">Flagrantes</th>
                    <th style="text-align:right;">Taxa</th>
                    <th>Distribuição</th>
                </tr>
            </thead>
            <tbody>
                @foreach (array_reverse($evolucaoMensal) as $mes)
                    @php
                        $taxa = $mes['total'] > 0 ? round($mes['flagrantes'] / $mes['total'] * 100, 1) : 0;
                        $barW = $maxMensal > 0 ? round($mes['total'] / $maxMensal * 100) : 0;
                    @endphp
                    <tr>
                        <td>{{ $mes['periodo'] }}</td>
                        <td style="text-align:right;">{{ number_format($mes['total']) }}</td>
                        <td style="text-align:right;">{{ $mes['flagrantes'] }}</td>
                        <td style="text-align:right;">
                            <span class="pct-badge {{ $taxa >= 20 ? 'high' : ($taxa >= 10 ? 'mid' : 'low') }}">{{ number_format($taxa, 1) }}%</span>
                        </td>
                        <td>
                            <div class="stat-bar-track" style="min-width: 80px;">
                                <div class="stat-bar-fill" style="width: {{ $barW }}%;"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════
     SEÇÃO 3: Evolução anual
     ══════════════════════════════════════════════════════════ --}}
@if (!empty($evolucaoAnual))
@php $maxAnual = max(array_map(fn($r) => (int)$r['total'], $evolucaoAnual)); @endphp
<section class="card" style="margin-bottom: 18px;">
    <h2 style="margin-top: 0;">Evolução anual</h2>
    <div style="overflow-x: auto;">
        <table class="table-compact">
            <thead>
                <tr>
                    <th>Ano</th>
                    <th style="text-align:right;">BOs</th>
                    <th style="text-align:right;">Flagrantes</th>
                    <th style="text-align:right;">Taxa</th>
                    <th style="text-align:right;">Com IP</th>
                    <th style="text-align:right;">Com MPU</th>
                    <th>Volume</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($evolucaoAnual as $ano)
                    @php
                        $taxa  = $ano['total'] > 0 ? round((int)$ano['flagrantes'] / (int)$ano['total'] * 100, 1) : 0;
                        $barW  = $maxAnual > 0 ? round((int)$ano['total'] / $maxAnual * 100) : 0;
                        $pctIp = $ano['total'] > 0 ? round((int)$ano['com_ip'] / (int)$ano['total'] * 100) : 0;
                        $pctMpu= $ano['total'] > 0 ? round((int)$ano['com_mpu'] / (int)$ano['total'] * 100) : 0;
                    @endphp
                    <tr>
                        <td><strong>{{ $ano['ano'] }}</strong></td>
                        <td style="text-align:right;">{{ number_format($ano['total']) }}</td>
                        <td style="text-align:right;">{{ number_format($ano['flagrantes']) }}</td>
                        <td style="text-align:right;">
                            <span class="pct-badge {{ $taxa >= 20 ? 'high' : ($taxa >= 10 ? 'mid' : 'low') }}">{{ number_format($taxa, 1) }}%</span>
                        </td>
                        <td style="text-align:right;">{{ number_format($ano['com_ip']) }} <span class="muted">({{ $pctIp }}%)</span></td>
                        <td style="text-align:right;">{{ number_format($ano['com_mpu']) }} <span class="muted">({{ $pctMpu }}%)</span></td>
                        <td style="min-width: 100px;">
                            <div class="stat-bar-wrap">
                                <div class="stat-bar-track">
                                    <div class="stat-bar-fill" style="width: {{ $barW }}%;"></div>
                                </div>
                                <small class="muted">{{ $barW }}%</small>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

{{-- ══════════════════════════════════════════════════════════
     SEÇÃO 4: Todas as ocorrências — naturezas + área
     ══════════════════════════════════════════════════════════ --}}
<div class="section-head" style="margin-top: 8px; margin-bottom: 10px; padding: 12px 16px; background: #eff6ff; border-radius: 8px; border-left: 4px solid #3b82f6;">
    <div>
        <h2 style="margin: 0; color: #1d4ed8;">Todas as ocorrências <small class="muted" style="font-weight:400;">(normais + flagrantes)</small></h2>
        <p class="muted" style="margin: 4px 0 0; font-size: 0.85rem;">Base completa de {{ number_format($sumario['total'] ?? 0) }} BOs registrados.</p>
    </div>
</div>

<div class="grid" style="grid-template-columns: 1.1fr 0.9fr; gap: 16px; margin-bottom: 18px;">

    {{-- Top naturezas — todas as ocorrências --}}
    @if (!empty($topNaturezas))
    <section class="card" style="padding-bottom: 12px;">
        <h2 style="margin-top: 0;">Top naturezas — todas as ocorrências&nbsp;<small class="muted">(20 maiores)</small></h2>
        <div style="overflow-x: auto;">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Natureza</th>
                        <th style="text-align:right;">Total BOs</th>
                        <th style="text-align:right;">Flagrantes</th>
                        <th style="text-align:right;">Taxa flag.</th>
                        <th>Volume</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topNaturezas as $i => $nat)
                        @php
                            $taxaN = $nat['total'] > 0 ? round((int)$nat['flagrantes'] / (int)$nat['total'] * 100, 1) : 0;
                            $barW  = $maxNat > 0 ? round((int)$nat['total'] / $maxNat * 100) : 0;
                        @endphp
                        <tr>
                            <td class="muted">{{ $i + 1 }}</td>
                            <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                title="{{ $nat['natureza_label'] }}">{{ $nat['natureza_label'] }}</td>
                            <td style="text-align:right; font-weight:600;">{{ number_format($nat['total']) }}</td>
                            <td style="text-align:right; color:#dc2626;">{{ number_format($nat['flagrantes']) }}</td>
                            <td style="text-align:right;">
                                <span class="pct-badge {{ $taxaN >= 20 ? 'high' : ($taxaN >= 10 ? 'mid' : 'low') }}">{{ number_format($taxaN, 1) }}%</span>
                            </td>
                            <td style="min-width: 80px;">
                                <div class="stat-bar-track">
                                    <div class="stat-bar-fill" style="width: {{ $barW }}%;"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif

    {{-- Distribuição por área — todas as ocorrências --}}
    @if (!empty($porArea))
    <section class="card" style="padding-bottom: 12px;">
        <h2 style="margin-top: 0;">Por área do fato — todas as ocorrências</h2>
        <div style="overflow-x: auto;">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th>Área</th>
                        <th style="text-align:right;">Total BOs</th>
                        <th style="text-align:right;">Flagrantes</th>
                        <th style="text-align:right;">Taxa flag.</th>
                        <th>Volume</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($porArea as $area)
                        @php
                            $taxaA = $area['total'] > 0 ? round((int)$area['flagrantes'] / (int)$area['total'] * 100, 1) : 0;
                            $barW  = $maxArea > 0 ? round((int)$area['total'] / $maxArea * 100) : 0;
                        @endphp
                        <tr>
                            <td title="{{ $area['area'] }}" style="max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $area['area'] }}
                            </td>
                            <td style="text-align:right; font-weight:600;">{{ number_format($area['total']) }}</td>
                            <td style="text-align:right; color:#dc2626;">{{ number_format($area['flagrantes']) }}</td>
                            <td style="text-align:right;">
                                <span class="pct-badge {{ $taxaA >= 20 ? 'high' : ($taxaA >= 10 ? 'mid' : 'low') }}">{{ number_format($taxaA, 1) }}%</span>
                            </td>
                            <td style="min-width: 70px;">
                                <div class="stat-bar-track">
                                    <div class="stat-bar-fill" style="width: {{ $barW }}%;"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif

</div>

{{-- ══════════════════════════════════════════════════════════
     SEÇÃO 5: Somente flagrantes — naturezas + área
     ══════════════════════════════════════════════════════════ --}}
<div class="section-head" style="margin-top: 8px; margin-bottom: 10px; padding: 12px 16px; background: #fef2f2; border-radius: 8px; border-left: 4px solid #ef4444;">
    <div>
        <h2 style="margin: 0; color: #b91c1c;">Somente flagrantes <small class="muted" style="font-weight:400;">(flagrante = 1)</small></h2>
        <p class="muted" style="margin: 4px 0 0; font-size: 0.85rem;">
            Base exclusiva de {{ number_format($sumario['flagrantes'] ?? 0) }} prisões em flagrante
            ({{ number_format($taxaFlagrante, 1) }}% do total de BOs).
        </p>
    </div>
</div>

<div class="grid" style="grid-template-columns: 1.1fr 0.9fr; gap: 16px; margin-bottom: 18px;">

    {{-- Top naturezas — somente flagrantes --}}
    @if (!empty($topNaturezasFlag))
    <section class="card" style="padding-bottom: 12px; border-top: 3px solid #ef4444;">
        <h2 style="margin-top: 0;">Top naturezas — somente flagrantes&nbsp;<small class="muted">(20 maiores)</small></h2>
        <div style="overflow-x: auto;">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Natureza</th>
                        <th style="text-align:right;">Flagrantes</th>
                        <th>Volume</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topNaturezasFlag as $i => $nat)
                        @php $barW = $maxNatFlag > 0 ? round((int)$nat['total'] / $maxNatFlag * 100) : 0; @endphp
                        <tr>
                            <td class="muted">{{ $i + 1 }}</td>
                            <td style="max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                title="{{ $nat['natureza_label'] }}">{{ $nat['natureza_label'] }}</td>
                            <td style="text-align:right; font-weight:600; color:#dc2626;">{{ number_format($nat['total']) }}</td>
                            <td style="min-width: 80px;">
                                <div class="stat-bar-track">
                                    <div class="stat-bar-fill danger" style="width: {{ $barW }}%;"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif

    {{-- Distribuição por área — somente flagrantes --}}
    @if (!empty($porAreaFlag))
    <section class="card" style="padding-bottom: 12px; border-top: 3px solid #ef4444;">
        <h2 style="margin-top: 0;">Por área do fato — somente flagrantes</h2>
        <div style="overflow-x: auto;">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th>Área</th>
                        <th style="text-align:right;">Flagrantes</th>
                        <th style="text-align:right;">Atos infrac.</th>
                        <th style="text-align:right;">Com IP</th>
                        <th>Volume</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($porAreaFlag as $area)
                        @php $barW = $maxAreaFlag > 0 ? round((int)$area['total'] / $maxAreaFlag * 100) : 0; @endphp
                        <tr>
                            <td title="{{ $area['area'] }}" style="max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $area['area'] }}
                            </td>
                            <td style="text-align:right; font-weight:600; color:#dc2626;">{{ number_format($area['total']) }}</td>
                            <td style="text-align:right;">{{ number_format($area['atos_infracionais']) }}</td>
                            <td style="text-align:right;">{{ number_format($area['com_ip']) }}</td>
                            <td style="min-width: 70px;">
                                <div class="stat-bar-track">
                                    <div class="stat-bar-fill danger" style="width: {{ $barW }}%;"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif

</div>

{{-- ══════════════════════════════════════════════════════════
     SEÇÃO 6 + 7: Dia da semana  |  Por cartório IP
     ══════════════════════════════════════════════════════════ --}}
<div class="grid" style="grid-template-columns: 0.5fr 1.5fr; gap: 16px; margin-bottom: 18px;">

    {{-- Dia da semana --}}
    @if (!empty($porDiaSemana))
    <section class="card">
        <h2 style="margin-top: 0;">Por dia da semana</h2>

        {{-- Mini bar chart --}}
        <div class="dia-chart" style="margin-bottom: 16px;">
            @foreach ($porDiaSemana as $dia)
                @php $h = $maxDia > 0 ? max(round(($dia['total'] / $maxDia) * 52), 3) : 3; @endphp
                <div class="dia-col" title="{{ $dia['dia'] }}: {{ number_format($dia['total']) }} BOs">
                    <div class="dia-bar" style="height: {{ $h }}px;"></div>
                    <div class="dia-label">{{ mb_substr($dia['dia'], 0, 3) }}</div>
                </div>
            @endforeach
        </div>

        <table class="table-compact">
            <thead>
                <tr>
                    <th>Dia</th>
                    <th style="text-align:right;">BOs</th>
                    <th style="text-align:right;">Flag.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($porDiaSemana as $dia)
                    <tr>
                        <td>{{ $dia['dia'] }}</td>
                        <td style="text-align:right;">{{ number_format($dia['total']) }}</td>
                        <td style="text-align:right;">{{ $dia['flagrantes'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
    @endif

    {{-- Por cartório IP --}}
    @if (!empty($porCartorio))
    @php $maxCart = max(array_map(fn($r) => (int)$r['total'], $porCartorio)); @endphp
    <section class="card">
        <h2 style="margin-top: 0;">Por cartório de IP</h2>
        <div style="overflow-x: auto;">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th>Cartório IP</th>
                        <th style="text-align:right;">BOs</th>
                        <th style="text-align:right;">Com IP</th>
                        <th style="text-align:right;">Com MPU</th>
                        <th style="text-align:right;">Flagrantes</th>
                        <th style="text-align:right;">Taxa</th>
                        <th>Volume</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($porCartorio as $cart)
                        @php
                            $taxaC = $cart['total'] > 0 ? round((int)$cart['flagrantes'] / (int)$cart['total'] * 100, 1) : 0;
                            $barW  = $maxCart > 0 ? round((int)$cart['total'] / $maxCart * 100) : 0;
                        @endphp
                        <tr>
                            <td title="{{ $cart['cartorio'] }}" style="max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $cart['cartorio'] }}
                            </td>
                            <td style="text-align:right;">{{ number_format($cart['total']) }}</td>
                            <td style="text-align:right;">{{ number_format($cart['com_ip']) }}</td>
                            <td style="text-align:right;">{{ number_format($cart['com_mpu']) }}</td>
                            <td style="text-align:right;">{{ number_format($cart['flagrantes']) }}</td>
                            <td style="text-align:right;">
                                <span class="pct-badge {{ $taxaC >= 20 ? 'high' : ($taxaC >= 10 ? 'mid' : 'low') }}">{{ number_format($taxaC, 1) }}%</span>
                            </td>
                            <td style="min-width: 80px;">
                                <div class="stat-bar-track">
                                    <div class="stat-bar-fill purple" style="width: {{ $barW }}%;"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif

</div>

{{-- ══════════════════════════════════════════════════════════
     SEÇÃO 8: Perfil das vítimas
     ══════════════════════════════════════════════════════════ --}}
<div class="grid" style="grid-template-columns: 1fr; gap: 16px; margin-bottom: 18px;">

    {{-- Tipos de vítimas --}}
    @if (!empty($tiposVitimas))
    @php $maxVit = max(array_map(fn($r) => (int)$r['total_vitimas'], $tiposVitimas)); @endphp
    <section class="card">
        <h2 style="margin-top: 0;">Perfil das vítimas</h2>
        <table class="table-compact">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th style="text-align:right;">Vítimas</th>
                    <th style="text-align:right;">BOs</th>
                    <th>Volume</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tiposVitimas as $vit)
                    @php $barW = $maxVit > 0 ? round((int)$vit['total_vitimas'] / $maxVit * 100) : 0; @endphp
                    <tr>
                        <td>{{ $vit['tipo'] }}</td>
                        <td style="text-align:right;">{{ number_format($vit['total_vitimas']) }}</td>
                        <td style="text-align:right;">{{ number_format($vit['total_bos']) }}</td>
                        <td style="min-width: 80px;">
                            <div class="stat-bar-track">
                                <div class="stat-bar-fill success" style="width: {{ $barW }}%;"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @php
            $totalVitimas = array_sum(array_column($tiposVitimas, 'total_vitimas'));
        @endphp
        @if ($totalVitimas > 0)
        <p class="muted" style="font-size: 0.8rem; margin: 12px 0 0;">
            Total de vítimas identificadas: <strong>{{ number_format($totalVitimas) }}</strong>
        </p>
        @endif
    </section>
    @endif

</div>

{{-- Sem dados --}}
@if (($sumario['total'] ?? 0) === 0)
<section class="card" style="text-align: center; padding: 40px; color: #6b7280;">
    <strong style="font-size: 1.1rem;">Base legada indisponível</strong><br>
    <p style="margin: 8px 0 0; max-width: 480px; margin-inline: auto;">
        O banco de dados legado não pôde ser lido no momento. Verifique se o arquivo SQLite está acessível e tente novamente.
    </p>
</section>
@endif

@endsection
