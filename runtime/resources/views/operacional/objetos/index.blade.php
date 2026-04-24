@extends('layouts.app')

@section('title', 'Objetos Apreendidos | Grom.Seg')

@section('content')

    {{-- === CABECALHO ============================================================= --}}
    <div class="section-head">
        <div>
            <h1>Objetos Apreendidos</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Controle operacional de custódia, IC, laudo e destinação de objetos apreendidos.
            </p>
        </div>
        @if (auth()->user()->hasPermission('operacional.objetos.manage'))
            <div class="actions">
                <button type="button" onclick="document.getElementById('modal-cadastro').showModal()">+ Novo Objeto</button>
                <button type="button" onclick="document.getElementById('modal-locais').showModal()" style="background:var(--color-secondary,#555);">Locais de Custódia</button>
            </div>
        @endif
    </div>

    {{-- === FLASH ================================================================ --}}
    @if (session('status'))
        <div class="alert alert-success" role="alert" style="margin-bottom: 12px;">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert" style="margin-bottom: 12px;">{{ session('error') }}</div>
    @endif

    {{-- === KPIs ================================================================= --}}
    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Total cadastrado</small>
            <strong>{{ $summary['total'] }}</strong>
            <span>Objetos na base web.</span>
        </article>
        <article class="card">
            <small>Em Custódia / IC</small>
            <strong>{{ $summary['em_custodia'] }}</strong>
            <span>Sob guarda ou aguardando retorno do IC.</span>
        </article>
        <article class="card {{ $summary['aguard_destinacao'] > 0 ? 'card--alert' : '' }}">
            <small>Aguardando Destinação</small>
            <strong>{{ $summary['aguard_destinacao'] }}</strong>
            <span>Pendentes de autorização ou conclusão.</span>
        </article>
        <article class="card">
            <small>Restituídos</small>
            <strong>{{ $summary['restituidos'] }}</strong>
            <span>Devolvidos ao proprietário.</span>
        </article>
        <article class="card">
            <small>Destruídos</small>
            <strong>{{ $summary['destruidos'] }}</strong>
            <span>Destinação concluída por destruição.</span>
        </article>
        <article class="card">
            <small>Exibidos</small>
            <strong>{{ $summary['exibidos'] }}</strong>
            <span>Resultado do filtro atual.</span>
        </article>
        <article class="card">
            <small>Locais ativos</small>
            <strong>{{ $summary['locais_ativos'] }}</strong>
            <span>Locais de custódia disponíveis.</span>
        </article>
    </div>

    {{-- === FILTROS ============================================================== --}}
    <section class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('operacional.objetos.index') }}" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <div>
                <label for="f-q" style="display:block; font-size:.8rem; margin-bottom:4px;">Busca (objeto / RDO / IP / lacre / série)</label>
                <input id="f-q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="Digite para filtrar…" style="min-width:260px;">
            </div>
            <div>
                <label for="f-sit" style="display:block; font-size:.8rem; margin-bottom:4px;">Situação</label>
                <select id="f-sit" name="situacao">
                    <option value="">Todas</option>
                    @foreach ($situacoes as $s)
                        <option value="{{ $s }}" {{ ($filters['situacao'] ?? '') === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-local" style="display:block; font-size:.8rem; margin-bottom:4px;">Local de Custódia</label>
                <select id="f-local" name="local_id">
                    <option value="">Todos</option>
                    @foreach ($locais as $loc)
                        <option value="{{ $loc->id }}" {{ ($filters['local_id'] ?? '') == $loc->id ? 'selected' : '' }}>{{ $loc->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-ano" style="display:block; font-size:.8rem; margin-bottom:4px;">Ano</label>
                <input id="f-ano" name="ano" type="number" value="{{ $filters['ano'] ?? '' }}" placeholder="{{ date('Y') }}" style="width:90px;" min="2000" max="2100">
            </div>
            <button type="submit">Filtrar</button>
            <a href="{{ route('operacional.objetos.index') }}" style="font-size:.85rem; color:var(--color-muted,#888);">Limpar</a>
        </form>
    </section>

    {{-- === TABELA =============================================================== --}}
    <section class="card" style="padding:0;">
        @if ($objetos->isEmpty())
            <p style="padding:28px; text-align:center; color:var(--color-muted,#888);">
                Nenhum objeto encontrado com os critérios informados.
            </p>
        @else
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                    <tr>
                        <th>RDO</th>
                        <th>Lacre</th>
                        <th>IP/TC-DDM</th>
                        <th>Descrição</th>
                        <th>Qtd</th>
                        <th>Custódia</th>
                        <th>Caixa</th>
                        <th>Situação</th>
                        <th>Laudo</th>
                        @if (auth()->user()->hasPermission('operacional.objetos.manage'))
                            <th style="text-align:right;">Ações</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($objetos as $obj)
                        <tr>
                            <td>{{ $obj->rdo_formatado }}</td>
                            <td>{{ $obj->lacre ?: '—' }}</td>
                            <td>
                                {{ $obj->ip_tc_ddm ?: '—' }}
                                @if ($obj->ip_externo)
                                    <br><small class="muted">{{ $obj->ip_externo }}</small>
                                @endif
                            </td>
                            <td>
                                {{ \Illuminate\Support\Str::limit($obj->objeto, 70) }}
                                @if ($obj->marca || $obj->modelo)
                                    <br><small class="muted">{{ implode(' / ', array_filter([$obj->marca, $obj->modelo])) }}</small>
                                @endif
                                @if ($obj->numero_serie)
                                    <br><small class="muted">Série: {{ $obj->numero_serie }}</small>
                                @endif
                            </td>
                            <td>{{ $obj->quantidade }} {{ $obj->unidade }}</td>
                            <td>{{ $obj->localCustodia?->nome ?? '—' }}</td>
                            <td>{{ $obj->caixa ?: '—' }}</td>
                            <td>
                                <span class="badge badge--sit badge--sit-{{ \Illuminate\Support\Str::slug($obj->situacao) }}">
                                    {{ $obj->situacao }}
                                </span>
                            </td>
                            <td>{{ $obj->laudo ?: '—' }}</td>
                            @if (auth()->user()->hasPermission('operacional.objetos.manage'))
                                <td style="text-align:right; white-space:nowrap;">
                                    <button type="button"
                                        onclick="abrirEdicaoObjeto({{ Js::from($obj->toArray()) }})"
                                        style="font-size:.8rem; padding:3px 10px;">
                                        Editar
                                    </button>
                                    <button type="button"
                                        onclick="confirmarExclusaoObjeto('{{ $obj->id }}', '{{ e(\Illuminate\Support\Str::limit($obj->objeto, 40)) }}')"
                                        style="font-size:.8rem; padding:3px 10px; background:var(--color-danger,#c0392b);">
                                        Excluir
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- === MODAIS =============================================================== --}}
    @if (auth()->user()->hasPermission('operacional.objetos.manage'))

        {{-- Cadastro --}}
        <dialog id="modal-cadastro" class="grom-modal grom-modal--lg">
            <form method="POST" action="{{ route('operacional.objetos.store') }}" class="grom-modal-card">
                @csrf
                <div class="grom-modal-head">
                    <strong>Novo Objeto Apreendido</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="this.closest('dialog').close()">x</button>
                </div>
                @include('operacional.objetos._form', ['objeto' => null])
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
                    <button type="button" onclick="this.closest('dialog').close()">Cancelar</button>
                    <button type="submit">Salvar</button>
                </div>
            </form>
        </dialog>

        {{-- Edição --}}
        <dialog id="modal-edicao" class="grom-modal grom-modal--lg">
            <form method="POST" id="form-edicao-obj" class="grom-modal-card">
                @csrf
                @method('PUT')
                <div class="grom-modal-head">
                    <strong>Editar Objeto</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="this.closest('dialog').close()">x</button>
                </div>
                @include('operacional.objetos._form', ['objeto' => null, 'edit' => true])
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
                    <button type="button" onclick="this.closest('dialog').close()">Cancelar</button>
                    <button type="submit">Atualizar</button>
                </div>
            </form>
        </dialog>

        {{-- Exclusão --}}
        <dialog id="modal-exclusao" class="grom-modal grom-modal--sm">
            <form method="POST" id="form-excl-obj" class="grom-modal-card">
                @csrf
                @method('DELETE')
                <div class="grom-modal-head">
                    <strong>Excluir Objeto</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="this.closest('dialog').close()">x</button>
                </div>
                <p>Excluir: <strong id="excl-obj-desc"></strong></p>
                <div>
                    <label for="excl-obj-motivo" style="display:block; margin-bottom:6px;">Motivo <span style="color:#c0392b">*</span></label>
                    <textarea id="excl-obj-motivo" name="motivo" rows="3" required minlength="5" maxlength="500" style="width:100%;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
                    <button type="button" onclick="this.closest('dialog').close()">Cancelar</button>
                    <button type="submit" style="background:var(--color-danger,#c0392b);">Confirmar Exclusão</button>
                </div>
            </form>
        </dialog>

        {{-- Gerenciar locais de custódia --}}
        <dialog id="modal-locais" class="grom-modal grom-modal--md">
            <div class="grom-modal-card">
                <div class="grom-modal-head">
                    <strong>Locais de Custódia</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="this.closest('dialog').close()">x</button>
                </div>

                <form method="POST" action="{{ route('operacional.objetos.locais.store') }}" style="display:flex; gap:8px; margin-bottom:16px;">
                    @csrf
                    <input type="text" name="nome" placeholder="Nome do novo local…" required minlength="2" maxlength="100" style="flex:1;">
                    <button type="submit">Adicionar</button>
                </form>

                <table style="width:100%;">
                    <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($locais as $loc)
                        <tr>
                            <td>{{ $loc->nome }}</td>
                            <td>{{ $loc->is_active ? 'Ativo' : 'Inativo' }}</td>
                            <td>
                                <form method="POST" action="{{ route('operacional.objetos.locais.toggle', $loc) }}" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" style="font-size:.8rem; padding:3px 10px; background:{{ $loc->is_active ? '#c0392b' : '#27ae60' }};">
                                        {{ $loc->is_active ? 'Desativar' : 'Ativar' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </dialog>

    @endif

@endsection

@section('scripts')
<script>
function abrirEdicaoObjeto(data) {
    const form = document.getElementById('form-edicao-obj');
    const dialog = document.getElementById('modal-edicao');
    form.action = '/operacional/objetos/' + data.id;
    const set = (name, val) => {
        const el = form.querySelector('[name="' + name + '"]');
        if (el) el.value = val ?? '';
    };
    ['rdo_num','ano','lacre','ip_tc_ddm','ip_externo','tipo_objeto','objeto',
     'quantidade','unidade','marca','modelo','cor','numero_serie',
     'ic_remessa','ic_retorno','lacre_ic','laudo','local_custodia_id','caixa',
     'situacao','dest_solicitado','dest_data_solicitado','dest_autorizado',
     'dest_data_autorizado','dest_status','dest_data','observacoes'].forEach(k => set(k, data[k]));
    dialog.showModal();
}
function confirmarExclusaoObjeto(id, desc) {
    const form = document.getElementById('form-excl-obj');
    form.action = '/operacional/objetos/' + id;
    document.getElementById('excl-obj-desc').textContent = desc;
    document.getElementById('excl-obj-motivo').value = '';
    document.getElementById('modal-exclusao').showModal();
}
</script>
@endsection
