@extends('layouts.app')

@section('title', 'Troca obrigatÃ³ria de senha | Grom.Seg')

@section('content')
    <div class="grid">
        <div class="section-head">
            <div>
                <h1>AtualizaÃ§Ã£o de senha</h1>
                <p class="muted">
                    @if ($mustChangePassword)
                        Este Ã© o primeiro acesso ou sua senha foi redefinida por um administrador.
                    @else
                        Mantenha sua credencial atualizada para preservar o acesso seguro ao Grom.Seg.
                    @endif
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="grid">
            @csrf
            @method('PUT')

            <div class="form-grid">
                @if ($mustChangePassword)
                    <div class="alert good" style="grid-column: 1 / -1;">
                        No primeiro acesso, basta definir a nova senha. A senha atual jÃ¡ foi validada no login.
                    </div>
                @else
                    <div class="field full">
                        <label for="current_password">Senha atual</label>
                        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                    </div>
                @endif

                <div class="field">
                    <label for="password">Nova senha</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirmar nova senha</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                </div>
            </div>

            <div class="alert good">
                Use ao menos 12 caracteres com letras maiÃºsculas, minÃºsculas, nÃºmeros e sÃ­mbolos.
            </div>

            <div class="actions">
                <button type="submit">Salvar nova senha</button>
            </div>
        </form>
    </div>
@endsection

