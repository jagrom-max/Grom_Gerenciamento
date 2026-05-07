<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Grom.Seg')</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>{!! file_get_contents(resource_path('css/grom.css')) !!}</style>
    @endif

    <style>
        .grom-date-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            width: 100%;
            min-width: 130px;
        }

        .grom-date-wrap > .grom-date-native {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            clip: rect(0 0 0 0) !important;
            overflow: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        .grom-date-wrap > .grom-date-display {
            width: 100%;
            padding-right: 34px;
        }

        .grom-date-wrap > .grom-date-btn {
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--ink-soft, #666);
            cursor: pointer;
            width: 24px;
            height: 24px;
            border-radius: 4px;
            font-size: 14px;
            line-height: 1;
            padding: 0;
        }

        .grom-date-wrap > .grom-date-btn:hover {
            background: var(--surface-alt, #f1f3f8);
        }

        .grom-date-picker {
            position: fixed;
            z-index: 9999;
            width: 280px;
            background: #fff;
            border: 1px solid var(--border, #d8dce5);
            border-radius: 8px;
            box-shadow: 0 14px 36px rgba(0, 0, 0, 0.18);
            padding: 10px;
            font-size: 13px;
        }

        .grom-date-picker[hidden] {
            display: none !important;
        }

        .grom-date-picker-head {
            display: grid;
            grid-template-columns: 28px 1fr 28px;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .grom-date-picker-title {
            text-align: center;
            font-weight: 600;
            color: var(--ink, #1e2633);
            user-select: none;
        }

        .grom-date-picker-nav {
            border: 1px solid var(--border, #d8dce5);
            border-radius: 6px;
            background: #fff;
            color: var(--ink-soft, #555);
            cursor: pointer;
            width: 28px;
            height: 28px;
            line-height: 1;
            padding: 0;
        }

        .grom-date-picker-nav:hover {
            background: var(--surface-alt, #f4f6fa);
        }

        .grom-date-picker-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }

        .grom-date-picker-dow {
            text-align: center;
            font-size: 11px;
            color: var(--ink-soft, #76809a);
            user-select: none;
            padding: 2px 0;
        }

        .grom-date-picker-day {
            border: 1px solid transparent;
            border-radius: 6px;
            height: 30px;
            background: #fff;
            cursor: pointer;
            color: var(--ink, #233044);
            padding: 0;
        }

        .grom-date-picker-day:hover:not([disabled]) {
            background: var(--surface-alt, #f4f6fa);
            border-color: var(--border, #dde2ee);
        }

        .grom-date-picker-day.is-selected {
            background: #0f5ea8;
            color: #fff;
            border-color: #0f5ea8;
        }

        .grom-date-picker-day.is-today:not(.is-selected) {
            border-color: #0f5ea8;
        }

        .grom-date-picker-day[disabled] {
            color: #b7bfce;
            cursor: not-allowed;
            background: #fafbfd;
        }

        @media print {
            .grom-date-wrap > .grom-date-btn,
            .grom-date-picker {
                display: none !important;
            }

            .grom-date-wrap > .grom-date-display {
                padding-right: 8px;
            }
        }
    </style>
</head>
<body class="app-body">
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <img src="{{ asset('assets/logo_grom_transparent.png') }}" alt="Logo Grom.Seg">
                <div>
                    <strong>Grom.Seg</strong>
                    <span>DDM Rio Claro</span>
                </div>
            </div>

            @auth
                <nav class="nav">
                    <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>

                    {{-- Operacional --}}
                    @if (
                        auth()->user()->hasPermission('produtividade.cartorios.view')
                        || auth()->user()->hasPermission('produtividade.flagrantes.view')
                        || auth()->user()->hasPermission('produtividade.stats.view')
                        || auth()->user()->hasPermission('operacional.view')
                        || auth()->user()->hasPermission('operacional.mandados.view')
                        || auth()->user()->hasPermission('operacional.ordens.view')
                    )
                        <details class="nav-folder" @if (request()->routeIs('produtividade.*', 'operacional.*')) open @endif>
                            <summary>Operacional</summary>
                            <div class="nav-folder-panel">
                                @if (auth()->user()->hasPermission('operacional.view'))
                                    <a href="{{ route('operacional.index') }}" @class(['active' => request()->routeIs('operacional.index')])>Painel operacional</a>
                                @endif
                                @if (auth()->user()->hasPermission('operacional.mandados.view'))
                                    <a href="{{ route('operacional.mandados.index') }}" @class(['active' => request()->routeIs('operacional.mandados.index')])>Mandados de Prisão</a>
                                    <a href="{{ route('operacional.mandados.stats') }}" @class(['active' => request()->routeIs('operacional.mandados.stats')])>Estatísticas de Mandados</a>
                                    <a href="{{ route('operacional.mandados.relatorio') }}" @class(['active' => request()->routeIs('operacional.mandados.relatorio')])>Relatório de Mandados</a>
                                @endif
                                @if (auth()->user()->hasPermission('operacional.objetos.view'))
                                    <a href="{{ route('operacional.objetos.index') }}" @class(['active' => request()->routeIs('operacional.objetos.*')])>Objetos Apreendidos</a>
                                @endif
                                @if (auth()->user()->hasPermission('operacional.ordens.view'))
                                    <a href="{{ route('operacional.ordens.index') }}" @class(['active' => request()->routeIs('operacional.ordens.*')])>Ordens de Serviço</a>
                                @endif
                                @if (auth()->user()->hasPermission('produtividade.cartorios.view'))
                                    <a href="{{ route('produtividade.hub') }}" @class(['active' => request()->routeIs('produtividade.hub')])>Hub de Produtividade</a>
                                    <a href="{{ route('produtividade.cartorios.index') }}" @class(['active' => request()->routeIs('produtividade.cartorios.*')])>Cartórios</a>
                                @endif
                                @if (auth()->user()->hasPermission('produtividade.boletins.view'))
                                    <a href="{{ route('produtividade.boletins.index') }}" @class(['active' => request()->routeIs('produtividade.boletins.*')])>Boletins / Upload Consolidado</a>
                                @endif
                                @if (auth()->user()->hasPermission('produtividade.flagrantes.view'))
                                    <a href="{{ route('produtividade.flagrantes.index') }}" @class(['active' => request()->routeIs('produtividade.flagrantes.index')])>Fila de Flagrantes</a>
                                    <a href="{{ route('produtividade.flagrantes.relatorio') }}" @class(['active' => request()->routeIs('produtividade.flagrantes.relatorio')])>Rel. Flagrantes (A4)</a>
                                @endif
                                @if (auth()->user()->hasPermission('produtividade.stats.view'))
                                    <a href="{{ route('produtividade.stats.index') }}" @class(['active' => request()->routeIs('produtividade.stats.*')])>Estatísticas</a>
                                @endif
                            </div>
                        </details>
                    @endif

                    {{-- Pessoas / RH --}}
                    @if (auth()->user()->hasPermission('rh.view'))
                        <details class="nav-folder" @if (request()->routeIs('funcionarios.*', 'rh.*')) open @endif>
                            <summary>Pessoas</summary>
                            <div class="nav-folder-panel">
                                <a href="{{ route('funcionarios.index') }}" @class(['active' => request()->routeIs('funcionarios.*')])>Funcionários</a>
                                <a href="{{ route('rh.index') }}" @class(['active' => request()->routeIs('rh.index')])>RH / Administração</a>
                                <a href="{{ route('rh.confronto') }}" @class(['active' => request()->routeIs('rh.confronto')])>Confronto de Afastamentos</a>
                                <a href="{{ route('rh.composicao') }}" @class(['active' => request()->routeIs('rh.composicao')])>Composição dos Cartórios</a>
                                <a href="{{ route('rh.stats') }}" @class(['active' => request()->routeIs('rh.stats')])>Estatísticas RH</a>
                            </div>
                        </details>
                    @endif

                    {{-- Escalas / Agenda --}}
                    @if (auth()->user()->hasPermission('escalas.view') || auth()->user()->hasPermission('calendarios.view'))
                        <details class="nav-folder" @if (request()->routeIs('escalas.*', 'calendarios.*')) open @endif>
                            <summary>Escalas</summary>
                            <div class="nav-folder-panel">
                                @if (auth()->user()->hasPermission('escalas.view'))
                                    <a href="{{ route('escalas.index') }}" @class(['active' => request()->routeIs('escalas.index')])>Escala Mensal</a>
                                    <a href="{{ route('escalas.plantoes') }}" @class(['active' => request()->routeIs('escalas.plantoes')])>Plantões</a>
                                    <a href="{{ route('escalas.plantoes.relatorio') }}" @class(['active' => request()->routeIs('escalas.plantoes.relatorio')])>Rel. Plantões (A4)</a>
                                @endif
                                @if (auth()->user()->hasPermission('calendarios.view'))
                                    <a href="{{ route('calendarios.index') }}" @class(['active' => request()->routeIs('calendarios.*')])>Agenda de Afastamentos</a>
                                @endif
                            </div>
                        </details>
                    @endif

                    {{-- Relatórios (menu dedicado) --}}
                    @if (auth()->user()->hasPermission('relatorios.emit') || auth()->user()->hasPermission('analise.view'))
                        <details class="nav-folder" @if (request()->routeIs('relatorios.*', 'analise.*')) open @endif>
                            <summary>Relatórios</summary>
                            <div class="nav-folder-panel">
                                @if (auth()->user()->hasPermission('relatorios.emit'))
                                    <a href="{{ route('relatorios.index') }}" @class(['active' => request()->routeIs('relatorios.index')])>Central de Relatórios</a>
                                    <a href="{{ route('relatorios.produtividade.a4') }}" @class(['active' => request()->routeIs('relatorios.produtividade.a4')])>Produtividade A4</a>
                                    <a href="{{ route('relatorios.operacional.integrado') }}" @class(['active' => request()->routeIs('relatorios.operacional.integrado')])>Acompanhamento Operacional</a>
                                @endif
                                @if (auth()->user()->hasPermission('analise.view'))
                                    <a href="{{ route('analise.index') }}" @class(['active' => request()->routeIs('analise.index')])>Análise de Dados</a>
                                    <a href="{{ route('analise.estatisticas') }}" @class(['active' => request()->routeIs('analise.estatisticas')])>Estatísticas de BOs</a>
                                @endif
                            </div>
                        </details>
                    @endif

                    {{-- Manutenção (backup + auditoria + acesso) --}}
                    @if (
                        auth()->user()->hasPermission('backup.view')
                        || auth()->user()->hasPermission('auditoria.view')
                        || auth()->user()->hasPermission('access.users.view')
                        || auth()->user()->hasPermission('access.roles.view')
                    )
                        <details class="nav-folder" @if (request()->routeIs('backup.*', 'auditoria.*', 'access.*')) open @endif>
                            <summary>Manutenção</summary>
                            <div class="nav-folder-panel">
                                @if (auth()->user()->hasPermission('backup.view'))
                                    <a href="{{ route('backup.index') }}" @class(['active' => request()->routeIs('backup.*')])>Backup</a>
                                @endif
                                @if (auth()->user()->hasPermission('auditoria.view'))
                                    <a href="{{ route('auditoria.index') }}" @class(['active' => request()->routeIs('auditoria.*')])>Auditoria</a>
                                @endif
                                @if (auth()->user()->hasPermission('access.users.view'))
                                    <a href="{{ route('access.users.index') }}" @class(['active' => request()->routeIs('access.users.*')])>Usuários</a>
                                @endif
                                @if (auth()->user()->hasPermission('access.roles.view'))
                                    <a href="{{ route('access.roles.index') }}" @class(['active' => request()->routeIs('access.roles.*')])>Perfis de Acesso</a>
                                @endif
                            </div>
                        </details>
                    @endif

                    @if (app()->environment(['local', 'testing']))
                        <a href="{{ route('evolucao') }}" @class(['active' => request()->routeIs('homologacao', 'evolucao')])>Evolução</a>
                    @endif
                    <a href="{{ route('password.edit') }}" @class(['active' => request()->routeIs('password.*')])>Minha senha</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="primary">Sair</button>
                    </form>
                </nav>
            @endauth
        </aside>

        <div class="content-area">
        @if (session('status'))
            <div class="alert good">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert bad">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @auth
            @if (config('grom_access.require_password_change') && auth()->user()->must_change_password)
                <div class="alert bad">A troca inicial de senha é obrigatória antes de acessar os demais módulos.</div>
            @endif
        @endauth

        <main class="page-card">
            @yield('content')
        </main>
        </div>{{-- /.content-area --}}
    </div>

    <script>
        (function () {
            const MONTHS_PT_BR = ['janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
            const DOW_PT_BR = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
            const picker = document.createElement('div');
            picker.className = 'grom-date-picker';
            picker.hidden = true;
            document.body.appendChild(picker);

            const state = {
                nativeInput: null,
                textInput: null,
                viewYear: 0,
                viewMonth: 0,
            };

            function pad(num) {
                return String(num).padStart(2, '0');
            }

            function toIso(year, month, day) {
                return String(year) + '-' + pad(month) + '-' + pad(day);
            }

            function parseIso(value) {
                if (!value) return null;
                const raw = String(value).trim().slice(0, 10);
                const parts = raw.split('-');
                if (parts.length !== 3) return null;
                const year = Number(parts[0]);
                const month = Number(parts[1]);
                const day = Number(parts[2]);
                if (!year || !month || !day) return null;
                const dt = new Date(year, month - 1, day);
                if (dt.getFullYear() !== year || dt.getMonth() !== month - 1 || dt.getDate() !== day) return null;
                return dt;
            }

            function parseBr(value) {
                if (!value) return null;
                const parts = String(value).trim().split('/');
                if (parts.length !== 3) return null;
                const day = Number(parts[0]);
                const month = Number(parts[1]);
                const year = Number(parts[2]);
                if (!year || !month || !day) return null;
                const dt = new Date(year, month - 1, day);
                if (dt.getFullYear() !== year || dt.getMonth() !== month - 1 || dt.getDate() !== day) return null;
                return dt;
            }

            function formatBr(date) {
                return pad(date.getDate()) + '/' + pad(date.getMonth() + 1) + '/' + String(date.getFullYear());
            }

            function maskBr(raw) {
                const digits = String(raw || '').replace(/\D+/g, '').slice(0, 8);
                if (digits.length <= 2) return digits;
                if (digits.length <= 4) return digits.slice(0, 2) + '/' + digits.slice(2);
                return digits.slice(0, 2) + '/' + digits.slice(2, 4) + '/' + digits.slice(4);
            }

            function isBefore(a, b) {
                return a.getTime() < b.getTime();
            }

            function isAfter(a, b) {
                return a.getTime() > b.getTime();
            }

            function withinRange(date, nativeInput) {
                const min = parseIso(nativeInput.min);
                const max = parseIso(nativeInput.max);
                if (min && isBefore(date, min)) return false;
                if (max && isAfter(date, max)) return false;
                return true;
            }

            function setNativeIso(nativeInput, isoValue, shouldDispatch) {
                const old = nativeInput.value;
                nativeInput.value = isoValue || '';
                if (shouldDispatch && old !== nativeInput.value) {
                    nativeInput.dispatchEvent(new Event('input', { bubbles: true }));
                    nativeInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            function commitTextToNative(nativeInput, textInput, forceValidation) {
                const masked = maskBr(textInput.value);
                textInput.value = masked;
                if (!masked) {
                    textInput.setCustomValidity('');
                    setNativeIso(nativeInput, '', true);
                    return true;
                }

                const parsed = parseBr(masked);
                if (!parsed || !withinRange(parsed, nativeInput)) {
                    if (forceValidation) {
                        textInput.setCustomValidity('Informe data valida no formato DD/MM/AAAA.');
                        textInput.reportValidity();
                    }
                    return false;
                }

                textInput.value = formatBr(parsed);
                textInput.setCustomValidity('');
                setNativeIso(nativeInput, toIso(parsed.getFullYear(), parsed.getMonth() + 1, parsed.getDate()), true);
                return true;
            }

            function syncTextFromNative(nativeInput, textInput) {
                const parsed = parseIso(nativeInput.value);
                textInput.value = parsed ? formatBr(parsed) : '';
                textInput.setCustomValidity('');
            }

            function closePicker() {
                picker.hidden = true;
                state.nativeInput = null;
                state.textInput = null;
            }

            function renderPicker() {
                if (!state.nativeInput || !state.textInput) return;

                const selected = parseIso(state.nativeInput.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                const year = state.viewYear;
                const monthIndex = state.viewMonth;
                const firstDay = new Date(year, monthIndex, 1);
                const startWeekday = firstDay.getDay();
                const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();

                picker.innerHTML = '';

                const head = document.createElement('div');
                head.className = 'grom-date-picker-head';

                const prev = document.createElement('button');
                prev.type = 'button';
                prev.className = 'grom-date-picker-nav';
                prev.textContent = '<';
                prev.addEventListener('click', function () {
                    if (state.viewMonth === 0) {
                        state.viewMonth = 11;
                        state.viewYear -= 1;
                    } else {
                        state.viewMonth -= 1;
                    }
                    renderPicker();
                });

                const title = document.createElement('div');
                title.className = 'grom-date-picker-title';
                title.textContent = MONTHS_PT_BR[monthIndex] + ' ' + year;

                const next = document.createElement('button');
                next.type = 'button';
                next.className = 'grom-date-picker-nav';
                next.textContent = '>';
                next.addEventListener('click', function () {
                    if (state.viewMonth === 11) {
                        state.viewMonth = 0;
                        state.viewYear += 1;
                    } else {
                        state.viewMonth += 1;
                    }
                    renderPicker();
                });

                head.appendChild(prev);
                head.appendChild(title);
                head.appendChild(next);
                picker.appendChild(head);

                const grid = document.createElement('div');
                grid.className = 'grom-date-picker-grid';

                DOW_PT_BR.forEach(function (label) {
                    const dow = document.createElement('div');
                    dow.className = 'grom-date-picker-dow';
                    dow.textContent = label;
                    grid.appendChild(dow);
                });

                for (let i = 0; i < startWeekday; i += 1) {
                    const blank = document.createElement('div');
                    grid.appendChild(blank);
                }

                for (let day = 1; day <= daysInMonth; day += 1) {
                    const date = new Date(year, monthIndex, day);
                    const iso = toIso(year, monthIndex + 1, day);
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'grom-date-picker-day';
                    btn.textContent = String(day);

                    if (!withinRange(date, state.nativeInput)) {
                        btn.disabled = true;
                    }

                    if (selected && selected.getTime() === date.getTime()) {
                        btn.classList.add('is-selected');
                    }

                    if (today.getTime() === date.getTime()) {
                        btn.classList.add('is-today');
                    }

                    btn.addEventListener('click', function () {
                        setNativeIso(state.nativeInput, iso, true);
                        syncTextFromNative(state.nativeInput, state.textInput);
                        closePicker();
                        state.textInput.focus();
                    });

                    grid.appendChild(btn);
                }

                picker.appendChild(grid);
            }

            function positionPicker(anchor) {
                const rect = anchor.getBoundingClientRect();
                const top = rect.bottom + 6;
                const maxLeft = Math.max(8, window.innerWidth - 288);
                const left = Math.min(Math.max(8, rect.left), maxLeft);
                picker.style.top = String(top) + 'px';
                picker.style.left = String(left) + 'px';
            }

            function openPicker(nativeInput, textInput, anchor) {
                const base = parseIso(nativeInput.value) || new Date();
                state.nativeInput = nativeInput;
                state.textInput = textInput;
                state.viewYear = base.getFullYear();
                state.viewMonth = base.getMonth();
                picker.hidden = false;
                renderPicker();
                positionPicker(anchor);
            }

            function enhanceDateInput(nativeInput) {
                if (!nativeInput || nativeInput.dataset.gromDateEnhanced === '1') return;
                if (nativeInput.closest('[data-grom-date-skip="1"]')) return;

                nativeInput.dataset.gromDateEnhanced = '1';
                nativeInput.classList.add('grom-date-native');
                nativeInput.tabIndex = -1;
                nativeInput.setAttribute('aria-hidden', 'true');

                const wrapper = document.createElement('div');
                wrapper.className = 'grom-date-wrap';
                nativeInput.parentNode.insertBefore(wrapper, nativeInput);
                wrapper.appendChild(nativeInput);

                const textInput = document.createElement('input');
                textInput.type = 'text';
                textInput.className = nativeInput.className.replace('grom-date-native', '').trim();
                if (textInput.className) textInput.className += ' ';
                textInput.className += 'grom-date-display';
                textInput.placeholder = 'DD/MM/AAAA';
                textInput.inputMode = 'numeric';
                textInput.autocomplete = 'off';
                textInput.disabled = nativeInput.disabled;
                textInput.readOnly = nativeInput.readOnly;
                if (nativeInput.required) {
                    textInput.required = true;
                }

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'grom-date-btn';
                button.textContent = 'cal';
                button.setAttribute('aria-label', 'Abrir calendario');
                button.disabled = nativeInput.disabled || nativeInput.readOnly;

                wrapper.appendChild(textInput);
                wrapper.appendChild(button);

                syncTextFromNative(nativeInput, textInput);

                textInput.addEventListener('input', function () {
                    textInput.value = maskBr(textInput.value);
                    textInput.setCustomValidity('');
                });

                textInput.addEventListener('blur', function () {
                    commitTextToNative(nativeInput, textInput, true);
                });

                textInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        closePicker();
                    }
                });

                button.addEventListener('click', function () {
                    if (picker.hidden || state.nativeInput !== nativeInput) {
                        openPicker(nativeInput, textInput, wrapper);
                    } else {
                        closePicker();
                    }
                });

                nativeInput.addEventListener('change', function () {
                    if (document.activeElement !== textInput) {
                        syncTextFromNative(nativeInput, textInput);
                    }
                });
            }

            function enhanceAllDateInputs(root) {
                root.querySelectorAll('input[type="date"]').forEach(enhanceDateInput);
            }

            function bindFormSubmitNormalization() {
                document.addEventListener('submit', function (event) {
                    const form = event.target;
                    if (!(form instanceof HTMLFormElement)) return;

                    const dateInputs = form.querySelectorAll('input[type="date"][data-grom-date-enhanced="1"]');
                    for (const nativeInput of dateInputs) {
                        const wrapper = nativeInput.closest('.grom-date-wrap');
                        const textInput = wrapper ? wrapper.querySelector('.grom-date-display') : null;
                        if (!textInput) continue;
                        const ok = commitTextToNative(nativeInput, textInput, true);
                        if (!ok) {
                            event.preventDefault();
                            textInput.focus();
                            return;
                        }
                    }
                }, true);
            }

            document.addEventListener('click', function (event) {
                if (picker.hidden) return;
                const insidePicker = picker.contains(event.target);
                const insideWrap = event.target.closest('.grom-date-wrap');
                if (!insidePicker && !insideWrap) {
                    closePicker();
                }
            });

            window.addEventListener('resize', function () {
                if (!picker.hidden && state.nativeInput) {
                    const wrapper = state.nativeInput.closest('.grom-date-wrap');
                    if (wrapper) positionPicker(wrapper);
                }
            });

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    enhanceAllDateInputs(document);
                    bindFormSubmitNormalization();
                });
            } else {
                enhanceAllDateInputs(document);
                bindFormSubmitNormalization();
            }

            const observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (!(node instanceof HTMLElement)) return;
                        if (node.matches('input[type="date"]')) {
                            enhanceDateInput(node);
                        }
                        enhanceAllDateInputs(node);
                    });
                });
            });

            observer.observe(document.documentElement, { childList: true, subtree: true });
        }());
    </script>

    <script>
        (function () {
            const folders = Array.from(document.querySelectorAll('.nav-folder'));

            // Fecha os outros painéis quando um é aberto
            folders.forEach((folder) => {
                folder.addEventListener('toggle', () => {
                    if (!folder.open) {
                        return;
                    }

                    folders.forEach((other) => {
                        if (other !== folder) {
                            other.open = false;
                        }
                    });
                });
            });

            // Fecha todos ao clicar fora da nav
            document.addEventListener('click', (event) => {
                const insideNav = event.target.closest('.nav-folder') || event.target.closest('.nav');

                if (insideNav) {
                    return;
                }

                folders.forEach((folder) => {
                    folder.open = false;
                });
            });

            // Fecha o painel ao clicar em qualquer link dentro dele
            document.querySelectorAll('.nav-folder-panel a').forEach((link) => {
                link.addEventListener('click', () => {
                    folders.forEach((folder) => { folder.open = false; });
                });
            });
        }());
    </script>
</body>
</html>

