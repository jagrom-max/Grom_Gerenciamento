@extends('layouts.app')

@section('title', 'Mandados de Prisão | Grom.Seg')

@section('content')

    {{-- === CABECALHO ============================================================= --}}
    <div class="section-head">
        <div>
            <h1>Mandados de Prisão</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Controle de mandados (MPP, MPT, MPD, MPC, MBA, MAM), procedimento e cumprimento.
            </p>
        </div>
        @if (auth()->user()->hasPermission('operacional.mandados.manage'))
            <div class="actions">
                <button type="button" onclick="document.getElementById('modal-cadastro').showModal()">+ Novo Mandado</button>

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
    @foreach ($legacyWarnings as $warn)
        <div class="alert alert-warning" role="alert" style="margin-bottom: 6px;">Aviso legado: {{ $warn }}</div>
    @endforeach

    {{-- === KPIs ================================================================= --}}
    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Total cadastrado</small>
            <strong>{{ $summary['total'] }}</strong>
            <span>Mandados na base web.</span>
        </article>
        <article class="card">
            <small>Em Aberto</small>
            <strong>{{ $summary['em_aberto'] }}</strong>
            <span>Aguardando cumprimento.</span>
        </article>
        <article class="card">
            <small>Cumpridos</small>
            <strong>{{ $summary['cumpridos'] }}</strong>
            <span>Finalizados com registro de cumprimento.</span>
        </article>
        <article class="card">
            <small>Revogados</small>
            <strong>{{ $summary['revogados'] }}</strong>
            <span>Cancelados pelo juízo.</span>
        </article>
        <article class="card {{ $summary['vencidos'] > 0 ? 'card--alert' : '' }}">
            <small>Vencidos (Em Aberto)</small>
            <strong>{{ $summary['vencidos'] }}</strong>
            <span>Validade expirada sem cumprimento.</span>
        </article>
        <article class="card">
            <small>Exibidos</small>
            <strong>{{ $summary['exibidos'] }}</strong>
            <span>Resultado do filtro atual.</span>
        </article>

    </div>

    {{-- === FILTROS ============================================================== --}}
    <section class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('operacional.mandados.index') }}" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <div>
                <label for="f-q" style="display:block; font-size:.8rem; margin-bottom:4px;">Busca (nome / CPF / RG / CNJ)</label>
                <input id="f-q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="Digite para filtrar…" style="min-width:260px;">
            </div>
            <div>
                <label for="f-tipo" style="display:block; font-size:.8rem; margin-bottom:4px;">Tipo</label>
                <select id="f-tipo" name="tipo_sigla">
                    <option value="todos">Todos os tipos</option>
                    @foreach ($tiposSigla as $sigla => $descricao)
                        <option value="{{ $sigla }}" {{ ($filters['tipo_sigla'] ?? '') === $sigla ? 'selected' : '' }}>
                            {{ $sigla }} — {{ $descricao }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-proc" style="display:block; font-size:.8rem; margin-bottom:4px;">Procedimento</label>
                <select id="f-proc" name="procedimento">
                    <option value="todos">Todos</option>
                    @foreach ($procedimentos as $proc)
                        <option value="{{ $proc }}" {{ ($filters['procedimento'] ?? '') === $proc ? 'selected' : '' }}>{{ $proc }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:flex; align-items:center; gap:6px; font-size:.8rem;">
                    <input type="checkbox" name="vencidos" value="1" {{ !empty($filters['vencidos']) ? 'checked' : '' }}>
                    Apenas vencidos
                </label>
            </div>
            <button type="submit">Filtrar</button>
            <a href="{{ route('operacional.mandados.index') }}" style="font-size:.85rem; color:var(--color-muted,#888);">Limpar</a>
        </form>
    </section>

    {{-- === TABELA =============================================================== --}}
    <section class="card" style="padding:0;">
        @if ($mandados->isEmpty())
            <p style="padding:28px; text-align:center; color:var(--color-muted,#888);">
                Nenhum mandado encontrado com os critérios informados.
            </p>
        @else
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>CNJ</th>
                        <th>Nome / Alvo</th>
                        <th>Emissão</th>
                        <th>Validade</th>
                        <th>Procedimento</th>
                        <th>Cumprido por</th>
                        <th>Data de cumprimento</th>
                        @if (auth()->user()->hasPermission('operacional.mandados.manage'))
                            <th style="text-align:right;">Ações</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($mandados as $m)
                        @php
                            $hoje = \Carbon\Carbon::today();
                            $vencido = $m->procedimento === 'Em Aberto'
                                && $m->validade
                                && $m->validade < $hoje->toDateString();
                        @endphp
                        <tr class="{{ $vencido ? 'row--alert' : '' }}">
                            <td>
                                <span class="badge badge--tipo" title="{{ $tiposSigla[$m->tipo_sigla] ?? $m->tipo_sigla }}">
                                    {{ $m->tipo_sigla }}
                                </span>
                            </td>
                            <td>{{ $m->cnj_numero ?: '—' }}</td>
                            <td>
                                {{ $m->nome }}
                                @if ($m->cpf_formatado)
                                    <br><small class="muted">CPF: {{ $m->cpf_formatado }}</small>
                                @endif
                            </td>
                            <td>{{ $m->data_emissao ? \Carbon\Carbon::parse($m->data_emissao)->format('d/m/Y') : '—' }}</td>
                            <td>
                                {{ $m->validade ? \Carbon\Carbon::parse($m->validade)->format('d/m/Y') : '—' }}
                                @if ($vencido)
                                    <span style="color:#c0392b; font-size:.75rem;"> ⚠ VENCIDO</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge--proc badge--proc-{{ \Illuminate\Support\Str::slug($m->procedimento) }}">
                                    {{ $m->procedimento }}
                                </span>
                            </td>
                            <td>{{ $m->cumprido_por ?: '—' }}</td>
                            <td>{{ $m->data_cumprimento ? \Carbon\Carbon::parse($m->data_cumprimento)->format('d/m/Y') : '—' }}</td>
                            @if (auth()->user()->hasPermission('operacional.mandados.manage'))
                                <td style="text-align:right; white-space:nowrap;">
                                    <button type="button"
                                        onclick="abrirEdicao({{ Js::from($m->toArray()) }})"
                                        style="font-size:.8rem; padding:3px 10px;">
                                        Editar
                                    </button>
                                    <button type="button"
                                        onclick="confirmarExclusao('{{ $m->id }}', '{{ e($m->nome) }}')"
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

    {{-- === MODAL CADASTRO / EDICAO ============================================== --}}
    @if (auth()->user()->hasPermission('operacional.mandados.manage'))

        {{-- Cadastro (novo) --}}
        <dialog id="modal-cadastro" class="grom-modal grom-modal--lg">
            <form method="POST" action="{{ route('operacional.mandados.store') }}" class="grom-modal-card">
                @csrf
                <div class="grom-modal-head">
                    <strong>Novo Mandado</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="this.closest('dialog').close()">x</button>
                </div>
                @include('operacional.mandados._form', ['mandado' => null])
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
                    <button type="button" onclick="this.closest('dialog').close()">Cancelar</button>
                    <button type="submit">Salvar</button>
                </div>
            </form>
        </dialog>

        {{-- Edição (preenchida via JS) --}}
        <dialog id="modal-edicao" class="grom-modal grom-modal--lg">
            <form method="POST" id="form-edicao" class="grom-modal-card">
                @csrf
                @method('PUT')
                <div class="grom-modal-head">
                    <strong>Editar Mandado</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="this.closest('dialog').close()">x</button>
                </div>
                @include('operacional.mandados._form', ['mandado' => null, 'edit' => true])
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
                    <button type="button" onclick="this.closest('dialog').close()">Cancelar</button>
                    <button type="submit">Atualizar</button>
                </div>
            </form>
        </dialog>

        {{-- Exclusão --}}
        <dialog id="modal-exclusao" class="grom-modal grom-modal--sm">
            <form method="POST" id="form-exclusao" class="grom-modal-card">
                @csrf
                @method('DELETE')
                <div class="grom-modal-head">
                    <strong>Excluir Mandado</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="this.closest('dialog').close()">x</button>
                </div>
                <p>Tem certeza que deseja excluir o mandado de <strong id="excl-nome"></strong>?</p>
                <div>
                    <label for="excl-motivo" style="display:block; margin-bottom:6px;">Motivo da exclusão <span style="color:#c0392b">*</span></label>
                    <textarea id="excl-motivo" name="motivo" rows="3" required minlength="5" maxlength="500" style="width:100%;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px;">
                    <button type="button" onclick="this.closest('dialog').close()">Cancelar</button>
                    <button type="submit" style="background:var(--color-danger,#c0392b);">Confirmar Exclusão</button>
                </div>
            </form>
        </dialog>

    @endif

@endsection

@section('scripts')
<script>
function abrirEdicao(data) {
    const form = document.getElementById('form-edicao');
    const dialog = document.getElementById('modal-edicao');

    // Define a action de update
    form.action = '/operacional/mandados/' + data.id;

    // Preenche campos pelo name
    const set = (name, val) => {
        const el = form.querySelector('[name="' + name + '"]');
        if (!el) return;
        if (el.tagName === 'SELECT' || el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
            el.value = val ?? '';
        }
    };

    set('tipo_sigla', data.tipo_sigla);
    set('cnj_numero', data.cnj_numero);
    set('vara', data.vara);
    set('nome', data.nome);
    set('cpf', data.cpf);
    set('rg', data.rg);
    set('data_emissao', data.data_emissao);
    set('validade', data.validade);
    set('tipificacao_penal', data.tipificacao_penal);
    set('artigo', data.artigo);
    set('paragrafo', data.paragrafo);
    set('pena_anos', data.pena_anos);
    set('pena_meses', data.pena_meses);
    set('pena_dias', data.pena_dias);
    set('regime', data.regime);
    set('procedimento', data.procedimento);
    set('cumprido_por', data.cumprido_por);
    set('data_cumprimento', data.data_cumprimento);
    set('bo_numero', data.bo_numero);
    set('observacoes', data.observacoes);

    // Carrega tipificações extras
    if (typeof loadTipExtras === 'function') {
        const extrasRaw = data.tipificacoes_extra
            ? (typeof data.tipificacoes_extra === 'string' ? data.tipificacoes_extra : JSON.stringify(data.tipificacoes_extra))
            : '';
        loadTipExtras('edit', extrasRaw);
    }

    dialog.showModal();
}

function confirmarExclusao(id, nome) {
    const form = document.getElementById('form-exclusao');
    form.action = '/operacional/mandados/' + id;
    document.getElementById('excl-nome').textContent = nome;
    document.getElementById('excl-motivo').value = '';
    document.getElementById('modal-exclusao').showModal();
}
</script>
@endsection
