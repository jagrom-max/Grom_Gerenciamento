@extends('layouts.app')

@section('title', request()->routeIs('funcionarios.*') ? 'Funcionarios | Grom.Seg' : 'RH / Admin | Grom.Seg')

@section('content')
    <?php
        $isFuncionariosRoute = request()->routeIs('funcionarios.*');
        $panelRoute = $isFuncionariosRoute ? 'funcionarios.index' : 'rh.index';
        $panelTitle = $isFuncionariosRoute ? 'Funcionários' : 'RH / Admin';
    ?>

    <style>
        .rh-inactive-row td { opacity: 0.36; }
        .rh-inactive-row td strong { font-weight: 400; }
        table td strong { font-size: 0.79rem; font-weight: 600; }
        .rh-filter-bar {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        #rh-table-afas td > strong { font-size: 0.76rem; }
        #rh-table-feriados td > span[style*='white-space'] { font-size: 0.75rem; }
        #rh-historico table td > strong { font-size: 0.75rem; }
    </style>

    <div class="section-head">
        <div>
            <h1>{{ $panelTitle }}</h1>
            <p class="muted" style="margin: 4px 0 0;">Servidores, cargos, afastamentos, feriados e delegados externos.</p>
        </div>
        <div class="actions">
            <a href="{{ route('rh.afastamentos.relatorio') }}" class="btn secondary">Rel. Afastamentos</a>
            <a href="{{ route('rh.confronto') }}" class="btn secondary">Confronto</a>
            <a href="{{ route('rh.composicao') }}" class="btn secondary">Composição</a>
            <a href="{{ route('rh.stats') }}" class="btn secondary">Estatísticas</a>
        </div>
    </div>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Funcionários</small>
            <strong>{{ $summary['funcionarios_total'] }}</strong>
            <span>{{ $summary['funcionarios_ativos'] }} ativos</span>
        </article>
        <article class="card">
            <small>Afastamentos em vigor</small>
            <strong>{{ $summary['afastamentos_em_vigor'] }}</strong>
            <span>{{ $summary['afastamentos_agendados'] }} agendados</span>
        </article>
        <article class="card">
            <small>Cargos ativos</small>
            <strong>{{ $summary['cargos_ativos'] }}</strong>
            <span>{{ $summary['cargos_total'] }} no total</span>
        </article>
        <article class="card">
            <small>Delegados externos</small>
            <strong>{{ $summary['delegados_externos_em_vigor'] }}</strong>
            <span>{{ $summary['delegados_externos_ativos'] }} ativos</span>
        </article>
        <article class="card">
            <small>Feriados próximos</small>
            <strong>{{ $summary['feriados_proximos'] }}</strong>
            <span>{{ $summary['feriados_ativos'] }} no calendário</span>
        </article>
    </div>

    {{-- SECTION: FUNCIONÁRIOS --}}
    <section class="card grom-admin-section" style="margin-bottom:18px;" id="rh-funcionarios">
        <div class="grom-admin-header">
            <div class="grom-admin-title">
                <button type="button" class="grom-toggle-btn" id="rh-toggle-func" onclick="rhToggle('func')" title="Minimizar / Expandir">&#9650;</button>
                <h2 style="margin:0;">Funcionários</h2>
                <span class="grom-mini-note" id="rh-count-func"></span>
            </div>
            @if (auth()->user()->hasPermission('rh.manage'))
                <button type="button" onclick="document.getElementById('new-func-dialog').showModal()">+ Novo servidor</button>
            @endif
        </div>
        <div id="rh-body-func">
            <form method="GET" action="{{ route($panelRoute) }}" class="rh-filter-bar">
                <div class="field" style="margin:0; min-width:180px;">
                    <label for="rh_filter_cargo_id" style="font-size:0.8rem;">Cargo</label>
                    <select id="rh_filter_cargo_id" name="cargo_id" style="font-size:0.80rem;">
                        <option value="">Todos os cargos</option>
                        @foreach ($cargos as $cargo)
                            <option value="{{ $cargo->id }}" @selected(($filters['cargo_id'] ?? null) == $cargo->id)>{{ $cargo->code }} — {{ $cargo->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin:0; min-width:140px;">
                    <label for="rh_filter_status" style="font-size:0.8rem;">Status</label>
                    <select id="rh_filter_status" name="status" style="font-size:0.80rem;">
                        <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Todos</option>
                        <option value="active" @selected(($filters['status'] ?? 'all') === 'active')>Ativos</option>
                        <option value="inactive" @selected(($filters['status'] ?? 'all') === 'inactive')>Inativos</option>
                    </select>
                </div>
                <div style="display:flex; gap:6px;">
                    <button type="submit" style="font-size:0.80rem;">Filtrar</button>
                    <a href="{{ route($panelRoute) }}" class="btn secondary" style="font-size:0.80rem; display:inline-flex; align-items:center;">Limpar</a>
                </div>
                @if (isset($summary['funcionarios_exibidos']) && $summary['funcionarios_exibidos'] !== $summary['funcionarios_total'])
                    <span class="muted" style="font-size:0.8rem; align-self:center;">{{ $summary['funcionarios_exibidos'] }} exibidos de {{ $summary['funcionarios_total'] }}</span>
                @endif
            </form>
            <table class="grom-table-compact" id="rh-table-func">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Cargo / Setor</th>
                        <th>Escala</th>
                        <th>Status</th>
                        @if (auth()->user()->hasPermission('rh.manage'))
                            <th style="width:1%;">Ações</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($funcionarios as $funcionario)
                        @php
                            $currentAfastamento = $funcionario->currentAfastamento();
                        @endphp
                        <tr class="{{ !$funcionario->is_active ? 'rh-inactive-row' : '' }}" data-active="{{ $funcionario->is_active ? '1' : '0' }}">
                            <td>
                                <a href="{{ route('rh.funcionarios.show', $funcionario) }}" style="font-weight:600; text-decoration:none; color:inherit;">{{ $funcionario->name }}</a>
                                @if ($funcionario->short_name && $funcionario->short_name !== $funcionario->name)
                                    <br><span class="muted" style="font-size:0.76rem;">{{ $funcionario->short_name }}</span>
                                @endif
                                @if ($funcionario->email)
                                    <br><span class="muted" style="font-size:0.76rem;">{{ $funcionario->email }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $funcionario->cargo?->name ?? '—' }}<br>
                                <span class="muted" style="font-size:0.76rem;">{{ $funcionario->sector ?: '—' }}</span>
                            </td>
                            <td>
                                <span class="tag {{ $funcionario->concorre_escala ? 'good' : 'warn' }}">
                                    {{ $funcionario->concorre_escala ? 'Concorre' : 'Não' }}
                                </span>
                            </td>
                            <td>
                                @if ($currentAfastamento)
                                    <span class="tag warn">Afastado</span><br>
                                    <span class="muted" style="font-size:0.76rem;">{{ $currentAfastamento->reason }}</span>
                                @else
                                    <span class="tag {{ $funcionario->is_active ? 'good' : 'warn' }}">{{ $funcionario->is_active ? 'Ativo' : 'Inativo' }}</span>
                                @endif
                            </td>
                            @if (auth()->user()->hasPermission('rh.manage'))
                                <td>
                                    <div class="actions" style="gap:4px; flex-wrap:wrap;">
                                        <button type="button" class="secondary" style="font-size:0.78rem; padding:2px 8px;"
                                            onclick="document.getElementById('edit-func-{{ $funcionario->id }}').showModal()">Editar</button>
                                        <form method="POST" action="{{ route('rh.funcionarios.toggle-active', $funcionario) }}" style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="secondary" style="font-size:0.78rem; padding:2px 8px;">{{ $funcionario->is_active ? 'Inativar' : 'Ativar' }}</button>
                                        </form>
                                        @if ($funcionario->user)
                                            <span class="tag good" style="font-size:0.75rem; padding:2px 8px;">
                                                Acesso <a href="{{ route('access.users.index') }}" style="color:inherit; text-decoration:none;" title="Ver em Perfis de Acesso">↗</a>
                                            </span>
                                        @elseif ($funcionario->cpf)
                                            <button type="button" class="secondary" style="font-size:0.78rem; padding:2px 8px;"
                                                onclick="document.getElementById('acesso-func-{{ $funcionario->id }}').showModal()">Criar acesso</button>
                                        @else
                                            <span class="tag warn" style="font-size:0.75rem; padding:2px 8px;" title="CPF não cadastrado">Sem CPF</span>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->hasPermission('rh.manage') ? 5 : 4 }}" class="muted">Nenhum servidor cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- DIALOGS: Funcionários (fora da tabela) --}}
    @if (auth()->user()->hasPermission('rh.manage'))
        <dialog id="new-func-dialog" class="grom-modal grom-modal--lg">
            <div class="grom-modal-card">
                <div class="grom-modal-head">
                    <strong>Novo servidor</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('new-func-dialog').close()">x</button>
                </div>
                <form method="POST" action="{{ route('rh.funcionarios.store') }}" class="grid">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>Matrícula</label>
                            <input name="matricula" type="text" required>
                        </div>
                        <div class="field">
                            <label>Nome</label>
                            <input name="name" type="text" required>
                        </div>
                        <div class="field">
                            <label>Nome simplificado</label>
                            <input name="short_name" type="text">
                        </div>
                        <div class="field">
                            <label>E-mail</label>
                            <input name="email" type="email">
                        </div>
                        <div class="field">
                            <label>Cargo</label>
                            <select name="cargo_id" required>
                                @foreach ($cargos as $cargo)
                                    <option value="{{ $cargo->id }}">{{ $cargo->code }} — {{ $cargo->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Setor</label>
                            <input name="sector" type="text">
                        </div>
                        <div class="field">
                            <label>Telefone</label>
                            <input name="phone" type="text">
                        </div>
                        <div class="field">
                            <label>RG</label>
                            <input name="rg" type="text">
                        </div>
                        <div class="field">
                            <label>CPF</label>
                            <input name="cpf" type="text">
                        </div>
                        <div class="field">
                            <label>Nascimento</label>
                            <input name="birth_date" type="date">
                        </div>
                        <div class="field">
                            <label>Admissão</label>
                            <input name="admission_date" type="date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="field">
                            <label>Designação</label>
                            <input name="designation_date" type="date">
                        </div>
                        <div class="field">
                            <label>Remoção</label>
                            <input name="removal_date" type="date">
                        </div>
                        <div class="field">
                            <label>Desligamento</label>
                            <input name="departure_date" type="date">
                        </div>
                        <div class="field">
                            <label>Concorre à escala</label>
                            <select name="concorre_escala" required>
                                <option value="1">Sim</option>
                                <option value="0" selected>Não</option>
                            </select>
                            <small class="muted">Delegados externos nunca concorrem à escala. Só policiais civis podem concorrer nos cargos escrivão, operacional e fechar.</small>
                        </div>
                        <div class="field">
                            <label>Status</label>
                            <select name="is_active">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Delegado Externo?</label>
                            <select name="is_delegado_externo" required>
                                <option value="1">Sim</option>
                                <option value="0" selected>Não</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Senha SPJ</label>
                            <input name="senha_spj" type="text">
                        </div>
                        <div class="field">
                            <label>Senha IPE</label>
                            <input name="senha_ipe" type="text">
                        </div>
                        <div class="field full">
                            <label>Observações Operacionais</label>
                            <textarea name="observacoes_operacionais" rows="2"></textarea>
                        </div>
                        <div class="field full">
                            <label>Observações</label>
                            <textarea name="notes" rows="2"></textarea>
                        </div>
                        <div class="field full">
                            <label style="display:flex; gap:8px; align-items:center; font-weight:600;">
                                <input type="checkbox" name="create_access" value="1">
                                Ativar acesso ao sistema neste cadastro
                            </label>
                            <small class="muted">Somente super_admin pode definir perfis de acesso.</small>
                        </div>
                        <div class="field full">
                            <label>Perfis de acesso (quando ativado)</label>
                            <select name="access_roles[]" multiple size="4">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                            <small class="muted">Segure Ctrl para selecionar multiplos.</small>
                        </div>
                    </div>
                    <div class="actions" style="margin-top:12px;">
                        <button type="submit">Criar servidor</button>
                        <button type="button" class="secondary" onclick="document.getElementById('new-func-dialog').close()">Cancelar</button>
                    </div>
                </form>
            </div>
        </dialog>

        @foreach ($funcionarios as $funcionario)
            <dialog id="edit-func-{{ $funcionario->id }}" class="grom-modal grom-modal--lg">
                <div class="grom-modal-card">
                    <div class="grom-modal-head">
                        <strong>Editar: {{ $funcionario->name }}</strong>
                        <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('edit-func-{{ $funcionario->id }}').close()">x</button>
                    </div>
                    <form method="POST" action="{{ route('rh.funcionarios.update', $funcionario) }}" class="grid">
                        @csrf
                        @method('PUT')
                        <div class="form-grid">
                            <div class="field">
                                <label>Nome</label>
                                <input name="name" type="text" value="{{ $funcionario->name }}" required>
                            </div>
                            <div class="field">
                                <label>Nome simplificado</label>
                                <input name="short_name" type="text" value="{{ $funcionario->short_name }}">
                            </div>
                            <div class="field">
                                <label>E-mail</label>
                                <input name="email" type="email" value="{{ $funcionario->email }}">
                            </div>
                            <div class="field">
                                <label>Cargo</label>
                                <select name="cargo_id" required>
                                    @foreach ($cargos as $c)
                                        <option value="{{ $c->id }}" @selected($funcionario->cargo_id === $c->id)>{{ $c->code }} — {{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label>Setor</label>
                                <input name="sector" type="text" value="{{ $funcionario->sector }}">
                            </div>
                            <div class="field">
                                <label>Telefone</label>
                                <input name="phone" type="text" value="{{ $funcionario->phone }}">
                            </div>
                            <div class="field">
                                <label>RG</label>
                                <input name="rg" type="text" value="{{ $funcionario->rg }}">
                            </div>
                            <div class="field">
                                <label>CPF</label>
                                <input name="cpf" type="text" value="{{ $funcionario->cpf }}">
                            </div>
                            <div class="field">
                                <label>Nascimento</label>
                                <input name="birth_date" type="date" value="{{ $funcionario->birth_date?->toDateString() }}">
                            </div>
                            <div class="field">
                                <label>Admissão</label>
                                <input name="admission_date" type="date" value="{{ $funcionario->admission_date?->toDateString() }}" required>
                            </div>
                            <div class="field">
                                <label>Designação</label>
                                <input name="designation_date" type="date" value="{{ $funcionario->designation_date?->toDateString() }}">
                            </div>
                            <div class="field">
                                <label>Remoção</label>
                                <input name="removal_date" type="date" value="{{ $funcionario->removal_date?->toDateString() }}">
                            </div>
                            <div class="field">
                                <label>Saída</label>
                                <input name="departure_date" type="date" value="{{ $funcionario->departure_date?->toDateString() }}">
                            </div>
                            <div class="field">
                                <label>Concorre à escala</label>
                                <select name="concorre_escala">
                                    <option value="1" @selected($funcionario->concorre_escala && !$funcionario->is_delegado_externo)>Sim</option>
                                    <option value="0" @selected(!$funcionario->concorre_escala || $funcionario->is_delegado_externo)>Não</option>
                                </select>
                                <small class="muted">Delegados externos nunca concorrem à escala. Só policiais civis podem concorrer nos cargos escrivão, operacional e fechar.</small>
                            </div>
                            <div class="field">
                                <label>Status</label>
                                <select name="is_active">
                                    <option value="1" @selected($funcionario->is_active)>Ativo</option>
                                    <option value="0" @selected(!$funcionario->is_active)>Inativo</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Delegado Externo?</label>
                                <select name="is_delegado_externo" required>
                                    <option value="1" @selected($funcionario->is_delegado_externo)>Sim</option>
                                    <option value="0" @selected(!$funcionario->is_delegado_externo)>Não</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Senha SPJ</label>
                                <input name="senha_spj" type="text" value="{{ $funcionario->senha_spj }}">
                            </div>
                            <div class="field">
                                <label>Senha IPE</label>
                                <input name="senha_ipe" type="text" value="{{ $funcionario->senha_ipe }}">
                            </div>
                            <div class="field full">
                                <label>Observações Operacionais</label>
                                <textarea name="observacoes_operacionais" rows="2">{{ $funcionario->observacoes_operacionais }}</textarea>
                            </div>
                            <div class="field full">
                                <label>Observações</label>
                                <textarea name="notes" rows="2">{{ $funcionario->notes }}</textarea>
                            </div>
                            <div class="field full">
                                <label style="display:flex; gap:8px; align-items:center; font-weight:600;">
                                    <input type="checkbox" name="create_access" value="1" @checked($funcionario->user !== null)>
                                    Ativar acesso ao sistema
                                </label>
                                <small class="muted">Desmarcar inativa o acesso existente; não remove histórico.</small>
                            </div>
                            <div class="field full">
                                <label>Perfis de acesso (quando ativado)</label>
                                <select name="access_roles[]" multiple size="4">
                                    @php $selectedRoles = $funcionario->user?->roles?->pluck('id')->all() ?? []; @endphp
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected(in_array($role->id, $selectedRoles, true))>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <small class="muted">Somente super_admin pode alterar perfis de acesso.</small>
                            </div>
                        </div>
                        <div class="actions" style="margin-top:12px; justify-content:space-between;">
                            <div style="display:flex; gap:6px;">
                                <button type="submit">Salvar alterações</button>
                                <button type="button" class="secondary" onclick="document.getElementById('edit-func-{{ $funcionario->id }}').close()">Cancelar</button>
                            </div>
                            <form method="POST" action="{{ route('rh.funcionarios.destroy', $funcionario) }}" style="display:inline;"
                                onsubmit="return confirm('Arquivar {{ addslashes($funcionario->name) }}? O cadastro sera inativado e o historico sera preservado.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="color:#92400e; border-color:#f59e0b; font-size:0.82rem; padding:3px 10px;">Arquivar servidor</button>
                            </form>
                        </div>
                    </form>
                </div>
            </dialog>
        @endforeach

        {{-- Dialogs: Criar acesso para funcionários sem usuário --}}
        @foreach ($funcionarios as $funcionario)
            @if (!$funcionario->user && $funcionario->cpf)
                <dialog id="acesso-func-{{ $funcionario->id }}" class="grom-modal grom-modal--md">
                    <div class="grom-modal-card">
                        <div class="grom-modal-head">
                            <div>
                                <strong>Criar acesso — {{ $funcionario->name }}</strong><br>
                                <span class="grom-mini-note">CPF: {{ preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $funcionario->cpf) }}</span>
                            </div>
                            <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('acesso-func-{{ $funcionario->id }}').close()">✕</button>
                        </div>
                        <p class="muted" style="font-size:0.85rem; margin:0 0 14px;">
                            O login será feito pelo CPF ({{ $funcionario->cpf }}). O e-mail cadastrado no RH será associado automaticamente.
                        </p>
                        <form method="POST" action="{{ route('access.users.from-funcionario', $funcionario) }}" class="grid">
                            @csrf
                            <div class="form-grid">
                                <div class="field full">
                                    <span class="muted" style="font-size:0.82rem;">
                                        A senha inicial sera gerada automaticamente no padrao <strong>DDM + CPF</strong>.
                                    </span>
                                </div>
                                <div class="field full">
                                    <label>Perfis de acesso</label>
                                    <select name="roles[]" multiple size="4">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="muted">Segure Ctrl para selecionar múltiplos</small>
                                </div>
                            </div>
                            <div class="actions" style="margin-top:12px;">
                                <button type="submit">Criar acesso</button>
                                <button type="button" class="secondary" onclick="document.getElementById('acesso-func-{{ $funcionario->id }}').close()">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </dialog>
            @endif
        @endforeach
    @endif

    {{-- SECTION: AFASTAMENTOS --}}
    <section class="card grom-admin-section" style="margin-bottom:18px;" id="rh-afastamentos">
        <div class="grom-admin-header">
            <div class="grom-admin-title">
                <button type="button" class="grom-toggle-btn" id="rh-toggle-afas" onclick="rhToggle('afas')" title="Minimizar / Expandir">&#9650;</button>
                <h2 style="margin:0;">Afastamentos</h2>
            </div>
            @if (auth()->user()->hasPermission('rh.manage'))
                <button type="button" onclick="document.getElementById('new-afas-dialog').showModal()">+ Novo afastamento</button>
            @endif
        </div>
        <div id="rh-body-afas">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-bottom:12px;">
                <div class="card" style="padding:12px 14px;">
                    <small class="muted" style="display:block; margin-bottom:4px;">Férias acumuladas</small>
                    <strong style="font-size:1.05rem; display:block;">{{ $afastamentosSummary['ferias_dias'] }} dias contabilizados</strong>
                    <span class="muted" style="font-size:0.78rem;">{{ $afastamentosSummary['ferias_registros'] }} registro(s)</span>
                </div>
                <div class="card" style="padding:12px 14px;">
                    <small class="muted" style="display:block; margin-bottom:4px;">Demais afastamentos</small>
                    <strong style="font-size:1.05rem; display:block;">{{ $afastamentosSummary['outros_dias'] }} dias contabilizados</strong>
                    <span class="muted" style="font-size:0.78rem;">{{ $afastamentosSummary['outros_registros'] }} registro(s)</span>
                </div>
                <div class="card" style="padding:12px 14px;">
                    <small class="muted" style="display:block; margin-bottom:4px;">Períodos em aberto</small>
                    <strong style="font-size:1.05rem; display:block;">{{ $afastamentosSummary['registros_em_aberto'] }} registro(s)</strong>
                    <span class="muted" style="font-size:0.78rem;">Dias só entram no total quando há início e fim.</span>
                </div>
            </div>
            <div class="rh-filter-bar" style="margin-bottom:12px; align-items:center;">
                <div class="field" style="margin:0; min-width:130px;">
                    <label style="font-size:0.8rem;">Mês</label>
                    <select id="afas-mes" onchange="filterAfastamentos()" style="font-size:0.80rem;">
                        <option value="">Todos</option>
                        <option value="1">Janeiro</option>
                        <option value="2">Fevereiro</option>
                        <option value="3">Março</option>
                        <option value="4">Abril</option>
                        <option value="5">Maio</option>
                        <option value="6">Junho</option>
                        <option value="7">Julho</option>
                        <option value="8">Agosto</option>
                        <option value="9">Setembro</option>
                        <option value="10">Outubro</option>
                        <option value="11">Novembro</option>
                        <option value="12">Dezembro</option>
                    </select>
                </div>
                <div class="field" style="margin:0; min-width:100px;">
                    <label style="font-size:0.8rem;">Ano</label>
                    <select id="afas-ano" onchange="filterAfastamentos()" style="font-size:0.80rem;">
                        <option value="">Todos</option>
                    </select>
                </div>
                <button type="button" class="secondary" style="font-size:0.82rem; padding:3px 10px; align-self:flex-end;" onclick="rhClearAfasFilter()">Limpar</button>
                <span class="muted" id="afas-count-label" style="font-size:0.8rem; align-self:center;"></span>
            </div>
            <table class="grom-table-compact" id="rh-table-afas">
                <thead>
                    <tr>
                        <th>Servidor</th>
                        <th>Motivo</th>
                        <th>Início</th>
                        <th>Fim</th>
                        <th>Dias</th>
                        <th>Status</th>
                        @if (auth()->user()->hasPermission('rh.manage'))
                            <th style="width:1%;">Ações</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($afastamentos as $afastamento)
                        <tr data-afas-year="{{ $afastamento->start_date?->year }}" data-afas-month="{{ $afastamento->start_date?->month }}">
                            <td>
                                <strong>{{ $afastamento->funcionario?->short_name ?: $afastamento->funcionario?->name }}</strong><br>
                                <span class="muted" style="font-size:0.76rem;">{{ $afastamento->funcionario?->name }}</span>
                            </td>
                            <td>{{ $afastamento->reason }}</td>
                            <td style="white-space:nowrap;">{{ $afastamento->start_date?->format('d/m/Y') }}</td>
                            <td style="white-space:nowrap;">{{ $afastamento->end_date?->format('d/m/Y') ?: '—' }}</td>
                            <td style="white-space:nowrap;">{{ $afastamento->durationInDays() !== null ? $afastamento->durationInDays() . ' dias' : 'Em aberto' }}</td>
                            <td><span class="tag {{ $afastamento->statusTone() }}">{{ $afastamento->statusLabel() }}</span></td>
                            @if (auth()->user()->hasPermission('rh.manage'))
                                <td>
                                    <div class="actions" style="gap:4px; flex-wrap:nowrap;">
                                        <button type="button" class="secondary" style="font-size:0.78rem; padding:2px 8px;"
                                            onclick="document.getElementById('edit-afas-{{ $afastamento->id }}').showModal()">Editar</button>
                                        <form method="POST" action="{{ route('rh.afastamentos.toggle-active', $afastamento) }}" style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="secondary" style="font-size:0.78rem; padding:2px 8px;">{{ $afastamento->is_active ? 'Inativar' : 'Ativar' }}</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->hasPermission('rh.manage') ? 7 : 6 }}" class="muted">Nenhum afastamento registrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- DIALOGS: Afastamentos (fora da tabela) --}}
    @if (auth()->user()->hasPermission('rh.manage'))
        <dialog id="new-afas-dialog" class="grom-modal grom-modal--md">
            <div class="grom-modal-card">
                <div class="grom-modal-head">
                    <strong>Novo afastamento</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('new-afas-dialog').close()">x</button>
                </div>
                <form method="POST" action="{{ route('rh.afastamentos.store') }}" class="grid js-afastamento-form">
                    @csrf
                    <div class="form-grid">
                        <div class="field full">
                            <label>Servidor</label>
                            <select name="funcionario_id" required>
                                @foreach ($funcionarios as $f)
                                    <option value="{{ $f->id }}">{{ $f->matricula }} — {{ $f->short_name ?: $f->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field full">
                            <label>Motivo</label>
                            <input name="reason" type="text" required>
                        </div>
                        <div class="field">
                            <label>Início</label>
                            <input name="start_date" type="date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="field">
                            <label>Fim</label>
                            <input name="end_date" type="date">
                        </div>
                        <div class="field full" style="margin-top:-4px;">
                            <span class="muted js-afastamento-counter" style="font-size:0.78rem;">Selecione início e fim para contar os dias.</span>
                        </div>
                        <div class="field full">
                            <label>Observações</label>
                            <textarea name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="actions" style="margin-top:12px;">
                        <button type="submit">Registrar</button>
                        <button type="button" class="secondary" onclick="document.getElementById('new-afas-dialog').close()">Cancelar</button>
                    </div>
                </form>
            </div>
        </dialog>

        @foreach ($afastamentos as $afastamento)
            <dialog id="edit-afas-{{ $afastamento->id }}" class="grom-modal grom-modal--md">
                <div class="grom-modal-card">
                    <div class="grom-modal-head">
                        <strong>Editar — {{ $afastamento->funcionario?->short_name ?: $afastamento->funcionario?->name }}</strong>
                        <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('edit-afas-{{ $afastamento->id }}').close()">x</button>
                    </div>
                    <form method="POST" action="{{ route('rh.afastamentos.update', $afastamento) }}" class="grid js-afastamento-form">
                        @csrf
                        @method('PUT')
                        <div class="form-grid">
                            <div class="field full">
                                <label>Motivo</label>
                                <input name="reason" type="text" value="{{ $afastamento->reason }}" required>
                            </div>
                            <div class="field">
                                <label>Início</label>
                                <input name="start_date" type="date" value="{{ $afastamento->start_date?->toDateString() }}" required>
                            </div>
                            <div class="field">
                                <label>Fim</label>
                                <input name="end_date" type="date" value="{{ $afastamento->end_date?->toDateString() }}">
                            </div>
                            <div class="field full" style="margin-top:-4px;">
                                <span class="muted js-afastamento-counter" style="font-size:0.78rem;">Selecione início e fim para contar os dias.</span>
                            </div>
                            <div class="field full">
                                <label>Observações</label>
                                <textarea name="notes" rows="2">{{ $afastamento->notes }}</textarea>
                            </div>
                        </div>
                        <div class="actions" style="margin-top:12px;">
                            <button type="submit">Salvar</button>
                            <button type="button" class="secondary" onclick="document.getElementById('edit-afas-{{ $afastamento->id }}').close()">Cancelar</button>
                        </div>
                    </form>
                </div>
            </dialog>
        @endforeach
    @endif

    {{-- SECTION: CARGOS --}}
    <section class="card grom-admin-section" style="margin-bottom:18px;" id="rh-cargos">
        <div class="grom-admin-header">
            <div class="grom-admin-title">
                <button type="button" class="grom-toggle-btn" id="rh-toggle-cargos" onclick="rhToggle('cargos')" title="Minimizar / Expandir">&#9650;</button>
                <h2 style="margin:0;">Cargos</h2>
            </div>
            @if (auth()->user()->hasPermission('rh.manage'))
                <button type="button" onclick="document.getElementById('new-cargo-dialog').showModal()">+ Novo cargo</button>
            @endif
        </div>
        <div id="rh-body-cargos">
            <table class="grom-table-compact">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Servidores</th>
                        <th>Status</th>
                        @if (auth()->user()->hasPermission('rh.manage'))
                            <th style="width:1%;">Ações</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cargos as $cargo)
                        <tr>
                            <td>{{ $cargo->name }} <span class="muted" style="font-size:0.72rem;">({{ $cargo->code }})</span></td>
                            <td>{{ $cargo->funcionarios_count }}</td>
                            <td><span class="tag {{ $cargo->is_active ? 'good' : 'warn' }}">{{ $cargo->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                            @if (auth()->user()->hasPermission('rh.manage'))
                                <td>
                                    <form method="POST" action="{{ route('rh.cargos.toggle-active', $cargo) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="secondary" style="font-size:0.78rem; padding:2px 8px;">{{ $cargo->is_active ? 'Inativar' : 'Ativar' }}</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->hasPermission('rh.manage') ? 4 : 3 }}" class="muted">Nenhum cargo cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- DIALOG: Novo cargo --}}
    @if (auth()->user()->hasPermission('rh.manage'))
        <dialog id="new-cargo-dialog" class="grom-modal grom-modal--sm">
            <div class="grom-modal-card">
                <div class="grom-modal-head">
                    <strong>Novo cargo</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('new-cargo-dialog').close()">x</button>
                </div>
                <form method="POST" action="{{ route('rh.cargos.store') }}" class="grid">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>Código</label>
                            <input name="code" type="text" required>
                        </div>
                        <div class="field">
                            <label>Nome</label>
                            <input name="name" type="text" required>
                        </div>
                        <div class="field full">
                            <label>Descrição</label>
                            <input name="description" type="text">
                        </div>
                        <div class="field">
                            <label>Status</label>
                            <select name="is_active">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="actions" style="margin-top:12px;">
                        <button type="submit">Criar cargo</button>
                        <button type="button" class="secondary" onclick="document.getElementById('new-cargo-dialog').close()">Cancelar</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endif

    {{-- SECTION: DELEGADOS EXTERNOS --}}
    <section class="card grom-admin-section" style="margin-bottom:18px;" id="rh-delegados-externos">
        <div class="grom-admin-header">
            <div class="grom-admin-title">
                <button type="button" class="grom-toggle-btn" id="rh-toggle-delegados" onclick="rhToggle('delegados')" title="Minimizar / Expandir">&#9650;</button>
                <h2 style="margin:0;">Delegados Externos</h2>
            </div>
            @if (auth()->user()->hasPermission('rh.manage'))
                <button type="button" onclick="document.getElementById('new-delegado-dialog').showModal()">+ Novo delegado externo</button>
            @endif
        </div>
        <div id="rh-body-delegados">
            <table class="grom-table-compact">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Origem</th>
                        <th>Função</th>
                        <th>Vigência</th>
                        <th>Status</th>
                        @if (auth()->user()->hasPermission('rh.manage'))
                            <th style="width:1%;">Ações</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($delegadosExternos as $delegadoExterno)
                        <tr>
                            <td>
                                {{ $delegadoExterno->name }}<br>
                                <span class="muted" style="font-size:0.76rem;">{{ $delegadoExterno->email ?: '—' }}</span>
                            </td>
                            <td>{{ $delegadoExterno->origin_unit }}</td>
                            <td>{{ $delegadoExterno->role_title }}</td>
                            <td style="white-space:nowrap;">
                                {{ $delegadoExterno->start_date?->format('d/m/Y') }}
                                @if ($delegadoExterno->end_date)
                                    <br><span class="muted" style="font-size:0.76rem;">até {{ $delegadoExterno->end_date->format('d/m/Y') }}</span>
                                @endif
                            </td>
                            <td><span class="tag {{ $delegadoExterno->statusTone() }}">{{ $delegadoExterno->statusLabel() }}</span></td>
                            @if (auth()->user()->hasPermission('rh.manage'))
                                <td>
                                    <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                        <a href="{{ route('rh.delegados-externos.show', $delegadoExterno) }}"
                                           class="btn secondary" style="font-size:0.78rem; padding:2px 10px;">Ver</a>
                                        <form method="POST" action="{{ route('rh.delegados-externos.toggle-active', $delegadoExterno) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="secondary" style="font-size:0.78rem; padding:2px 8px;">{{ $delegadoExterno->is_active ? 'Inativar' : 'Ativar' }}</button>
                                        </form>
                                    </div>
                                </td>
                            @else
                                <td>
                                    <a href="{{ route('rh.delegados-externos.show', $delegadoExterno) }}"
                                       class="btn secondary" style="font-size:0.78rem; padding:2px 10px;">Ver</a>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">Nenhum delegado externo cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- DIALOG: Novo delegado externo --}}
    @if (auth()->user()->hasPermission('rh.manage'))
        <dialog id="new-delegado-dialog" class="grom-modal grom-modal--md">
            <div class="grom-modal-card">
                <div class="grom-modal-head">
                    <strong>Novo delegado externo</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('new-delegado-dialog').close()">x</button>
                </div>
                <form method="POST" action="{{ route('rh.delegados-externos.store') }}" class="grid">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>Código</label>
                            <input name="registration_code" type="text">
                        </div>
                        <div class="field">
                            <label>Nome</label>
                            <input name="name" type="text" required>
                        </div>
                        <div class="field">
                            <label>Unidade de origem</label>
                            <input name="origin_unit" type="text" required>
                        </div>
                        <div class="field">
                            <label>Função</label>
                            <input name="role_title" type="text" required>
                        </div>
                        <div class="field">
                            <label>Contato</label>
                            <input name="contact" type="text">
                        </div>
                        <div class="field">
                            <label>E-mail</label>
                            <input name="email" type="email">
                        </div>
                        <div class="field">
                            <label>Início</label>
                            <input name="start_date" type="date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="field">
                            <label>Fim</label>
                            <input name="end_date" type="date">
                        </div>
                        <div class="field full">
                            <label>Observações</label>
                            <input name="notes" type="text">
                        </div>
                        <div class="field">
                            <label>Status</label>
                            <select name="is_active">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="actions" style="margin-top:12px;">
                        <button type="submit">Cadastrar</button>
                        <button type="button" class="secondary" onclick="document.getElementById('new-delegado-dialog').close()">Cancelar</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endif

    {{-- FERIADOS + HISTORICO --}}
    <div class="grid" style="grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px;">
        <section class="card" id="rh-calendario">
            <div class="grom-admin-header">
                <div class="grom-admin-title">
                    <button type="button" class="grom-toggle-btn" id="rh-toggle-feriados" onclick="rhToggle('feriados')" title="Minimizar / Expandir">&#9650;</button>
                    <h2 style="margin:0;">Feriados</h2>
                </div>
                @if (auth()->user()->hasPermission('rh.manage'))
                    <button type="button" style="font-size:0.80rem;" onclick="document.getElementById('new-feriado-dialog').showModal()">+ Novo feriado</button>
                @endif
            </div>
            <div id="rh-body-feriados">
                <div class="rh-filter-bar" style="margin-bottom:10px;">
                    <div class="field" style="margin:0; min-width:110px;">
                        <label style="font-size:0.8rem;">Ano</label>
                        <select id="feriados-ano" onchange="filterFeriados()" style="font-size:0.80rem;">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <button type="button" class="secondary" style="font-size:0.82rem; padding:3px 10px; align-self:flex-end;" onclick="rhClearFeriadosFilter()">Limpar</button>
                </div>
                <table class="grom-table-compact" id="rh-table-feriados">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Nome</th>
                            <th>Escopo</th>
                            <th>Status</th>
                            @if (auth()->user()->hasPermission('rh.manage'))
                                <th style="width:1%;"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($holidays as $holiday)
                            <tr data-feriado-year="{{ $holiday->holiday_date?->year }}">
                                <td>
                                    <span style="white-space:nowrap; font-size:0.76rem;">{{ $holiday->holiday_date?->format('d/m/Y') }}</span><br>
                                    <span class="muted" style="font-size:0.72rem;">{{ $holiday->holiday_date?->diffForHumans(now(), true) }}</span>
                                </td>
                                <td>{{ $holiday->name }}</td>
                                <td><span class="tag good" style="font-size:0.78rem;">{{ $holiday->scope }}</span></td>
                                <td><span class="tag {{ $holiday->is_active ? 'good' : 'warn' }}" style="font-size:0.78rem;">{{ $holiday->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                                @if (auth()->user()->hasPermission('rh.manage'))
                                    <td>
                                        <form method="POST" action="{{ route('rh.holidays.toggle-active', $holiday) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="secondary" style="font-size:0.78rem; padding:2px 8px;">{{ $holiday->is_active ? 'Inativar' : 'Ativar' }}</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->hasPermission('rh.manage') ? 5 : 4 }}" class="muted">Nenhum feriado cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card" id="rh-historico">
            <div class="grom-admin-header" style="margin-bottom:12px;">
                <div class="grom-admin-title">
                    <button type="button" class="grom-toggle-btn" id="rh-toggle-hist" onclick="rhToggle('hist')" title="Minimizar / Expandir">&#9650;</button>
                    <h2 style="margin:0;">Histórico recente</h2>
                </div>
            </div>
            <div id="rh-body-hist">
                <table class="grom-table-compact">
                    <thead>
                        <tr>
                            <th>Quando</th>
                            <th>Evento</th>
                            <th>Usuário</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentHistory as $event)
                            <tr>
                                <td style="white-space:nowrap;">{{ $event->created_at?->format('d/m/Y H:i') }}</td>
                                <td>
                                    <strong>{{ $event->event_type }}</strong><br>
                                    <span class="muted" style="font-size:0.76rem;">{{ $event->description }}</span>
                                </td>
                                <td>{{ $event->actor?->username ?? 'Sistema' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="muted">Nenhum evento registrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- DIALOG: Novo feriado --}}
    @if (auth()->user()->hasPermission('rh.manage'))
        <dialog id="new-feriado-dialog" class="grom-modal grom-modal--sm">
            <div class="grom-modal-card">
                <div class="grom-modal-head">
                    <strong>Novo feriado</strong>
                    <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('new-feriado-dialog').close()">x</button>
                </div>
                <form method="POST" action="{{ route('rh.holidays.store') }}" class="grid">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label>Data</label>
                            <input name="holiday_date" type="date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="field">
                            <label>Nome</label>
                            <input name="name" type="text" required>
                        </div>
                        <div class="field">
                            <label>Escopo</label>
                            <select name="scope">
                                <option value="nacional">Nacional</option>
                                <option value="estadual">Estadual</option>
                                <option value="municipal">Municipal</option>
                                <option value="interno">Interno</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Status</label>
                            <select name="is_active">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                        <div class="field full">
                            <label>Observações</label>
                            <input name="notes" type="text">
                        </div>
                    </div>
                    <div class="actions" style="margin-top:12px;">
                        <button type="submit">Cadastrar</button>
                        <button type="button" class="secondary" onclick="document.getElementById('new-feriado-dialog').close()">Cancelar</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endif

    <script>
    // ── Seções minimizáveis ──────────────────────────────────────────────────
    const RH_SECTIONS = ['func', 'afas', 'cargos', 'delegados', 'feriados', 'hist'];

    function rhToggle(id) {
        var body = document.getElementById('rh-body-' + id);
        var btn  = document.getElementById('rh-toggle-' + id);
        if (!body || !btn) return;
        var collapsed = body.style.display === 'none';
        body.style.display = collapsed ? '' : 'none';
        btn.innerHTML = collapsed ? '&#9650;' : '&#9660;';
        try { localStorage.setItem('rh_section_' + id, collapsed ? '1' : '0'); } catch(e){}
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Restaurar estado das seções
        RH_SECTIONS.forEach(function (id) {
            try {
                var state = localStorage.getItem('rh_section_' + id);
                if (state === '0') {
                    var body = document.getElementById('rh-body-' + id);
                    var btn  = document.getElementById('rh-toggle-' + id);
                    if (body) { body.style.display = 'none'; }
                    if (btn)  { btn.innerHTML = '&#9660;'; }
                }
            } catch(e) {}
        });

        // Popular anos dos afastamentos
        buildAfasAnoSelect();
        // Popular anos dos feriados
        buildFeriadosAnoSelect();
        // Aplicar filtro padrão feriados = ano atual
        var anoAtual = String(new Date().getFullYear());
        var selFeriado = document.getElementById('feriados-ano');
        if (selFeriado) {
            for (var i = 0; i < selFeriado.options.length; i++) {
                if (selFeriado.options[i].value === anoAtual) {
                    selFeriado.value = anoAtual;
                    break;
                }
            }
            filterFeriados();
        }

        bindAfastamentoCounters(document);
    });

    function parseIsoDate(value) {
        if (!value) return null;
        var parts = value.split('-');
        if (parts.length !== 3) return null;
        var year = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);
        var day = parseInt(parts[2], 10);
        if (!year || !month || !day) return null;
        return new Date(year, month - 1, day);
    }

    function diffInclusiveDays(startValue, endValue) {
        var start = parseIsoDate(startValue);
        var end = parseIsoDate(endValue);
        if (!start || !end || end < start) return null;
        var millisPerDay = 24 * 60 * 60 * 1000;
        return Math.round((end - start) / millisPerDay) + 1;
    }

    function afastamentoCategoryLabel(reasonValue) {
        var normalized = (reasonValue || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();

        return normalized.indexOf('ferias') >= 0 ? 'Férias' : 'Demais afastamentos';
    }

    function updateAfastamentoCounter(form) {
        var startInput = form.querySelector('input[name="start_date"]');
        var endInput = form.querySelector('input[name="end_date"]');
        var reasonInput = form.querySelector('input[name="reason"]');
        var counter = form.querySelector('.js-afastamento-counter');

        if (!startInput || !endInput || !counter) return;

        if (!startInput.value || !endInput.value) {
            counter.textContent = endInput.value
                ? 'Informe a data inicial para calcular os dias.'
                : 'Selecione início e fim para contar os dias.';
            return;
        }

        var totalDays = diffInclusiveDays(startInput.value, endInput.value);
        if (totalDays === null) {
            counter.textContent = 'Período inválido para contagem.';
            return;
        }

        counter.textContent = afastamentoCategoryLabel(reasonInput ? reasonInput.value : '') + ': ' + totalDays + ' dia(s) corridos.';
    }

    function bindAfastamentoCounters(root) {
        var forms = root.querySelectorAll('.js-afastamento-form');
        forms.forEach(function (form) {
            ['start_date', 'end_date', 'reason'].forEach(function (name) {
                var input = form.querySelector('[name="' + name + '"]');
                if (!input || input.dataset.afastamentoCounterBound === '1') {
                    return;
                }

                input.addEventListener('input', function () {
                    updateAfastamentoCounter(form);
                });
                input.dataset.afastamentoCounterBound = '1';
            });

            updateAfastamentoCounter(form);
        });
    }

    // ── Afastamentos: filtro por mês/ano ─────────────────────────────────────
    function buildAfasAnoSelect() {
        var rows = document.querySelectorAll('#rh-table-afas tbody tr[data-afas-year]');
        var anos = {};
        rows.forEach(function (tr) {
            var y = tr.getAttribute('data-afas-year');
            if (y) anos[y] = true;
        });
        var sel = document.getElementById('afas-ano');
        if (!sel) return;
        Object.keys(anos).sort().reverse().forEach(function (y) {
            var opt = document.createElement('option');
            opt.value = y;
            opt.textContent = y;
            sel.appendChild(opt);
        });
    }

    function filterAfastamentos() {
        var mes  = document.getElementById('afas-mes') ? document.getElementById('afas-mes').value : '';
        var ano  = document.getElementById('afas-ano') ? document.getElementById('afas-ano').value : '';
        var rows = document.querySelectorAll('#rh-table-afas tbody tr[data-afas-year]');
        var vis  = 0;
        rows.forEach(function (tr) {
            var trAno = tr.getAttribute('data-afas-year') || '';
            var trMes = tr.getAttribute('data-afas-month') || '';
            var ok = (!ano || trAno === ano) && (!mes || String(parseInt(trMes, 10)) === mes);
            tr.style.display = ok ? '' : 'none';
            if (ok) vis++;
        });
        var lbl = document.getElementById('afas-count-label');
        if (lbl) { lbl.textContent = (mes || ano) ? vis + ' registro(s) exibido(s)' : ''; }
    }

    function rhClearAfasFilter() {
        var m = document.getElementById('afas-mes');
        var a = document.getElementById('afas-ano');
        if (m) m.value = '';
        if (a) a.value = '';
        filterAfastamentos();
    }

    // ── Feriados: filtro por ano ──────────────────────────────────────────────
    function buildFeriadosAnoSelect() {
        var rows = document.querySelectorAll('#rh-table-feriados tbody tr[data-feriado-year]');
        var anos = {};
        rows.forEach(function (tr) {
            var y = tr.getAttribute('data-feriado-year');
            if (y) anos[y] = true;
        });
        var sel = document.getElementById('feriados-ano');
        if (!sel) return;
        Object.keys(anos).sort().forEach(function (y) {
            var opt = document.createElement('option');
            opt.value = y;
            opt.textContent = y;
            sel.appendChild(opt);
        });
    }

    function filterFeriados() {
        var ano  = document.getElementById('feriados-ano') ? document.getElementById('feriados-ano').value : '';
        var rows = document.querySelectorAll('#rh-table-feriados tbody tr[data-feriado-year]');
        rows.forEach(function (tr) {
            var trAno = tr.getAttribute('data-feriado-year') || '';
            tr.style.display = (!ano || trAno === ano) ? '' : 'none';
        });
    }

    function rhClearFeriadosFilter() {
        var s = document.getElementById('feriados-ano');
        if (s) s.value = '';
        filterFeriados();
    }
    </script>

@endsection
