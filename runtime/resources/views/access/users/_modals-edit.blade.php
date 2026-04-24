{{-- Partial: modals de edição, reset de senha e escopos por usuário --}}

{{-- Modal: Editar usuário --}}
<dialog id="edit-user-{{ $user->id }}" class="grom-modal grom-modal--md">
    <div class="grom-modal-card">
        <div class="grom-modal-head">
            <strong>Editar: {{ $user->name }}</strong>
            <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('edit-user-{{ $user->id }}').close()">✕</button>
        </div>
        <form method="POST" action="{{ route('access.users.update', $user) }}" class="grid">
            @csrf @method('PUT')
            <div class="form-grid">
                <div class="field">
                    <label>Nome completo</label>
                    <input name="name" type="text" value="{{ $user->name }}" required>
                </div>
                <div class="field">
                    <label>CPF (login)</label>
                    <input name="cpf" type="text" value="{{ $user->cpf }}" inputmode="numeric" pattern="\d{11}" maxlength="11"
                           placeholder="11 dígitos" oninput="this.value=this.value.replace(/\D/g,'')">
                </div>
                <div class="field full">
                    <label>E-mail</label>
                    <input name="email" type="email" value="{{ $user->email }}">
                </div>
                <div class="field">
                    <label>RG</label>
                    <input name="rg" type="text" value="{{ $user->rg }}" maxlength="50">
                </div>
                <div class="field">
                    <label>Telefone</label>
                    <input name="phone" type="text" value="{{ $user->phone }}" maxlength="50">
                </div>
                <div class="field full">
                    <label>Observações</label>
                    <textarea name="notes" rows="2">{{ $user->notes }}</textarea>
                </div>
                <div class="field full">
                    <label>Perfis</label>
                    <select name="roles[]" multiple size="5">
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected($user->roles->contains('id', $role->id))>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Nova senha (deixe em branco para não alterar)</label>
                    <input name="password" type="password" autocomplete="new-password" minlength="8">
                </div>
                <div class="field">
                    <label>Confirmar nova senha</label>
                    <input name="password_confirmation" type="password" autocomplete="new-password">
                </div>
            </div>
            <div class="actions" style="margin-top: 12px;">
                <button type="submit">Salvar alterações</button>
                <button type="button" class="secondary" onclick="document.getElementById('edit-user-{{ $user->id }}').close()">Cancelar</button>
            </div>
        </form>
    </div>
</dialog>

{{-- Modal: Reset de senha --}}
<dialog id="reset-pw-{{ $user->id }}" class="grom-modal grom-modal--sm">
    <div class="grom-modal-card">
        <div class="grom-modal-head">
            <strong>Redefinir senha — {{ $user->name }}</strong>
            <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('reset-pw-{{ $user->id }}').close()">✕</button>
        </div>
        <p class="muted" style="font-size: 0.85rem; margin: 0 0 14px;">
            A senha sera redefinida para o padrao <strong>DDM + CPF</strong> e marcada como temporaria.
            O usuario devera altera-la no proximo acesso.
        </p>
        <form method="POST" action="{{ route('access.users.reset-password', $user) }}" class="grid">
            @csrf
            <div class="actions" style="margin-top: 12px;">
                <button type="submit">Redefinir senha</button>
                <button type="button" class="secondary" onclick="document.getElementById('reset-pw-{{ $user->id }}').close()">Cancelar</button>
            </div>
        </form>
    </div>
</dialog>

{{-- Modal: Escopos --}}
<dialog id="scopes-{{ $user->id }}" class="grom-modal grom-modal--lg">
    <div class="grom-modal-card">
        <div class="grom-modal-head">
            <strong>Escopos — {{ $user->name }}</strong>
            <button type="button" class="secondary grom-modal-close" onclick="document.getElementById('scopes-{{ $user->id }}').close()">✕</button>
        </div>

        @if ($user->scopes->isEmpty())
            <p class="muted" style="font-size: 0.85rem; margin-bottom: 14px;">
                Sem restrições de escopo. O usuário acessa todos os dados permitidos pelo perfil.
            </p>
        @else
            <div class="grom-admin-stack" style="margin-bottom: 14px;">
                @foreach ($user->scopes as $scope)
                    <div class="grom-soft-panel" style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                        <div>
                            <strong style="font-size: 0.85rem;">
                                {{ $scope->scope_type === 'cartorio' ? 'Cartório' : 'Unidade' }}:
                            </strong>
                            <span class="muted" style="font-size: 0.85rem;">
                                @if ($scope->scope_type === 'cartorio')
                                    {{ $cartorioLabels[$scope->scope_key] ?? $scope->scope_key }}
                                @else
                                    {{ $lavradoUnidadeLabels[$scope->scope_key] ?? $scope->scope_key }}
                                @endif
                            </span>
                        </div>
                        <form method="POST" action="{{ route('access.users.scopes.destroy', [$user, $scope]) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="secondary" style="font-size: 0.8rem; padding: 4px 10px;">Remover</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

                <div class="form-grid">
            <form method="POST" action="{{ route('access.users.scopes.store', $user) }}"
                                    class="grom-soft-panel">
                @csrf
                <input type="hidden" name="scope_type" value="cartorio">
                <h4 style="margin: 0 0 10px; font-size: 0.9rem;">+ Vincular cartório</h4>
                <div class="field" style="margin-bottom: 10px;">
                    <label>Cartório</label>
                    <select name="scope_key">
                        @foreach ($cartorios as $cartorio)
                            <option value="{{ $cartorio->id }}">
                                {{ str_pad((string) $cartorio->number, 3, '0', STR_PAD_LEFT) }} — {{ $cartorio->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" style="font-size: 0.85rem; padding: 7px 12px;">Vincular</button>
            </form>

            <form method="POST" action="{{ route('access.users.scopes.store', $user) }}"
                class="grom-soft-panel">
                @csrf
                <input type="hidden" name="scope_type" value="lavrado_unidade">
                <h4 style="margin: 0 0 10px; font-size: 0.9rem;">+ Vincular unidade</h4>
                <div class="field" style="margin-bottom: 10px;">
                    <label>Unidade</label>
                    <select name="scope_key">
                        @foreach ($lavradoUnidades as $unidade)
                            <option value="{{ $unidade['value'] }}">{{ $unidade['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" style="font-size: 0.85rem; padding: 7px 12px;">Vincular</button>
            </form>
        </div>
    </div>
</dialog>
