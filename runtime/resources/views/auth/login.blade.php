<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso | Grom.Seg</title>
    <style>
        :root {
            --bg-top: #0f2744;
            --bg-bottom: #071521;
            --panel: rgba(7, 18, 30, 0.78);
            --panel-border: rgba(212, 175, 55, 0.22);
            --brand-white: #ffffff;
            --text: #edf3f9;
            --muted: rgba(237, 243, 249, 0.74);
            --accent: #d4af37;
            --accent-strong: #b88d1d;
            --danger-bg: rgba(127, 29, 29, 0.25);
            --danger-border: rgba(248, 113, 113, 0.3);
            --danger-text: #fecaca;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            min-height: 100%;
            margin: 0;
        }

        body {
            font-family: Aptos, "Segoe UI", Tahoma, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(212, 175, 55, 0.16), transparent 28%),
                radial-gradient(circle at right center, rgba(70, 124, 181, 0.22), transparent 32%),
                linear-gradient(160deg, var(--bg-top) 0%, #0a1e33 42%, var(--bg-bottom) 100%);
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            inset: auto;
            pointer-events: none;
            z-index: 0;
        }

        body::before {
            width: 38vw;
            height: 38vw;
            right: -10vw;
            top: -8vw;
            border-radius: 50%;
            border: 1px solid rgba(212, 175, 55, 0.12);
            box-shadow: 0 0 0 40px rgba(212, 175, 55, 0.04), 0 0 0 90px rgba(212, 175, 55, 0.02);
        }

        body::after {
            inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.028) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.028) 1px, transparent 1px);
            background-size: 28px 28px;
            mask-image: linear-gradient(180deg, rgba(0,0,0,0.3), transparent 82%);
        }

        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(320px, 1.15fr) minmax(340px, 420px);
            align-items: stretch;
        }

        .institutional {
            display: flex;
            align-items: center;
            padding: clamp(36px, 6vw, 80px);
        }

        .institutional-inner {
            width: 100%;
            max-width: 720px;
        }

        .brand-lockup {
            display: flex;
            align-items: flex-end;
            width: 100%;
            min-height: 156px;
            padding: 20px 24px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(8px);
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.22);
        }

        .brand-copy {
            display: grid;
            gap: 6px;
            width: 100%;
            justify-items: center;
            text-align: center;
        }

        .eyebrow {
            font-size: 0.76rem;
            letter-spacing: 0.24em;
            text-transform: uppercase;
            color: rgba(212, 175, 55, 0.82);
            font-weight: 700;
        }

        .brand-title {
            font-size: clamp(2.4rem, 5vw, 4.4rem);
            line-height: 0.92;
            font-weight: 800;
            letter-spacing: -0.05em;
        }

        .brand-subtitle {
            font-size: 0.96rem;
            color: var(--muted);
            max-width: none;
            line-height: 1.55;
            margin-top: 28px;
            text-align: justify;
            text-justify: inter-word;
        }

        .institutional-grid {
            margin-top: 34px;
            display: grid;
            width: 100%;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .institutional-card {
            padding: 18px 18px 16px;
            border-radius: 18px;
            background: linear-gradient(180deg, rgba(255,255,255,0.055), rgba(255,255,255,0.025));
            border: 1px solid rgba(255,255,255,0.08);
            min-height: 132px;
        }

        .institutional-card strong {
            display: block;
            font-size: 0.84rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(212, 175, 55, 0.88);
            margin-bottom: 10px;
        }

        .institutional-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
            font-size: 0.95rem;
            text-align: justify;
            text-justify: inter-word;
        }

        .login-shell {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(24px, 4vw, 44px);
        }

        .login-card {
            width: min(100%, 430px);
            border-radius: 28px;
            border: 1px solid var(--panel-border);
            background: linear-gradient(180deg, rgba(9, 21, 35, 0.9), var(--panel));
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.34);
            backdrop-filter: blur(12px);
            overflow: hidden;
        }

        .login-card-top {
            padding: 34px 28px 22px;
            border-bottom: 1px solid rgba(15, 39, 68, 0.08);
            background: var(--brand-white);
            text-align: center;
        }

        .login-brand-panel {
            padding: 12px 12px 10px;
        }

        .logo-stage {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            padding: 6px 0 2px;
        }

        .login-card-top img {
            width: 176px;
            height: auto;
            display: block;
            margin: 0 auto;
            filter: none;
        }

        .login-card-top h1 {
            margin: 0;
            font-size: 1.95rem;
            line-height: 1.1;
            color: #0f2744;
        }

        .login-card-body {
            padding: 24px 28px 28px;
        }

        .alert-error {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
            font-size: 0.92rem;
            line-height: 1.45;
        }

        .login-form {
            display: grid;
            gap: 16px;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        .field label {
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(212, 175, 55, 0.82);
        }

        .field input {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text);
            padding: 14px 15px;
            font: inherit;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        .field input::placeholder {
            color: rgba(237, 243, 249, 0.34);
        }

        .field input:focus {
            border-color: rgba(212, 175, 55, 0.62);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.12);
            background: rgba(255, 255, 255, 0.07);
        }

        .btn-submit {
            width: 100%;
            border: none;
            border-radius: 14px;
            padding: 14px 18px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-strong) 100%);
            color: #08131d;
            font: inherit;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 14px 28px rgba(184, 141, 29, 0.26);
            transition: transform 0.16s ease, filter 0.16s ease, box-shadow 0.16s ease;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            filter: brightness(1.04);
            box-shadow: 0 18px 32px rgba(184, 141, 29, 0.34);
        }

        .test-links {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .test-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 999px;
            text-decoration: none;
            border: 1px solid rgba(212, 175, 55, 0.28);
            color: #f4e0a1;
            background: rgba(212, 175, 55, 0.08);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        @media (max-width: 960px) {
            .page {
                grid-template-columns: 1fr;
            }

            .institutional {
                padding-bottom: 0;
            }

            .login-shell {
                padding-top: 18px;
                padding-bottom: 36px;
            }
        }

        @media (max-width: 640px) {
            .institutional {
                padding: 28px 20px 0;
            }

            .brand-lockup {
                width: 100%;
                align-items: flex-end;
                gap: 14px;
                border-radius: 20px;
                min-height: 136px;
            }

            .institutional-grid {
                grid-template-columns: 1fr;
            }

            .login-shell {
                padding: 18px 20px 32px;
            }

            .login-card-top,
            .login-card-body {
                padding-left: 20px;
                padding-right: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="institutional" aria-label="Apresentacao institucional do sistema">
            <div class="institutional-inner">
                <div class="brand-lockup">
                    <div class="brand-copy">
                        <div class="eyebrow">Ecossistema</div>
                        <div class="brand-title">Grom.Seg</div>
                    </div>
                </div>

                <p class="brand-subtitle">
                    O acesso ao Grom.Seg ocorre por autenticação central. Após o login, cada usuário visualiza apenas os módulos e recursos liberados pelo perfil definido pela administração.
                </p>

                <div class="institutional-grid">
                    <article class="institutional-card">
                        <strong>Acesso por perfil</strong>
                        <p>Menus, páginas e operações são liberados conforme o nível de permissão atribuído ao usuário pelo administrador.</p>
                    </article>

                    <article class="institutional-card">
                        <strong>Entrada unificada</strong>
                        <p>O acesso principal concentra a entrada institucional do sistema em uma única experiência.</p>
                    </article>

                    <article class="institutional-card">
                        <strong>Operação segura</strong>
                        <p>Autenticação, trilha de acesso e regras de segurança permanecem centralizadas no ambiente principal.</p>
                    </article>
                </div>
            </div>
        </section>

        <aside class="login-shell">
            <main class="login-card">
                <div class="login-card-top">
                    <div class="login-brand-panel">
                        <div class="logo-stage">
                            <img src="{{ asset('assets/logo_grom_transparent.png') }}" alt="Grom.Seg">
                        </div>
                        <h1>Acesso Grom.Seg</h1>
                    </div>
                </div>

                <div class="login-card-body">
                    @if ($errors->any())
                        <div class="alert-error">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="login-form">
                        @csrf

                        <div class="field">
                            <label for="login">Login</label>
                            <input id="login" name="login" type="text"
                                   value="{{ old('login') }}" required autofocus
                                placeholder="CPF, usuário ou e-mail">
                        </div>

                        <div class="field">
                            <label for="password">Senha</label>
                            <input id="password" name="password" type="password"
                                   required placeholder="Informe sua senha">
                        </div>

                        <button type="submit" class="btn-submit">Entrar</button>
                    </form>

                    @if (app()->environment(['local', 'testing']))
                        <div class="test-links">
                            <a href="{{ route('pilot.access') }}">Ver credenciais</a>
                            <a href="{{ route('evolucao') }}">Evolução</a>
                        </div>
                    @endif
                </div>
            </main>
        </aside>
    </div>
</body>
</html>

