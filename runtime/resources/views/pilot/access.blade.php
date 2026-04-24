@extends('layouts.app')

@section('title', 'Acesso de teste | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Acesso de teste do piloto</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Use esta pagina para entrar rapido no ambiente local ou de teste sem depender de memorizar as credenciais iniciais.
            </p>
        </div>
    </div>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Ambiente</small>
            <strong>Local / Testing</strong>
            <span>Pagina bloqueada fora dos ambientes de desenvolvimento e validacao.</span>
        </article>
        <article class="card">
            <small>Senha compartilhada</small>
            <strong>{{ $password }}</strong>
            <span>Usada pelos usuarios demo do piloto local.</span>
        </article>
        <article class="card">
            <small>Fluxo recomendado</small>
            <strong>Login</strong>
            <span>Entre pelo formulario normal e siga para o modulo desejado.</span>
        </article>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Credenciais de teste</h2>
        <table>
            <thead>
                <tr>
                    <th>Perfil</th>
                    <th>Usuario</th>
                    <th>Senha</th>
                    <th>Observacao</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($accounts as $account)
                    <tr>
                        <td><strong>{{ $account['label'] }}</strong></td>
                        <td><code>{{ $account['username'] }}</code></td>
                        <td><code>{{ $account['password'] }}</code></td>
                        <td>{{ $account['note'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <div class="grid" style="grid-template-columns: 1fr .9fr;">
        <section class="card">
            <h2 style="margin-top: 0;">Atalhos rapidos</h2>
            <div class="actions">
                <a class="btn" href="{{ route('login') }}">Ir para o login</a>
                <a class="btn secondary" href="{{ route('evolucao') }}">Abrir evolucao</a>
                <a class="btn secondary" href="{{ route('dashboard') }}">Abrir dashboard</a>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Nota operacional</h2>
            <p class="muted" style="margin: 0;">
                O administrador bootstrap entra com troca obrigatoria de senha. Os usuarios demo existem para validar
                permissao, navegacao e fluxo do piloto sem travar a migracao.
            </p>
        </section>
    </div>
@endsection

