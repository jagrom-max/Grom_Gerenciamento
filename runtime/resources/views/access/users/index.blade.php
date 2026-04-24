@extends('layouts.app')

@section('title', 'Usuários | Grom.Seg')

@section('content')
    @php
        $isSuperAdmin = auth()->user()->isSuperAdmin();
        $canCreate = auth()->user()->hasPermission('access.users.create') && $isSuperAdmin;
        $canUpdate = auth()->user()->hasPermission('access.users.update') && $isSuperAdmin;
        $canToggle = auth()->user()->hasPermission('access.users.toggle') && $isSuperAdmin;
        $pendenteTroca = $users->where('must_change_password', true)->count();
        $totalAtivos   = $users->where('is_active', true)->count();
        $activeTab     = request('tab', 'servidores');
    @endphp

    <style>
        .tab-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            padding: 6px;
            border: 1px solid rgba(15, 39, 68, 0.08);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.82);
            box-shadow: 0 10px 24px rgba(15, 39, 68, 0.05);
        }
        .tab-nav a {
            padding: 10px 16px;
            font-size: 0.86rem;
            font-weight: 700;
            color: var(--grom-ink-soft);
            text-decoration: none;
            border: 1px solid transparent;
            border-radius: 12px;
            transition: color .15s, background .15s, border-color .15s, box-shadow .15s;
        }
        .tab-nav a:hover {
            color: var(--grom-ink);
            background: #f5f8fb;
            border-color: rgba(15, 39, 68, 0.08);
        }
        .tab-nav a.active {
            color: #fff;
            background: linear-gradient(180deg, var(--grom-primary) 0%, var(--grom-primary-2) 100%);
            box-shadow: 0 12px 22px rgba(15, 39, 68, 0.16);
        }
        .tab-pane { display:none; }
        .tab-pane.active { display:block; }
        .count-alert { color: #c0392b; }
    </style>

    <div class="section-head">
        <div>
            <h1>Usuários e Atribuições</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Servidores e visitantes com acesso ao sistema. Login via CPF.
            </p>
        </div>
        <div class="actions">
            @if (auth()->user()->hasPermission('access.roles.view'))
                <a class="btn secondary" href="{{ route('access.roles.index') }}">Perfis de Acesso</a>
            @endif
        </div>
    </div>

    @unless ($isSuperAdmin)
        <div class="card" style="margin-bottom: 16px; border-color:#f5c06d;">
            <p class="muted" style="margin:0; font-size:0.85rem;">
                Governança ativa: somente o super_admin pode criar, alterar status, redefinir senha e ajustar perfis/escopos de acesso.
            </p>
        </div>
    @endunless

    {{-- Cards de resumo --}}
    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Total de usuários</small>
            <strong>{{ $users->count() }}</strong>
            <span>{{ $totalAtivos }} ativos no sistema.</span>
        </article>
        <article class="card">
            <small>Servidores</small>
            <strong>{{ $servidores->count() }}</strong>
            <span>vinculados ao RH</span>
        </article>
        <article class="card">
            <small>Visitantes</small>
            <strong>{{ $visitantes->count() }}</strong>
            <span>acesso externo</span>
        </article>
        <article class="card">
            <small>Troca de senha pendente</small>
            <strong class="{{ $pendenteTroca > 0 ? 'count-alert' : '' }}">{{ $pendenteTroca }}</strong>
            <span>Usuários com senha temporária.</span>
        </article>
        <article class="card">
            <small>Perfis cadastrados</small>
            <strong>{{ $roles->count() }}</strong>
            <span>RBAC configurado no sistema.</span>
        </article>
    </div>

    {{-- ── Seção novo visitante ─────────────────────────────────────────── --}}
    @if ($canCreate)
        <section class="card" style="margin-bottom: 18px;" id="novo-visitante">
            <h2 style="margin-top: 0;">Novo visitante</h2>
            <p class="muted" style="font-size:0.83rem; margin:-4px 0 12px;">Usuários externos não vinculados a servidor RH. Login: CPF.</p>
            <form method="POST" action="{{ route('access.users.store') }}" class="grid">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label for="v-name">Nome completo</label>
                        <input id="v-name" name="name" type="text" required placeholder="Ex: João da Silva">
                    </div>
                    <div class="field">
                        <label for="v-cpf">CPF <span style="color:#c0392b">*</span></label>
                        <input id="v-cpf" name="cpf" type="text" inputmode="numeric" pattern="\d{11}" maxlength="14"
                               required placeholder="Somente dígitos (11 caracteres)"
                               oninput="this.value=this.value.replace(/\D/g,'')">
                        <span class="muted" style="font-size:0.78rem;">Será usado como login.</span>
                    </div>
                    <div class="field">
                        <label for="v-email">E-mail (opcional)</label>
                        <input id="v-email" name="email" type="email" placeholder="contato@exemplo.com">
                    </div>
                    <div class="field">
                        <label for="v-rg">RG (opcional)</label>
                        <input id="v-rg" name="rg" type="text" maxlength="50" placeholder="Documento de identidade">
                    </div>
                    <div class="field">
                        <label for="v-phone">Telefone (opcional)</label>
                        <input id="v-phone" name="phone" type="text" maxlength="50" placeholder="(xx) xxxxx-xxxx">
                    </div>
                    <div class="field full">
                        <label for="v-notes">Observações (opcional)</label>
                        <textarea id="v-notes" name="notes" rows="2" placeholder="Observações de governança ou contexto do acesso"></textarea>
                    </div>
                    <div class="field full">
                        <label for="v-roles">Perfis</label>
                        <select id="v-roles" name="roles[]" multiple size="4">
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field full">
                        <span class="muted" style="font-size:0.82rem;">
                            A senha inicial e gerada automaticamente no padrao <strong>DDM + CPF</strong>.
                        </span>
                    </div>
                </div>
                <div class="actions" style="margin-top: 6px;">
                    <button type="submit">Criar visitante</button>
                    <span class="muted" style="font-size: 0.85rem;">Login = CPF informado. Troca de senha obrigatória no 1º acesso.</span>
                </div>
            </form>
        </section>
    @endif

    {{-- ── Abas: Servidores / Visitantes ───────────────────────────────── --}}
    <section class="card">
        <div class="tab-nav">
            <a href="?tab=servidores" class="{{ $activeTab === 'servidores' ? 'active' : '' }}">Servidores ({{ $servidores->count() }})</a>
            <a href="?tab=visitantes" class="{{ $activeTab === 'visitantes' ? 'active' : '' }}">Visitantes ({{ $visitantes->count() }})</a>
        </div>

        {{-- ─── ABA: SERVIDORES ────────────────────────────────────────── --}}
        <div class="tab-pane {{ $activeTab === 'servidores' ? 'active' : '' }}" id="tab-servidores">
            @if ($servidores->isEmpty())
                <p class="muted" style="text-align:center; padding:20px;">Nenhum servidor com acesso ainda.
                    Crie acessos pela tela de <a href="{{ route('rh.index') }}">RH / Funcionários</a>.</p>
            @else
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Servidor</th>
                            <th>CPF (login)</th>
                            <th>Status</th>
                            <th>Perfis</th>
                            <th>Último acesso</th>
                            <th>Escopos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($servidores as $user)
                            <tr>
                                <td>
                                    <strong>{{ $user->name }}</strong><br>
                                    <span class="muted" style="font-size:0.78rem;">{{ $user->funcionario?->cargo?->name ?? '—' }}</span>
                                </td>
                                <td style="font-family:monospace; font-size:0.85rem;">{{ $user->cpf_formatado ?: '—' }}</td>
                                <td>
                                    <span class="tag {{ $user->is_active ? 'good' : 'warn' }}">
                                        {{ $user->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                    @if ($user->must_change_password)
                                        <br><span class="tag warn" style="font-size:0.74rem;">Troca pendente</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse ($user->roles as $role)
                                        <span class="tag">{{ $role->name }}</span>
                                    @empty
                                        <span class="muted">Sem perfil</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if ($user->last_login_at)
                                        {{ $user->last_login_at->format('d/m/Y H:i') }}
                                    @else
                                        <span class="muted">Nunca</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse ($user->scopes as $scope)
                                        <span class="tag" style="font-size:0.74rem;">
                                            {{ $scope->scope_type === 'cartorio' ? '🏛' : '📋' }}
                                            {{ $scope->scope_type === 'cartorio' ? ($cartorioLabels[$scope->scope_key] ?? $scope->scope_key) : ($lavradoUnidadeLabels[$scope->scope_key] ?? $scope->scope_key) }}
                                        </span>
                                    @empty
                                        <span class="muted" style="font-size:0.8rem;">Sem restrições</span>
                                    @endforelse
                                </td>
                                <td>
                                    <div class="actions" style="gap:4px; flex-wrap:wrap;">
                                        @if ($canToggle && auth()->id() !== $user->id)
                                            <form method="POST" action="{{ route('access.users.toggle-active', $user) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="secondary" style="font-size:0.8rem; padding:4px 8px;">
                                                    {{ $user->is_active ? 'Inativar' : 'Ativar' }}
                                                </button>
                                            </form>
                                        @endif
                                        @if ($canUpdate)
                                            <button type="button" class="secondary" style="font-size:0.8rem; padding:4px 8px;"
                                                onclick="document.getElementById('edit-user-{{ $user->id }}').showModal()">Editar</button>
                                            <button type="button" class="secondary" style="font-size:0.8rem; padding:4px 8px;"
                                                onclick="document.getElementById('reset-pw-{{ $user->id }}').showModal()">Red. senha</button>
                                            <button type="button" class="secondary" style="font-size:0.8rem; padding:4px 8px;"
                                                onclick="document.getElementById('scopes-{{ $user->id }}').showModal()">Escopos</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            @if ($canUpdate)
                                @include('access.users._modals-edit', compact('user'))
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- ─── ABA: VISITANTES ────────────────────────────────────────── --}}
        <div class="tab-pane {{ $activeTab === 'visitantes' ? 'active' : '' }}" id="tab-visitantes">
            @if ($visitantes->isEmpty())
                <p class="muted" style="text-align:center; padding:20px;">Nenhum visitante cadastrado.</p>
            @else
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CPF (login)</th>
                            <th>E-mail</th>
                            <th>Status</th>
                            <th>Perfis</th>
                            <th>Último acesso</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($visitantes as $user)
                            <tr>
                                <td>
                                    <strong>{{ $user->name }}</strong>
                                    @if ($user->rg || $user->phone)
                                        <br><span class="muted" style="font-size:0.78rem;">{{ $user->rg ?: 'RG —' }} • {{ $user->phone ?: 'Tel —' }}</span>
                                    @endif
                                    @if ($user->notes)
                                        <br><span class="muted" style="font-size:0.76rem;">{{ \Illuminate\Support\Str::limit($user->notes, 90) }}</span>
                                    @endif
                                </td>
                                <td style="font-family:monospace; font-size:0.85rem;">{{ $user->cpf_formatado ?: '—' }}</td>
                                <td class="muted" style="font-size:0.82rem;">{{ $user->email ?: '—' }}</td>
                                <td>
                                    <span class="tag {{ $user->is_active ? 'good' : 'warn' }}">
                                        {{ $user->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                    @if ($user->must_change_password)
                                        <br><span class="tag warn" style="font-size:0.74rem;">Troca pendente</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse ($user->roles as $role)
                                        <span class="tag">{{ $role->name }}</span>
                                    @empty
                                        <span class="muted">Sem perfil</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if ($user->last_login_at)
                                        {{ $user->last_login_at->format('d/m/Y H:i') }}
                                    @else
                                        <span class="muted">Nunca</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="actions" style="gap:4px; flex-wrap:wrap;">
                                        @if ($canToggle && auth()->id() !== $user->id)
                                            <form method="POST" action="{{ route('access.users.toggle-active', $user) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="secondary" style="font-size:0.8rem; padding:4px 8px;">
                                                    {{ $user->is_active ? 'Inativar' : 'Ativar' }}
                                                </button>
                                            </form>
                                        @endif
                                        @if ($canUpdate)
                                            <button type="button" class="secondary" style="font-size:0.8rem; padding:4px 8px;"
                                                onclick="document.getElementById('edit-user-{{ $user->id }}').showModal()">Editar</button>
                                            <button type="button" class="secondary" style="font-size:0.8rem; padding:4px 8px;"
                                                onclick="document.getElementById('reset-pw-{{ $user->id }}').showModal()">Red. senha</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            @if ($canUpdate)
                                @include('access.users._modals-edit', compact('user'))
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </section>
@endsection
