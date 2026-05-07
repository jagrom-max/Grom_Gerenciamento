@extends('layouts.app')

@section('title', 'Resultado da Importação | Grom.Seg')

@section('content')
<div class="section-head">
    <div>
        <h1>Resultado da importação</h1>
        <p class="muted" style="margin: 6px 0 0;">
            Arquivo: <strong>{{ $source }}</strong>
            @if ($periodo && $periodo->inicio)
                &mdash; Período: <strong>{{ $periodo->inicio }}</strong> a <strong>{{ $periodo->fim }}</strong>
            @endif
        </p>
    </div>
    <div class="actions">
        <a class="btn secondary" href="{{ route('analise.bos.import') }}">â† Nova importação</a>
        <a class="btn secondary" href="{{ route('analise.index') }}">Painel de análise</a>
        @if ($totalPendentes > 0)
            <a class="btn" style="background:#ef4444; color:#fff;" href="{{ route('analise.bos.auditoria-flagrantes') }}">
                ⚠ Auditoria de flagrantes ({{ $totalPendentes }})
            </a>
        @endif
    </div>
</div>

{{-- â"€â"€ KPIs do import â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ --}}
<div class="cards" style="margin-bottom: 20px;">
    <article class="card" style="text-align:center; padding:18px;">
        <small>Inseridos</small>
        <div style="font-size:2rem; font-weight:700; color:#059669;">{{ number_format($result['inserted']) }}</div>
        <span class="muted" style="font-size:0.78rem;">novos registros</span>
    </article>
    <article class="card" style="text-align:center; padding:18px;">
        <small>Atualizados</small>
        <div style="font-size:2rem; font-weight:700; color:#2563eb;">{{ number_format($result['updated']) }}</div>
        <span class="muted" style="font-size:0.78rem;">já existiam</span>
    </article>
    <article class="card" style="text-align:center; padding:18px;">
        <small>Ignorados</small>
        <div style="font-size:2rem; font-weight:700; color:#6b7280;">{{ number_format($result['skipped']) }}</div>
        <span class="muted" style="font-size:0.78rem;">sem SPJ válido</span>
    </article>
    <article class="card" style="text-align:center; padding:18px;">
        <small>Erros</small>
        <div style="font-size:2rem; font-weight:700; color:{{ $result['errors'] > 0 ? '#dc2626' : '#6b7280' }};">
            {{ number_format($result['errors']) }}
        </div>
        <span class="muted" style="font-size:0.78rem;">linhas com falha</span>
    </article>
    <article class="card" style="text-align:center; padding:18px;">
        <small>Flagrantes</small>
        <div style="font-size:2rem; font-weight:700; color:#dc2626;">{{ number_format($result['flagrantesTotal']) }}</div>
        <span class="muted" style="font-size:0.78rem;">prisões em flagrante</span>
    </article>
</div>

{{-- â"€â"€ Alerta de pendências de auditoria â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ --}}
@if ($pendenciasImport > 0)
<div style="background:#fef3c7; border:1px solid #f59e0b; border-radius:8px; padding:16px 20px; margin-bottom:20px; display:flex; align-items:center; gap:16px;">
    <div style="font-size:1.8rem;">⚠</div>
    <div style="flex:1;">
        <strong style="color:#92400e;">{{ $pendenciasImport }} flagrante{{ $pendenciasImport > 1 ? 's' : '' }} sem cartório atribuído</strong>
        <p style="margin:4px 0 0; color:#78350f; font-size:0.88rem;">
            Esses BOs foram marcados como <strong>flagrante</strong> na planilha, porém o campo
            <em>Cartório do IP</em> está vazio ou ausente. Eles entram automaticamente na
            <strong>lista de auditoria</strong> para que o cartório responsável seja
            atribuído ou o registro corrigido.
        </p>
    </div>
    <a class="btn" style="background:#f59e0b; color:#fff; white-space:nowrap;"
       href="{{ route('analise.bos.auditoria-flagrantes') }}">
        Revisar agora
    </a>
</div>
@endif

{{-- â"€â"€ Estatísticas de flagrantes por cartório do arquivo â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ --}}
@if ($porCartorio->isNotEmpty())
<section class="card" style="margin-bottom:20px;">
    <h2 style="margin-top:0;">Flagrantes do período por cartório</h2>
    <p class="muted" style="margin-bottom:12px; font-size:0.85rem;">
        Baseado nos registros do arquivo <strong>{{ $source }}</strong>.
    </p>

    @php
        $maxCart = $porCartorio->max('total') ?: 1;
        $totalArq = $porCartorio->sum('total');
        $flagTotal = $porCartorio->sum('flagrantes');
    @endphp

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
            <thead>
                <tr style="border-bottom:2px solid #e5e7eb;">
                    <th style="padding:8px 12px; text-align:left;">Cartório IP</th>
                    <th style="padding:8px 12px; text-align:right;">Total BOs</th>
                    <th style="padding:8px 12px; text-align:right;">Flagrantes</th>
                    <th style="padding:8px 12px; text-align:right;">Atos infrac.</th>
                    <th style="padding:8px 12px; text-align:right;">Taxa flag.</th>
                    <th style="padding:8px 12px;">Volume</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($porCartorio as $row)
                    @php
                        $taxa = $row->total > 0 ? round($row->flagrantes / $row->total * 100, 1) : 0;
                        $barW = $maxCart > 0 ? round($row->total / $maxCart * 100) : 0;
                        $semCart = $row->cartorio === 'Sem cartório';
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6; {{ $semCart ? 'background:#fff7ed;' : '' }}">
                        <td style="padding:7px 12px; font-weight:{{ $semCart ? '600' : '400' }}; color:{{ $semCart ? '#b45309' : 'inherit' }};">
                            {{ $row->cartorio }}
                            @if ($semCart)
                                <span style="font-size:0.72rem; background:#fef3c7; color:#92400e; padding:1px 6px; border-radius:10px; margin-left:6px;">pendente auditoria</span>
                            @endif
                        </td>
                        <td style="padding:7px 12px; text-align:right;">{{ number_format($row->total) }}</td>
                        <td style="padding:7px 12px; text-align:right; color:#dc2626; font-weight:600;">{{ number_format($row->flagrantes) }}</td>
                        <td style="padding:7px 12px; text-align:right;">{{ number_format($row->atos_infracionais) }}</td>
                        <td style="padding:7px 12px; text-align:right;">
                            <span style="display:inline-block; padding:1px 7px; border-radius:10px; font-size:0.75rem; font-weight:600;
                                background:{{ $taxa >= 20 ? '#fef2f2' : ($taxa >= 10 ? '#fffbeb' : '#f0fdf4') }};
                                color:{{ $taxa >= 20 ? '#dc2626' : ($taxa >= 10 ? '#d97706' : '#16a34a') }};">
                                {{ number_format($taxa, 1) }}%
                            </span>
                        </td>
                        <td style="padding:7px 12px; min-width:100px;">
                            <div style="display:flex; align-items:center; gap:6px;">
                                <div style="flex:1; height:7px; background:#e5e7eb; border-radius:4px; overflow:hidden;">
                                    <div style="width:{{ $barW }}%; height:100%; background:#3b82f6; border-radius:4px;"></div>
                                </div>
                                <small style="color:#9ca3af; white-space:nowrap;">{{ $barW }}%</small>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid #e5e7eb; font-weight:700; background:#f9fafb;">
                    <td style="padding:8px 12px;">Total</td>
                    <td style="padding:8px 12px; text-align:right;">{{ number_format($totalArq) }}</td>
                    <td style="padding:8px 12px; text-align:right; color:#dc2626;">{{ number_format($flagTotal) }}</td>
                    <td style="padding:8px 12px; text-align:right;">{{ number_format($porCartorio->sum('atos_infracionais')) }}</td>
                    <td style="padding:8px 12px; text-align:right;">
                        @php $taxaGeral = $totalArq > 0 ? round($flagTotal / $totalArq * 100, 1) : 0; @endphp
                        <span style="font-weight:700;">{{ number_format($taxaGeral, 1) }}%</span>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>
@endif

@endsection

