@extends('layouts.app')

@section('title', 'Estatísticas de Mandados')

@section('content')
@php use Carbon\Carbon; @endphp

<div class="section-head">
    <h1>Estatísticas de Mandados</h1>
</div>

{{-- ── Filtros ─────────────────────────────────────────────────────────── --}}
<form method="GET" class="form-grid" style="--cols:3; margin-bottom:1.5rem; align-items:flex-end; gap:.75rem">
    <div class="field">
        <label>Ano</label>
        <input type="number" name="year" min="2020" max="2100" value="{{ $year }}" style="width:90px">
    </div>
    <div class="field">
        <label>Mês</label>
        <select name="month">
            <option value="0" @selected($month === 0)>Todo o ano</option>
            @foreach ([1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'] as $i => $l)
                <option value="{{ $i }}" @selected($month === $i)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" style="justify-self:start;">
        <label>&nbsp;</label>
        <button type="submit" class="btn">Filtrar</button>
    </div>
</form>

{{-- ── Cards de totais ─────────────────────────────────────────────────── --}}
<div class="cards" style="margin-bottom:1.5rem">
    <div class="card" style="text-align:center">
        <small style="display:block; font-size:.75rem; color:#666; margin-bottom:4px">Total de mandados</small>
        <strong style="font-size:2rem; color:#2c3e50">{{ $totalGeral }}</strong>
        <small style="display:block; margin-top:4px; color:#777">{{ $periodoLabel }}</small>
    </div>
    <div class="card" style="text-align:center">
        <small style="display:block; font-size:.75rem; color:#666; margin-bottom:4px">Em Aberto</small>
        <strong style="font-size:2rem; color:#e67e22">{{ $totalEmAberto }}</strong>
    </div>
    <div class="card" style="text-align:center">
        <small style="display:block; font-size:.75rem; color:#666; margin-bottom:4px">Cumpridos</small>
        <strong style="font-size:2rem; color:#27ae60">{{ $totalCumprido }}</strong>
    </div>
    <div class="card" style="text-align:center">
        <small style="display:block; font-size:.75rem; color:#666; margin-bottom:4px">Revogados</small>
        <strong style="font-size:2rem; color:#7f8c8d">{{ $totalRevogado }}</strong>
    </div>
    <div class="card" style="text-align:center">
        <small style="display:block; font-size:.75rem; color:#666; margin-bottom:4px">Vencidos (em aberto)</small>
        <strong style="font-size:2rem; color:#e74c3c">{{ $totalVencidos }}</strong>
    </div>
</div>

{{-- ── Por tipo de mandado ─────────────────────────────────────────────── --}}
<div class="section-head" style="margin-bottom:.75rem">
    <h2>Por Tipo de Mandado</h2>
</div>

<div style="overflow-x:auto; margin-bottom:2rem">
    <table>
        <thead>
            <tr>
                <th>Sigla</th>
                <th>Tipo</th>
                <th style="text-align:right">Total</th>
                <th style="text-align:right">Em Aberto</th>
                <th style="text-align:right">Cumpridos</th>
                <th style="text-align:right">Revogados</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($porTipo->sortByDesc('total') as $t)
                @if ($t['total'] > 0)
                <tr>
                    <td><span class="tag">{{ $t['sigla'] }}</span></td>
                    <td>{{ $t['label'] }}</td>
                    <td style="text-align:right"><strong>{{ $t['total'] }}</strong></td>
                    <td style="text-align:right; color:#e67e22">{{ $t['em_aberto'] }}</td>
                    <td style="text-align:right; color:#27ae60">{{ $t['cumpridos'] }}</td>
                    <td style="text-align:right; color:#7f8c8d">{{ $t['revogados'] }}</td>
                </tr>
                @endif
            @empty
                <tr><td colspan="6" style="text-align:center; color:#888; font-style:italic">Nenhum mandado no período selecionado.</td></tr>
            @endforelse
            @if ($totalGeral > 0)
            <tr style="font-weight:bold; background:#f0f0f0">
                <td colspan="2" style="text-align:right">Total</td>
                <td style="text-align:right">{{ $totalGeral }}</td>
                <td style="text-align:right; color:#e67e22">{{ $totalEmAberto }}</td>
                <td style="text-align:right; color:#27ae60">{{ $totalCumprido }}</td>
                <td style="text-align:right; color:#7f8c8d">{{ $totalRevogado }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>

{{-- ── Histórico mensal ────────────────────────────────────────────────── --}}
<div class="section-head" style="margin-bottom:.75rem">
    <h2>Histórico Mensal — {{ $year }}</h2>
</div>

<div style="overflow-x:auto; margin-bottom:2rem">
    <table>
        <thead>
            <tr>
                @foreach ($historicoMensal as $h)
                    <th style="text-align:center; font-size:.8rem">{{ $h['mes'] }}</th>
                @endforeach
                <th style="text-align:center">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach ($historicoMensal as $h)
                    <td style="text-align:center">
                        @if ($h['total'] > 0)
                            <strong>{{ $h['total'] }}</strong>
                        @else
                            <span style="color:#ccc">—</span>
                        @endif
                    </td>
                @endforeach
                <td style="text-align:center; font-weight:bold">{{ collect($historicoMensal)->sum('total') }}</td>
            </tr>
            <tr style="font-size:.8rem; color:#666">
                @foreach ($historicoMensal as $h)
                    <td style="text-align:center">
                        @if ($h['em_aberto'] > 0)
                            <span style="color:#e67e22">{{ $h['em_aberto'] }}</span>
                        @else
                            —
                        @endif
                    </td>
                @endforeach
                <td style="text-align:center; color:#e67e22">{{ collect($historicoMensal)->sum('em_aberto') }}</td>
            </tr>
        </tbody>
    </table>
    <small style="color:#888">Linha superior: total emitido no mês. Linha inferior: em aberto no período.</small>
</div>

{{-- ── Link para ver todos ──────────────────────────────────────────────── --}}
<div style="margin-top:1rem">
    <a href="{{ route('operacional.mandados.index') }}" class="btn" style="text-decoration:none">← Ver lista completa de mandados</a>
</div>

@endsection
