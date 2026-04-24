@extends('layouts.app')

@section('title', 'Ordens de Serviço')

@section('content')

<div class="section-head">
    <h1>Ordens de Serviço</h1>
    @if (auth()->user()->hasPermission('operacional.ordens.manage'))
        <button type="button" class="btn" onclick="document.getElementById('modal-nova-os').showModal()">+ Nova OS</button>
    @endif
</div>

@if (session('status'))
    <div class="alert good">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert warn">{{ $errors->first() }}</div>
@endif

{{-- ── KPI Cards ────────────────────────────────────────────────────────── --}}
<div class="cards" style="margin-bottom:1.5rem">
    <article class="card" style="text-align:center">
        <small>Total de OS</small>
        <strong>{{ $summary['total'] }}</strong>
    </article>
    <article class="card" style="text-align:center">
        <small>Abertas</small>
        <strong style="color:#e67e22">{{ $summary['abertas'] }}</strong>
    </article>
    <article class="card" style="text-align:center">
        <small>Em andamento</small>
        <strong>{{ $summary['em_andamento'] }}</strong>
    </article>
    <article class="card" style="text-align:center">
        <small>Concluídas</small>
        <strong style="color:#27ae60">{{ $summary['concluidas'] }}</strong>
    </article>
    <article class="card" style="text-align:center">
        <small>Vencidas</small>
        <strong style="color:#e74c3c">{{ $summary['vencidas'] }}</strong>
    </article>
</div>

{{-- ── Filtros ─────────────────────────────────────────────────────────── --}}
<form method="GET" class="form-grid" style="--cols:4; margin-bottom:1.5rem; align-items:flex-end; gap:.75rem">
    <div class="field">
        <label>Busca</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Assunto, número, solicitante…">
    </div>
    <div class="field">
        <label>Status</label>
        <select name="status">
            <option value="">Todos</option>
            @foreach (\App\Models\OperacionalOrdemServico::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Tipo</label>
        <select name="tipo">
            <option value="">Todos os tipos</option>
            @foreach (\App\Models\OperacionalOrdemServico::TIPOS as $t)
                <option value="{{ $t }}" @selected(request('tipo') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" style="display:flex; gap:8px; align-items:flex-end;">
        <label style="white-space:nowrap">
            <input type="checkbox" name="vencidas" value="1" @checked(request('vencidas'))>
            Somente vencidas
        </label>
        <button type="submit" class="btn">Filtrar</button>
    </div>
</form>

{{-- ── Tabela ───────────────────────────────────────────────────────────── --}}
<div style="overflow-x:auto">
    <table>
        <thead>
            <tr>
                <th>Nº / Data</th>
                <th>Assunto</th>
                <th>Tipo</th>
                <th>Responsável</th>
                <th>Prazo</th>
                <th>Status</th>
                @if (auth()->user()->hasPermission('operacional.ordens.manage'))
                    <th style="width:1%">Ações</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($ordens as $os)
                <tr>
                    <td>
                        {{ $os->numero ?: '—' }}<br>
                        <span class="muted" style="font-size:.76rem">{{ $os->data_emissao?->format('d/m/Y') ?: '—' }}</span>
                    </td>
                    <td>
                        <strong>{{ $os->assunto }}</strong>
                        @if ($os->descricao)
                            <br><span class="muted" style="font-size:.76rem">{{ Str::limit($os->descricao, 80) }}</span>
                        @endif
                    </td>
                    <td>{{ $os->tipo ?: '—' }}</td>
                    <td>{{ $os->responsavel ?: $os->solicitante ?: '—' }}</td>
                    <td>
                        @if ($os->data_prazo)
                            <span @class(['tag', $os->esta_atrasada ? 'danger' : ''])>{{ $os->data_prazo->format('d/m/Y') }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <span class="tag {{ $os->status_tone }}">{{ $os->status }}</span>
                    </td>
                    @if (auth()->user()->hasPermission('operacional.ordens.manage'))
                        <td>
                            <div class="actions" style="gap:4px; flex-wrap:nowrap">
                                <button type="button" class="secondary" style="font-size:.78rem; padding:2px 8px"
                                    onclick="document.getElementById('edit-os-{{ $os->id }}').showModal()">Editar</button>
                                <button type="button" class="secondary" style="font-size:.78rem; padding:2px 8px; color:#c0392b"
                                    onclick="document.getElementById('delete-os-{{ $os->id }}').showModal()">Excluir</button>
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ auth()->user()->hasPermission('operacional.ordens.manage') ? 7 : 6 }}" class="muted" style="text-align:center">
                        Nenhuma ordem de serviço encontrada.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if (auth()->user()->hasPermission('operacional.ordens.manage'))

    {{-- ── Modal: Nova OS ─────────────────────────────────────────────── --}}
    <dialog id="modal-nova-os" class="grom-modal grom-modal--lg">
        <form method="POST" action="{{ route('operacional.ordens.store') }}" class="grom-modal-card">
            @csrf
            <div class="grom-modal-head">
                <strong>Nova Ordem de Serviço</strong>
                <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('modal-nova-os').close()">x</button>
            </div>
            <div class="form-grid" style="--cols:2; gap:.75rem">
                <div class="field">
                    <label>Número <span class="muted">(opcional)</span></label>
                    <input type="text" name="numero" maxlength="30">
                </div>
                <div class="field">
                    <label>Data de Emissão</label>
                    <input type="date" name="data_emissao" value="{{ date('Y-m-d') }}">
                </div>
                <div class="field" style="grid-column:1/-1">
                    <label>Assunto *</label>
                    <input type="text" name="assunto" required maxlength="255" placeholder="Descrição resumida da OS">
                </div>
                <div class="field">
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="">— selecione —</option>
                        @foreach (\App\Models\OperacionalOrdemServico::TIPOS as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status" required>
                        @foreach (\App\Models\OperacionalOrdemServico::STATUSES as $s)
                            <option value="{{ $s }}" @selected($s === 'Aberta')>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Solicitante</label>
                    <input type="text" name="solicitante" maxlength="120">
                </div>
                <div class="field">
                    <label>Responsável</label>
                    <input type="text" name="responsavel" maxlength="120">
                </div>
                <div class="field">
                    <label>Prazo</label>
                    <input type="date" name="data_prazo">
                </div>
                <div class="field">
                    <label>Data de Conclusão</label>
                    <input type="date" name="data_conclusao">
                </div>
                <div class="field" style="grid-column:1/-1">
                    <label>Descrição</label>
                    <textarea name="descricao" rows="3" placeholder="Detalhes da ordem de serviço…"></textarea>
                </div>
                <div class="field" style="grid-column:1/-1">
                    <label>Resultado / Observação</label>
                    <textarea name="resultado" rows="2" placeholder="Registro do resultado (preencher na conclusão)"></textarea>
                </div>
            </div>
            <div class="actions" style="margin-top:1rem; justify-content:flex-end">
                <button type="button" class="secondary" onclick="document.getElementById('modal-nova-os').close()">Cancelar</button>
                <button type="submit" class="btn">Salvar</button>
            </div>
        </form>
    </dialog>

    {{-- ── Modais: Editar / Excluir por OS ────────────────────────────── --}}
    @foreach ($ordens as $os)
        <dialog id="edit-os-{{ $os->id }}" class="grom-modal grom-modal--lg">
            <form method="POST" action="{{ route('operacional.ordens.update', $os) }}" class="grom-modal-card">
                @csrf
                @method('PUT')
                <div class="grom-modal-head">
                    <strong>Editar OS{{ $os->numero ? " #{$os->numero}" : '' }}</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('edit-os-{{ $os->id }}').close()">x</button>
                </div>
                <div class="form-grid" style="--cols:2; gap:.75rem">
                    <div class="field">
                        <label>Número</label>
                        <input type="text" name="numero" value="{{ $os->numero }}" maxlength="30">
                    </div>
                    <div class="field">
                        <label>Data de Emissão</label>
                        <input type="date" name="data_emissao" value="{{ $os->data_emissao?->format('Y-m-d') }}">
                    </div>
                    <div class="field" style="grid-column:1/-1">
                        <label>Assunto *</label>
                        <input type="text" name="assunto" required maxlength="255" value="{{ $os->assunto }}">
                    </div>
                    <div class="field">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="">— selecione —</option>
                            @foreach (\App\Models\OperacionalOrdemServico::TIPOS as $t)
                                <option value="{{ $t }}" @selected($os->tipo === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Status</label>
                        <select name="status" required>
                            @foreach (\App\Models\OperacionalOrdemServico::STATUSES as $s)
                                <option value="{{ $s }}" @selected($os->status === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Solicitante</label>
                        <input type="text" name="solicitante" value="{{ $os->solicitante }}" maxlength="120">
                    </div>
                    <div class="field">
                        <label>Responsável</label>
                        <input type="text" name="responsavel" value="{{ $os->responsavel }}" maxlength="120">
                    </div>
                    <div class="field">
                        <label>Prazo</label>
                        <input type="date" name="data_prazo" value="{{ $os->data_prazo?->format('Y-m-d') }}">
                    </div>
                    <div class="field">
                        <label>Data de Conclusão</label>
                        <input type="date" name="data_conclusao" value="{{ $os->data_conclusao?->format('Y-m-d') }}">
                    </div>
                    <div class="field" style="grid-column:1/-1">
                        <label>Descrição</label>
                        <textarea name="descricao" rows="3">{{ $os->descricao }}</textarea>
                    </div>
                    <div class="field" style="grid-column:1/-1">
                        <label>Resultado / Observação</label>
                        <textarea name="resultado" rows="2">{{ $os->resultado }}</textarea>
                    </div>
                </div>
                <div class="actions" style="margin-top:1rem; justify-content:flex-end">
                    <button type="button" class="secondary" onclick="document.getElementById('edit-os-{{ $os->id }}').close()">Cancelar</button>
                    <button type="submit" class="btn">Salvar alterações</button>
                </div>
            </form>
        </dialog>

        <dialog id="delete-os-{{ $os->id }}" class="grom-modal grom-modal--sm">
            <form method="POST" action="{{ route('operacional.ordens.destroy', $os) }}" class="grom-modal-card">
                @csrf
                @method('DELETE')
                <div class="grom-modal-head">
                    <strong style="color:#b42318;">Excluir OS</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('delete-os-{{ $os->id }}').close()">x</button>
                </div>
                <p style="margin-bottom:.75rem">Excluir <strong>{{ $os->assunto }}</strong>?</p>
                <div class="field">
                    <label>Motivo da exclusão *</label>
                    <input type="text" name="motivo" required placeholder="Informe o motivo…" style="width:100%">
                </div>
                <div class="actions" style="margin-top:1rem; justify-content:flex-end">
                    <button type="button" class="secondary" onclick="document.getElementById('delete-os-{{ $os->id }}').close()">Cancelar</button>
                    <button type="submit" style="background:#c0392b; color:#fff; border:0; padding:7px 16px; border-radius:6px; cursor:pointer">Excluir</button>
                </div>
            </form>
        </dialog>
    @endforeach

@endif

@endsection
