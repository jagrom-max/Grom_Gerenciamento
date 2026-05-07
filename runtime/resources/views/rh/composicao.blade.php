@extends('layouts.app')

@section('title', 'Composição dos Cartórios | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Composição dos Cartórios</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Efetivo atual por setor — servidores ativos, afastamentos em vigor e participação na escala.
            </p>
        </div>
        <div class="actions">
            <a href="{{ route('rh.composicao.print') }}" class="btn secondary" target="_blank">Imprimir A4</a>
            <a href="{{ route('rh.index') }}" class="btn secondary">← RH/Admin</a>
        </div>
    </div>

    {{-- Estatísticas gerais --}}
    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Total de servidores ativos</small>
            <strong>{{ $estatisticas['total_ativos'] }}</strong>
            <span>Funcionários com status ativo no sistema.</span>
        </article>
        <article class="card">
            <small>Concorrem à escala</small>
            <strong>{{ $estatisticas['concorrem_escala'] }}</strong>
            <span>Aptos para escalação no mês em vigor.</span>
        </article>
        <article class="card">
            <small>Em afastamento hoje</small>
            <strong style="color: {{ $estatisticas['em_afastamento'] > 0 ? '#c0392b' : 'inherit' }}">
                {{ $estatisticas['em_afastamento'] }}
            </strong>
            <span>Afastamentos que abrangem {{ $hoje->format('d/m/Y') }}.</span>
        </article>
        <article class="card">
            <small>Setores / cartórios</small>
            <strong>{{ $estatisticas['setores'] }}</strong>
            <span>Unidades organizacionais mapeadas.</span>
        </article>
    </div>

    {{-- Tabela por setor --}}
    @forelse ($porSetor as $setor => $funcionarios)
        <section class="card" style="margin-bottom: 18px;">
            <h2 style="margin-top: 0; border-bottom: 1px solid #e0e0e0; padding-bottom: 8px;">
                {{ $setor }}
                <span class="muted" style="font-size: 0.85rem; font-weight: normal; margin-left: 8px;">
                    — {{ $funcionarios->count() }} servidor(es) ativo(s)
                </span>
            </h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Matrícula</th>
                            <th>Nome</th>
                            <th>Cargo</th>
                            <th>Escala</th>
                            <th>Status atual</th>
                            <th>Afastamento vigente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($funcionarios->sortBy('name') as $f)
                            @php
                                $afAtual = $f->afastamentos->first();
                                $emAfastamento = $afAtual !== null;
                            @endphp
                            <tr @if ($emAfastamento) style="background: #fdf5e6;" @endif>
                                <td>
                                    <code style="font-size: 0.85rem;">{{ $f->matricula }}</code>
                                    @if ($f->legacy_id)
                                        <span class="badge" style="background:#e8f4f8;color:#1a6a9a;font-size:0.7rem;padding:1px 5px;border-radius:3px;margin-left:4px;">LEG</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $f->name }}</strong>
                                    @if ($f->short_name && $f->short_name !== $f->name)
                                        <br><span class="muted" style="font-size: 0.82rem;">{{ $f->short_name }}</span>
                                    @endif
                                </td>
                                <td>{{ $f->cargo?->name ?? '—' }}</td>
                                <td style="text-align: center;">
                                    @if ($f->concorre_escala)
                                        <span style="color: #27ae60; font-weight: bold;">✓</span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($emAfastamento)
                                        <span style="background: #fff3cd; color: #856404; padding: 2px 7px; border-radius: 3px; font-size: 0.82rem; font-weight: bold;">
                                            Afastado(a)
                                        </span>
                                    @else
                                        <span style="background: #d4edda; color: #155724; padding: 2px 7px; border-radius: 3px; font-size: 0.82rem;">
                                            Presente
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if ($emAfastamento)
                                        <strong>{{ $afAtual->reason }}</strong><br>
                                        <span class="muted" style="font-size: 0.8rem;">
                                            @php
                                                $ini = \Illuminate\Support\Carbon::parse($afAtual->start_date);
                                                $fim = $afAtual->end_date ? \Illuminate\Support\Carbon::parse($afAtual->end_date) : null;
                                            @endphp
                                            {{ $ini->format('d/m/Y') }} →
                                            {{ $fim ? $fim->format('d/m/Y') : 'Em aberto' }}
                                        </span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <section class="card">
            <p class="muted" style="text-align: center; padding: 24px 0;">
                Nenhum funcionário ativo cadastrado no sistema.
            </p>
        </section>
    @endforelse

    <div style="margin-top: 10px; text-align: right; font-size: 0.8rem;" class="muted">
        Gerado em {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->name }}.
    </div>
@endsection

