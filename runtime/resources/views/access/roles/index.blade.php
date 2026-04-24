@extends('layouts.app')

@section('title', 'Perfis | Grom.Seg')

@section('content')
    @php($canManageRoles = auth()->user()->hasPermission('access.roles.manage'))

    <div class="section-head">
        <div>
            <h1>Perfis de acesso</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Base central do RBAC, com perfis auditaveis e sem exclusao direta de registros sensiveis.
            </p>
        </div>
        @if (auth()->user()->hasPermission('access.users.view'))
            <div class="actions">
                <a class="btn secondary" href="{{ route('access.users.index') }}">Abrir usuarios</a>
            </div>
        @endif
    </div>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Perfis cadastrados</small>
            <strong>{{ $roles->count() }}</strong>
            <span>Base formal de acesso do sistema.</span>
        </article>
        <article class="card">
            <small>Permissoes catalogadas</small>
            <strong>{{ collect($permissionsByModule)->flatten(1)->count() }}</strong>
            <span>Escopo completo habilitado no bootstrap.</span>
        </article>
        <article class="card">
            <small>Perfis com usuarios</small>
            <strong>{{ $roles->where('users_count', '>', 0)->count() }}</strong>
            <span>Perfis ja atribuÃ­dos a contas ativas.</span>
        </article>
    </div>

    @if ($canManageRoles)
        <section class="card" style="margin-bottom: 18px;">
            <h2 style="margin-top: 0;">Novo perfil</h2>
            <form method="POST" action="{{ route('access.roles.store') }}" class="grid">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label for="code">Codigo tecnico</label>
                        <input id="code" name="code" type="text" required placeholder="ex: gestor_regional">
                    </div>
                    <div class="field">
                        <label for="name">Nome</label>
                        <input id="name" name="name" type="text" required placeholder="ex: Gestor Regional">
                    </div>
                    <div class="field full">
                        <label for="description">Descricao</label>
                        <input id="description" name="description" type="text" placeholder="Resumo de uso e responsabilidade">
                    </div>
                    <div class="field full">
                        <label for="permissions">Permissoes</label>
                        <select id="permissions" name="permissions[]" multiple size="10">
                            @foreach ($permissionsByModule as $moduleCode => $permissions)
                                <optgroup label="{{ strtoupper($moduleCode) }}">
                                    @foreach ($permissions as $permission)
                                        <option value="{{ $permission->id }}">
                                            {{ $permission->code }} - {{ $permission->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit">Criar perfil</button>
                    <span class="muted">A criacao nao remove perfis existentes e preserva a trilha de auditoria.</span>
                </div>
            </form>
        </section>
    @else
        <section class="card" style="margin-bottom: 18px;">
            <h2 style="margin-top: 0;">Visualizacao somente leitura</h2>
            <p class="muted" style="margin: 0;">
                Seu acesso permite consultar os perfis, mas nao criar ou alterar atribuicoes.
            </p>
        </section>
    @endif

    <section class="card">
        <h2 style="margin-top: 0;">Perfis existentes</h2>
        <table>
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Nome</th>
                    <th>Usuarios</th>
                    <th>Permissoes</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($roles as $role)
                    <tr>
                        <td><strong>{{ $role->code }}</strong></td>
                        <td>
                            <strong>{{ $role->name }}</strong><br>
                            <span class="muted">{{ $role->description ?: 'Sem descricao cadastrada.' }}</span>
                        </td>
                        <td>{{ (int) $role->users_count }}</td>
                        <td>
                            @forelse ($role->permissions as $permission)
                                <span class="tag">{{ $permission->code }}</span>
                            @empty
                                <span class="muted">Sem permissoes vinculadas.</span>
                            @endforelse
                        </td>
                        <td>
                            @if ($canManageRoles)
                                <span class="tag good">Editavel</span>
                            @else
                                <span class="tag">Consulta</span>
                            @endif
                        </td>
                    </tr>
                    @if ($canManageRoles)
                        <tr>
                            <td colspan="5" style="background: #fbfcfe;">
                                <details>
                                    <summary>Editar {{ $role->code }}</summary>
                                    <form method="POST" action="{{ route('access.roles.update', $role) }}" class="grid" style="margin-top: 14px;">
                                        @csrf
                                        @method('PUT')
                                        <div class="form-grid">
                                            <div class="field">
                                                <label>Codigo tecnico</label>
                                                <input name="code" type="text" value="{{ $role->code }}" required>
                                            </div>
                                            <div class="field">
                                                <label>Nome</label>
                                                <input name="name" type="text" value="{{ $role->name }}" required>
                                            </div>
                                            <div class="field full">
                                                <label>Descricao</label>
                                                <input name="description" type="text" value="{{ $role->description }}">
                                            </div>
                                            <div class="field full">
                                                <label>Permissoes</label>
                                                <select name="permissions[]" multiple size="10">
                                                    @foreach ($permissionsByModule as $moduleCode => $permissions)
                                                        <optgroup label="{{ strtoupper($moduleCode) }}">
                                                            @foreach ($permissions as $permission)
                                                                <option value="{{ $permission->id }}" @selected($role->permissions->contains('id', $permission->id))>
                                                                    {{ $permission->code }} - {{ $permission->name }}
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="actions">
                                            <button type="submit">Salvar alteracoes</button>
                                        </div>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5">Nenhum perfil cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection

