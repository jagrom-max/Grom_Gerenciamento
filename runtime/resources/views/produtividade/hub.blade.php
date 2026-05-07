@extends('layouts.app')

@section('title', 'Hub de Produtividade | Grom.Seg')

@push('styles')
<style>
/* Barras */
.hub-bar-track {
    flex: 1; height: 6px;
    background: #e6edf5;
    border-radius: 3px;
    overflow: hidden;
    min-width: 50px;
}
.hub-bar-fill {
    height: 100%;
    border-radius: 3px;
    background: var(--grom-primary);
}
.hub-bar-fill.red    { background: #b42318; }
.hub-bar-fill.green  { background: #16794d; }
.hub-bar-fill.amber  { background: #b7791f; }
.hub-bar-fill.purple { background: #5b4fb2; }

/* KPI card */
.hub-kpi {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 18px 12px;
    text-align: center;
}
.hub-kpi .kv {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}
.hub-kpi .kv.blue   { color: var(--grom-primary); }
.hub-kpi .kv.red    { color: #b42318; }
.hub-kpi .kv.green  { color: #16794d; }
.hub-kpi .kv.amber  { color: #b7791f; }
.hub-kpi .kv.purple { color: #5b4fb2; }
.hub-kpi small { color: var(--grom-ink-soft); font-size: 0.78rem; display: block; margin-top: 4px; }

.delta-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    margin-top: 6px;
    border: 1px solid transparent;
}
.delta-badge.up   { background: #e8f3eb; color: #16794d; border-color: #bfdac7; }
.delta-badge.down { background: #fdecec; color: #b42318; border-color: #f0c1c1; }
.delta-badge.flat { background: #edf2f7; color: #5f7388; border-color: rgba(15, 39, 68, 0.08); }

/* Mini bar chart mensal */
.month-chart {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    height: 54px;
}
.month-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
}
.month-bar {
    width: 100%;
    background: var(--grom-primary);
    border-radius: 3px 3px 0 0;
    min-height: 2px;
    cursor: default;
}

/* Alertas */
.hub-alert {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 18px;
    margin-bottom: 10px;
    box-shadow: 0 10px 24px rgba(15, 39, 68, 0.05);
}
.hub-alert.warn  { background: #fff4de; border: 1px solid #e9c06c; }
.hub-alert.info  { background: #eef3f9; border: 1px solid #bcd0e5; }
.hub-alert.ok    { background: #e8f3eb; border: 1px solid #bfdac7; }
.hub-alert .icon { font-size: 1.5rem; }
.hub-alert > div { flex: 1; }
.hub-alert strong { display: block; font-size: 0.88rem; }
.hub-alert .muted { font-size: 0.78rem; color: var(--grom-ink-soft); }

/* Modulo-card */
.hub-module {
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 18px;
    border: 1px solid rgba(15, 39, 68, 0.08);
    border-radius: 18px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fafd 100%);
    transition: box-shadow .15s, border-color .15s, transform .15s;
}
.hub-module:hover {
    box-shadow: 0 14px 30px rgba(15, 39, 68, 0.1);
    border-color: rgba(15, 39, 68, 0.18);
    transform: translateY(-1px);
}
.hub-module .mod-icon { font-size: 1.8rem; }
.hub-module .mod-title { font-weight: 700; font-size: 0.95rem; }
.hub-module .mod-desc  { font-size: 0.78rem; color: var(--grom-ink-soft); }
.hub-module .mod-badge {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    background: #fff4de;
    color: #8a6015;
    align-self: flex-start;
    border: 1px solid #e9c06c;
}
.hub-module .mod-badge.ok { background: #e8f3eb; color: #16794d; border-color: #bfdac7; }
</style>
@endpush

@section('content')

{{-- Cabecalho --}}
<div class="section-head">
    <div>
        <h1>Hub de Produtividade</h1>
        <p class="muted" style="margin: 6px 0 0;">
            Visão consolidada &mdash; <strong>{{ $periodoLabel }}</strong>
            &nbsp;·&nbsp; {{ $totalCartorios }} cartório{{ $totalCartorios !== 1 ? 's' : '' }}
            ({{ $cartoriosAtivos }} ativo{{ $cartoriosAtivos !== 1 ? 's' : '' }})
        </p>
    </div>
    <div class="actions">
        <a class="btn secondary" href="{{ route('produtividade.stats.index') }}">Estatísticas completas</a>
        <a class="btn secondary" href="{{ route('produtividade.boletins.index') }}">Boletins</a>
        <a class="btn secondary" href="{{ route('produtividade.flagrantes.index') }}">Fila de flagrantes</a>
        <a class="btn" href="{{ route('produtividade.flagrantes.relatorio') }}">Relatório A4</a>
    </div>
</div>

{{-- Alertas --}}
@if ($auditoriaPendentes > 0 || $pendingSemCartorio > 0)
<div style="margin-bottom: 16px;">
    @if ($auditoriaPendentes > 0)
    <div class="hub-alert warn">
        <div class="icon">⚠</div>
        <div>
            <strong>{{ $auditoriaPendentes }} flagrante{{ $auditoriaPendentes > 1 ? 's' : '' }} aguardando auditoria de cartório</strong>
            <span class="muted">BOs marcados como flagrante na planilha sem cartório do IP vinculado.</span>
        </div>
        <a class="btn secondary" style="border-color:#f59e0b; color:#b45309; font-size:0.83rem;"
           href="{{ route('analise.bos.auditoria-flagrantes') }}">Revisar</a>
    </div>
    @endif
    @if ($pendingSemCartorio > 0)
    <div class="hub-alert info">
        <div class="icon">📋</div>
        <div>
            <strong>{{ $pendingSemCartorio }} sugestão{{ $pendingSemCartorio > 1 ? 'ões' : '' }} sem cartório na fila</strong>
            <span class="muted">Registros de importação aguardando atribuição de cartório.</span>
        </div>
        <a class="btn secondary" style="font-size:0.83rem;"
           href="{{ route('produtividade.flagrantes.index') }}">Ver fila</a>
    </div>
    @endif
</div>
@else
<div style="margin-bottom: 16px;">
    <div class="hub-alert ok">
        <div class="icon">✓</div>
        <div>
            <strong>Sem pendências críticas</strong>
            <span class="muted">Fila de auditoria e saneamento em dia.</span>
        </div>
    </div>
</div>
@endif

{{-- KPIs do mes atual --}}
<div class="cards" style="margin-bottom: 18px;">

    @php
        $difBo = $boletimStats['boletins_total'] - $prevStats['boletins_total'];
        $difFlag = $stats['flagrantes_total'] - $prevStats['flagrantes_total'];
        $difIp   = $stats['ip_instaurados']   - $prevStats['ip_instaurados'];
        $difRel  = $stats['ip_relatados']      - $prevStats['ip_relatados'];
    @endphp

    <article class="card hub-kpi">
        <small>BOs totais — {{ $periodoLabel }}</small>
        <div class="kv amber">{{ number_format($boletimStats['boletins_total']) }}</div>
        @php
            $clsBo = $difBo > 0 ? 'up' : ($difBo < 0 ? 'down' : 'flat');
            $symBo = $difBo > 0 ? '↑' : ($difBo < 0 ? '↓' : '→');
        @endphp
        <span class="delta-badge {{ $clsBo }}">{{ $symBo }} {{ abs($difBo) }} vs {{ $periodoAnteriorLabel }}</span>
        <small>Não-flagrantes: <strong>{{ $boletimStats['nao_flagrantes_total'] }}</strong> | MPU sem IP: <strong>{{ $boletimStats['mpu_sem_ip_total'] }}</strong></small>
    </article>

    <article class="card hub-kpi">
        <small>Flagrantes — {{ $periodoLabel }}</small>
        <div class="kv red">{{ number_format($stats['flagrantes_total']) }}</div>
        @php
            $cls = $difFlag > 0 ? 'up' : ($difFlag < 0 ? 'down' : 'flat');
            $sym = $difFlag > 0 ? '↑' : ($difFlag < 0 ? '↓' : '→');
        @endphp
        <span class="delta-badge {{ $cls }}">{{ $sym }} {{ abs($difFlag) }} vs {{ $periodoAnteriorLabel }}</span>
        <small>
            DDM: <strong>{{ $stats['flagrantes_ddm'] }}</strong>
            &nbsp;|&nbsp;
            Outras: <strong>{{ $stats['flagrantes_outras'] }}</strong>
        </small>
    </article>

    <article class="card hub-kpi">
        <small>IP instaurados</small>
        <div class="kv blue">{{ number_format($stats['ip_instaurados']) }}</div>
        @php $cls2 = $difIp > 0 ? 'up' : ($difIp < 0 ? 'down' : 'flat'); $sym2 = $difIp > 0 ? '↑' : ($difIp < 0 ? '↓' : '→'); @endphp
        <span class="delta-badge {{ $cls2 }}">{{ $sym2 }} {{ abs($difIp) }} vs mês ant.</span>
    </article>

    <article class="card hub-kpi">
        <small>IP relatados</small>
        <div class="kv green">{{ number_format($stats['ip_relatados']) }}</div>
        @php $cls3 = $difRel > 0 ? 'up' : ($difRel < 0 ? 'down' : 'flat'); $sym3 = $difRel > 0 ? '↑' : ($difRel < 0 ? '↓' : '→'); @endphp
        <span class="delta-badge {{ $cls3 }}">{{ $sym3 }} {{ abs($difRel) }} vs mês ant.</span>
    </article>

    <article class="card hub-kpi">
        <small>Sugestões pendentes</small>
        <div class="kv {{ $pendingCount > 0 ? 'amber' : 'green' }}">{{ number_format($pendingCount) }}</div>
        <small>na fila de confirmação</small>
    </article>

    <article class="card hub-kpi">
        <small>IPs em andamento</small>
        <div class="kv purple">{{ number_format($stats['ips_andamento']) }}</div>
        <small>registros | concluídos: {{ $stats['concluidos'] }}</small>
    </article>

</div>

{{-- Evolucao de flagrantes (12 meses) + Ranking --}}
<div class="grid" style="grid-template-columns: 1fr 1.2fr; gap: 16px; margin-bottom: 18px;">

    {{-- Mini bar chart mensal --}}
    <section class="card">
        <h2 style="margin-top: 0;">Flagrantes — {{ $year }}</h2>
        <div class="month-chart" style="margin-bottom: 12px;">
            @foreach ($breakdown as $b)
                @php $h = $maxFlagrante > 0 ? max(round($b['flagrantes_total'] / $maxFlagrante * 50), $b['flagrantes_total'] > 0 ? 3 : 0) : 0; @endphp
                <div class="month-col" title="{{ $b['label'] }}: {{ $b['flagrantes_total'] }} flagrantes">
                    <div class="month-bar {{ $b['month'] == $month ? 'red' : '' }}"
                         style="height: {{ $h }}px; {{ $b['month'] == $month ? 'background:#ef4444;' : '' }}"></div>
                </div>
            @endforeach
        </div>
        <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:4px;">
            @foreach ($breakdown as $b)
                <div style="flex:1; text-align:center; min-width:24px;">
                    <div style="font-size:0.68rem; font-weight:{{ $b['month'] == $month ? '700' : '400' }};
                                color:{{ $b['month'] == $month ? '#dc2626' : '#6b7280' }};">
                        {{ substr($b['label'], 0, 3) }}
                    </div>
                    <div style="font-size:0.7rem; font-weight:600;">
                        {{ $b['flagrantes_total'] > 0 ? $b['flagrantes_total'] : '—' }}
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Ultimos lotes importados --}}
        @if ($recentBatches->isNotEmpty())
        <div style="margin-top: 16px; border-top: 1px solid #f3f4f6; padding-top: 12px;">
            <p style="font-size:0.78rem; font-weight:600; color:#6b7280; margin:0 0 6px;">Últimas importações</p>
            @foreach ($recentBatches as $batch)
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">
                <div style="flex:1; font-size:0.78rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                     title="{{ $batch->source_name }}">{{ $batch->source_name }}</div>
                <div style="font-size:0.72rem; color:#9ca3af; white-space:nowrap;">
                    {{ $batch->imported_at?->format('d/m/Y') ?? $batch->created_at->format('d/m/Y') }}
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </section>

    {{-- Ranking de flagrantes por cartorio --}}
    <section class="card">
        <h2 style="margin-top: 0;">
            Ranking cartórios — {{ $periodoLabel }}
            @if($ranking->isEmpty())
                <small class="muted" style="font-weight:400;">(sem dados)</small>
            @endif
        </h2>
        @php $maxRank = $ranking->max('flagrantes_total') ?: 1; @endphp
        @forelse ($ranking as $row)
            @php
                $barW = $maxRank > 0 ? round($row['flagrantes_total'] / $maxRank * 100) : 0;
                $taxa = $row['ip_instaurados'] > 0
                    ? round($row['flagrantes_total'] / $row['ip_instaurados'] * 100, 1)
                    : null;
            @endphp
            <div style="margin-bottom: 10px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:3px;">
                    <span style="font-size:0.85rem; font-weight:600;">
                        [{{ $row['cartorio']->number }}] {{ $row['cartorio']->name }}
                        @if ($row['pending_items'] > 0)
                            <span style="font-size:0.7rem; background:#fef3c7; color:#92400e; padding:1px 5px; border-radius:8px; margin-left:4px;">
                                {{ $row['pending_items'] }} pend.
                            </span>
                        @endif
                    </span>
                    <span style="font-size:0.83rem; font-weight:700; color:#dc2626;">
                        {{ $row['flagrantes_total'] }}
                    </span>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <div class="hub-bar-track">
                        <div class="hub-bar-fill red" style="width: {{ $barW }}%;"></div>
                    </div>
                    <span style="font-size:0.72rem; color:#9ca3af; white-space:nowrap;">
                        IP: {{ $row['ip_instaurados'] }}
                        @if ($taxa !== null)
                            &nbsp;({{ $taxa }}% flag.)
                        @endif
                    </span>
                </div>
            </div>
        @empty
            <p class="muted" style="font-size:0.85rem;">Nenhum dado para {{ $periodoLabel }}.</p>
        @endforelse

        @if ($ranking->count() === 8)
            <a href="{{ route('produtividade.stats.index', ['year' => $year, 'month' => $month]) }}"
               style="font-size:0.8rem; color:#2563eb;">Ver todos →</a>
        @endif
    </section>

</div>

{{-- Modulos de acesso rapido --}}
<h2 style="margin: 0 0 12px; font-size: 1rem; color: #374151;">Acesso rápido</h2>
<div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 18px;">

    <a class="hub-module" href="{{ route('produtividade.cartorios.index') }}">
        <div class="mod-icon">🏛</div>
        <div class="mod-title">Cartórios</div>
        <div class="mod-desc">Cadastro, responsável, histórico de designações</div>
        <span class="mod-badge ok">{{ $cartoriosAtivos }} ativos</span>
    </a>

    <a class="hub-module" href="{{ route('produtividade.cartorios.index') }}">
        <div class="mod-icon">📝</div>
        <div class="mod-title">Fechamento Mensal</div>
        <div class="mod-desc">Lançar IP instaurados, relatados, cotas e despachos por cartório</div>
        <span class="mod-badge ok">{{ now()->format('m/Y') }}</span>
    </a>

    <a class="hub-module" href="{{ route('produtividade.boletins.index') }}">
        <div class="mod-icon">🗂</div>
        <div class="mod-title">Boletins / Upload Consolidado</div>
        <div class="mod-desc">Ponto único de upload do arquivo de consolidação e gestão de todos os BOs</div>
        <span class="mod-badge ok">Entrada principal</span>
    </a>

    <a class="hub-module" href="{{ route('produtividade.flagrantes.index') }}">
        <div class="mod-icon">⚡</div>
        <div class="mod-title">Fila de flagrantes</div>
        <div class="mod-desc">Sugestões, confirmação e saneamento dos flagrantes detectados no arquivo único</div>
        @if ($pendingCount > 0)
            <span class="mod-badge">{{ $pendingCount }} pendente{{ $pendingCount > 1 ? 's' : '' }}</span>
        @else
            <span class="mod-badge ok">Em dia</span>
        @endif
    </a>

    <a class="hub-module" href="{{ route('produtividade.stats.index') }}">
        <div class="mod-icon">📊</div>
        <div class="mod-title">Estatísticas</div>
        <div class="mod-desc">IP instaurados, relatados, concluídos, fechamento mensal</div>
        <span class="mod-badge ok">{{ $summary['stats_registros'] }} registros</span>
    </a>

    <a class="hub-module" href="{{ route('produtividade.flagrantes.relatorio') }}">
        <div class="mod-icon">📄</div>
        <div class="mod-title">Relatório A4</div>
        <div class="mod-desc">Relatório de flagrantes por cartório para impressão</div>
        <span class="mod-badge ok">Pronto para imprimir</span>
    </a>

    <a class="hub-module" href="{{ route('analise.bos.import') }}">
        <div class="mod-icon">📥</div>
        <div class="mod-title">Importar BOs</div>
        <div class="mod-desc">Upload de planilha XLSX/CSV de ocorrências</div>
        @if ($summary['lotes_com_erro_30d'] > 0)
            <span class="mod-badge">{{ $summary['lotes_com_erro_30d'] }} lote(s) c/ erro</span>
        @else
            <span class="mod-badge ok">{{ $summary['lotes_30d'] }} lotes (30d)</span>
        @endif
    </a>

    <a class="hub-module" href="{{ route('analise.bos.auditoria-flagrantes') }}">
        <div class="mod-icon">🔍</div>
        <div class="mod-title">Auditoria de flagrantes</div>
        <div class="mod-desc">Flagrantes sem cartório aguardando revisão</div>
        @if ($auditoriaPendentes > 0)
            <span class="mod-badge">{{ $auditoriaPendentes }} pendente{{ $auditoriaPendentes > 1 ? 's' : '' }}</span>
        @else
            <span class="mod-badge ok">Sem pendências</span>
        @endif
    </a>

    <a class="hub-module" href="{{ route('analise.estatisticas') }}">
        <div class="mod-icon">📈</div>
        <div class="mod-title">Estatísticas de BOs</div>
        <div class="mod-desc">Análise avançada do banco consolidado de ocorrências</div>
        <span class="mod-badge ok">Somente leitura</span>
    </a>

    <a class="hub-module" href="{{ route('analise.index') }}">
        <div class="mod-icon">🗂</div>
        <div class="mod-title">Análise de dados</div>
        <div class="mod-desc">Painel principal de análise, lotes e pesquisa nominal</div>
        <span class="mod-badge ok">Painel</span>
    </a>

</div>

{{-- Cartorios com maior demanda na fila --}}
@if ($cartoriosComPendencia->isNotEmpty())
<section class="card" style="margin-bottom: 16px;">
    <h2 style="margin-top:0;">Cartórios com mais sugestões pendentes</h2>
    <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
        <thead>
            <tr style="border-bottom:2px solid #e5e7eb;">
                <th style="padding:8px 12px; text-align:left;">Cartório</th>
                <th style="padding:8px 12px; text-align:right;">Sugestões pendentes</th>
                <th style="padding:8px 12px;">Volume</th>
            </tr>
        </thead>
        <tbody>
            @php $maxPend = $cartoriosComPendencia->max('total') ?: 1; @endphp
            @foreach ($cartoriosComPendencia as $row)
                @php $bw = round($row->total / $maxPend * 100); @endphp
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:7px 12px; font-weight:600;">
                        [{{ $row->cartorio?->number ?? '?' }}] {{ $row->cartorio?->name ?? 'Desconhecido' }}
                    </td>
                    <td style="padding:7px 12px; text-align:right; color:#d97706; font-weight:700;">{{ $row->total }}</td>
                    <td style="padding:7px 12px; min-width:120px;">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <div class="hub-bar-track">
                                <div class="hub-bar-fill amber" style="width:{{ $bw }}%;"></div>
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div style="margin-top:10px;">
        <a href="{{ route('produtividade.flagrantes.index') }}" style="font-size:0.82rem; color:#2563eb;">
            Ver fila completa →
        </a>
    </div>
</section>
@endif

@endsection

