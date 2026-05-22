@extends('layouts.app')

@section('title', 'Escala Mensal | Grom.Seg')

@section('content')
    @php
        $canManageEscalas = auth()->user()?->isSuperAdmin() ?? false;
        $anosDisp = ! empty($anosPhp) ? $anosPhp : ($snapshot['available_years'] ?? [now()->year]);
        $funcionarioOpcoes = $phpFuncionarios
            ->map(fn ($funcionario) => $funcionario->short_name ?: $funcionario->name)
            ->filter()
            ->unique()
            ->values();
    @endphp

    <style>
        .escala-clean-wrap {
            display: grid;
            gap: 14px;
        }

        .escala-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .plantao-cell {
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        .scale-row-holiday td {
            background: #f7f1dd;
        }

        .scale-holiday-marker {
            background: #f4d03f;
            color: #1f1f1f;
            font-weight: 700;
        }

        .escala-workflow {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 8px;
        }

        .escala-workflow-title {
            margin: 0 0 2px;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .escala-workflow-text {
            margin: 0;
            color: var(--grom-ink-soft);
            font-size: 0.84rem;
        }

        .escala-primary-action {
            background: #17446f;
            color: #fff;
            border: 1px solid #103255;
        }

        .escala-state-note {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            padding: 6px 10px;
            border-radius: 8px;
            background: #edf2f7;
            color: var(--grom-ink-soft);
            font-size: 0.82rem;
            font-weight: 700;
        }

        .scale-edit-form {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .scale-edit-form input,
        .scale-edit-form select {
            min-width: 0;
            width: 100%;
            padding: 6px 7px;
            font-size: 0.78rem;
        }

        .scale-edit-form button {
            padding: 6px 8px;
            border-radius: 8px;
            font-size: 0.72rem;
        }

        .plantao-calendar {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 4px;
            margin-top: 6px;
        }

        .plantao-calendar-head {
            color: var(--grom-ink-soft);
            font-size: 0.68rem;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
        }

        .plantao-day {
            min-height: 34px;
            padding: 0;
            border-radius: 8px;
            border: 1px solid var(--grom-line);
            background: #fff;
            color: var(--grom-ink);
            box-shadow: none;
            font-size: 0.78rem;
        }

        .plantao-day.is-selected {
            background: #17446f;
            color: #fff;
            border-color: #103255;
        }

        .plantao-selected-list {
            min-height: 28px;
            margin-top: 8px;
            color: var(--grom-ink-soft);
            font-size: 0.78rem;
            line-height: 1.4;
        }

        @media print {
            .no-print,
            .sidebar,
            .alert {
                display: none !important;
            }

            .page-card {
                border: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
        }
    </style>

    <div class="escala-clean-wrap">
        <div class="section-head">
            <div>
                <h1>Escala Mensal</h1>
            </div>
            <div class="escala-actions no-print">
                @if ($canManageEscalas)
                    @if (($phpDias ?? collect())->isEmpty())
                        <form method="POST" action="{{ route('escalas.gerar') }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="ano" value="{{ $filters['ano'] }}">
                            <input type="hidden" name="mes" value="{{ $filters['mes'] }}">
                            <button class="btn escala-primary-action" type="submit">Gerar provisória</button>
                        </form>
                    @endif
                @endif
                <a class="btn secondary" href="{{ route('escalas.print.pdf', $filters) }}" target="_blank" rel="noopener noreferrer">Imprimir / Salvar PDF</a>
                <a class="btn secondary" href="{{ route('escalas.prova', $filters) }}" target="_blank" rel="noopener noreferrer">Prova da escala</a>
                <a class="btn secondary" href="{{ route('escalas.plantoes', $filters) }}">Plantões</a>
            </div>
        </div>

        @if (session('status-success'))
            <div class="alert success no-print">{{ session('status-success') }}</div>
        @elseif (session('status-error'))
            <div class="alert danger no-print">{{ session('status-error') }}</div>
        @elseif (session('status-warning'))
            <div class="alert warn no-print">{{ session('status-warning') }}</div>
        @endif

        <section class="card no-print">
            <form method="GET" action="{{ route('escalas.index') }}" class="actions">
                <div class="field" style="min-width: 140px;">
                    <label for="ano">Ano</label>
                    <select id="ano" name="ano">
                        @foreach ($anosDisp as $year)
                            <option value="{{ $year }}" @selected($filters['ano'] === $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field" style="min-width: 180px;">
                    <label for="mes">Mês</label>
                    <select id="mes" name="mes">
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}" @selected($filters['mes'] === $month)>
                                {{ str_pad((string)$month, 2, '0', STR_PAD_LEFT) }} - {{ \Carbon\Carbon::create()->month($month)->locale('pt_BR')->isoFormat('MMMM') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if (($todasVersoes ?? collect())->count() > 1)
                    <div class="field" style="min-width: 130px;">
                        <label for="versao">Versão</label>
                        <select id="versao" name="versao">
                            <option value="">Mais recente</option>
                            @foreach ($todasVersoes as $vItem)
                                <option value="{{ $vItem->versao }}" @selected($filters['versao'] === $vItem->versao)>
                                    v{{ $vItem->versao }} {{ $vItem->status === 'definitiva' ? '✅' : '⏳' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="actions" style="align-self: end;">
                    <button type="submit">Pesquisar</button>
                </div>
            </form>
        </section>

        @if ($canManageEscalas)
            <section class="card no-print escala-workflow">
                <div>
                    <p class="escala-workflow-title">Fluxo da escala</p>
                    <p class="escala-workflow-text">
                        Gere a provisória, confira e ajuste os dias editáveis. <b>É obrigatório visualizar a escala (tela ou PDF) antes de aprovar.</b> Depois grave como definitiva; se precisar emendar, crie uma nova versão.
                    </p>
                    @if (isset($escalaVersao) && ($escalaVersao->status === 'provisoria') && empty($escalaVersao->conferida_em))
                        <div class="alert warn" style="margin-top:8px;">⚠️ <b>Conferência obrigatória:</b> Antes de aprovar, visualize a escala na tela ou gere o PDF para liberar a aprovação.</div>
                    @endif
                </div>
                <div class="escala-actions">
                    @if (($phpDias ?? collect())->isEmpty())
                        <form method="POST" action="{{ route('escalas.gerar') }}">
                            @csrf
                            <input type="hidden" name="ano" value="{{ $filters['ano'] }}">
                            <input type="hidden" name="mes" value="{{ $filters['mes'] }}">
                            <button class="btn-sm escala-primary-action" type="submit">Gerar provisória</button>
                        </form>
                    @elseif (($escalaVersao?->status ?? 'provisoria') !== 'definitiva')
                        <span class="escala-state-note">Provisória já gerada para este mês</span>
                        <form method="POST" action="{{ route('escalas.fechar') }}">
                            @csrf
                            <input type="hidden" name="ano" value="{{ $filters['ano'] }}">
                            <input type="hidden" name="mes" value="{{ $filters['mes'] }}">
                            <button class="btn-sm" type="submit">Gravar definitiva</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('escalas.nova-versao') }}">
                            @csrf
                            <input type="hidden" name="ano" value="{{ $filters['ano'] }}">
                            <input type="hidden" name="mes" value="{{ $filters['mes'] }}">
                            <button class="btn-sm" type="submit">Criar nova versão</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        <section class="card">
            <div style="display:flex; justify-content:flex-end; align-items:center; margin-bottom: 10px;">
                <h2 style="margin:0 auto 0 0; font-size:0.98rem;">Resultado</h2>
                @if ($canManageEscalas)
                    <div class="escala-actions no-print">
                        <button class="btn-sm" type="button" data-open-modal="modal-add-dia">+ Adicionar dia</button>
                        <button class="btn-sm" type="button" data-open-modal="modal-add-plantao-func">+ Atribuir plantão</button>
                        <button class="btn-sm" type="button" data-open-modal="modal-add-plantao-ext">+ Novo plantão externo</button>
                    </div>
                @endif
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Dia</th>
                        <th>Escrivão</th>
                        <th>Operacional</th>
                        <th>Fechar</th>
                        <th>Delegada</th>
                        <th>Plantão externo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($escalaLinhas as $row)
                        <tr class="{{ ($row['display_mode'] ?? 'normal') === 'holiday' ? 'scale-row-holiday' : '' }}">
                            <td>{{ $row['date_label'] }}</td>
                            <td>{{ $row['day_label'] }}</td>

                            @if (($row['source'] ?? 'php') === 'php' && ! empty($row['is_weekend']))
                                <td colspan="4" style="text-align:center; color:#999;">Fim de semana</td>
                            @elseif (($row['display_mode'] ?? 'normal') === 'holiday')
                                <td colspan="4" class="scale-holiday-marker" style="text-align:center;">FERIADO</td>
                            @elseif (($row['display_mode'] ?? 'normal') === 'weekend')
                                <td colspan="4" style="text-align:center; color:#999;">Fim de semana</td>
                            @else
                                @php
                                    $canEditRow = ($row['source'] ?? 'php') === 'php' && empty($row['is_fechada']) && ! empty($row['id']);
                                @endphp
                                <td>
                                    @if ($canManageEscalas)
                                        @if ($canEditRow)
                                            <form class="scale-edit-form" method="POST" action="{{ route('escalas.dias.update', $row['id']) }}">
                                                @csrf
                                                <input type="hidden" name="_method" value="PATCH">
                                                <input type="hidden" name="campo" value="escrivao">
                                                <input type="hidden" name="valor" value="{{ $row['escrivao'] }}">
                                                <select name="valor" required onchange="this.form.valor.value=this.value;">
                                                    <option value="">—</option>
                                                    @if ($row['escrivao'] && ! $funcionarioOpcoes->contains($row['escrivao']))
                                                        <option value="{{ $row['escrivao'] }}" selected>{{ $row['escrivao'] }}</option>
                                                    @endif
                                                    @foreach ($funcionarioOpcoes as $nomeOpcao)
                                                        <option value="{{ $nomeOpcao }}" @selected($row['escrivao'] === $nomeOpcao)>{{ $nomeOpcao }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit">OK</button>
                                            </form>
                                        @else
                                            {{ $row['escrivao'] ?: '—' }}
                                        @endif
                                    @else
                                        {{ $row['escrivao'] ?: '—' }}
                                    @endif
                                </td>
                                <td>
                                    @if ($canManageEscalas)
                                        @if ($canEditRow)
                                            <form class="scale-edit-form" method="POST" action="{{ route('escalas.dias.update', $row['id']) }}">
                                                @csrf
                                                <input type="hidden" name="_method" value="PATCH">
                                                <input type="hidden" name="campo" value="operacional">
                                                <input type="hidden" name="valor" value="{{ $row['operacional'] }}">
                                                <select name="valor" required onchange="this.form.valor.value=this.value;">
                                                    <option value="">—</option>
                                                    @if ($row['operacional'] && ! $funcionarioOpcoes->contains($row['operacional']))
                                                        <option value="{{ $row['operacional'] }}" selected>{{ $row['operacional'] }}</option>
                                                    @endif
                                                    @foreach ($funcionarioOpcoes as $nomeOpcao)
                                                        <option value="{{ $nomeOpcao }}" @selected($row['operacional'] === $nomeOpcao)>{{ $nomeOpcao }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit">OK</button>
                                            </form>
                                        @else
                                            {{ $row['operacional'] ?: '—' }}
                                        @endif
                                    @else
                                        {{ $row['operacional'] ?: '—' }}
                                    @endif
                                </td>
                                <td>
                                    @if ($canManageEscalas)
                                        @if ($canEditRow)
                                            @php
                                                $fecharOpcoes = collect([$row['escrivao'] ?? '', $row['operacional'] ?? ''])
                                                    ->filter(fn ($nome) => trim((string) $nome) !== '')
                                                    ->unique()
                                                    ->values();
                                            @endphp
                                            <form class="scale-edit-form" method="POST" action="{{ route('escalas.dias.update', $row['id']) }}">
                                                @csrf
                                                <input type="hidden" name="_method" value="PATCH">
                                                <input type="hidden" name="campo" value="fechar_nome">
                                                <input type="hidden" name="valor" value="{{ $row['fechar'] }}">
                                                <select name="valor" required onchange="this.form.valor.value=this.value;">
                                                    <option value="">—</option>
                                                    @if ($row['fechar'] && ! $fecharOpcoes->contains($row['fechar']))
                                                        <option value="{{ $row['fechar'] }}" selected>{{ $row['fechar'] }}</option>
                                                    @endif
                                                    @foreach ($fecharOpcoes as $nomeOpcao)
                                                        <option value="{{ $nomeOpcao }}" @selected($row['fechar'] === $nomeOpcao)>{{ $nomeOpcao }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit">OK</button>
                                            </form>
                                        @else
                                            {{ $row['fechar'] ?: '—' }}
                                        @endif
                                    @else
                                        {{ $row['fechar'] ?: '—' }}
                                    @endif
                                </td>
                                <td>
                                    @if ($canManageEscalas)
                                        @if ($canEditRow)
                                            <form class="scale-edit-form" method="POST" action="{{ route('escalas.dias.update', $row['id']) }}">
                                                @csrf
                                                <input type="hidden" name="_method" value="PATCH">
                                                <input type="hidden" name="campo" value="delegada">
                                                <input type="hidden" name="valor" value="{{ $row['delegada'] }}">
                                                <select name="valor" required onchange="this.form.valor.value=this.value;">
                                                    <option value="">—</option>
                                                    @if ($row['delegada'] && ! $delegadosExternos->contains('nome_simplificado', $row['delegada']) && ! $delegadosExternos->contains('nome_completo', $row['delegada']))
                                                        <option value="{{ $row['delegada'] }}" selected>{{ $row['delegada'] }}</option>
                                                    @endif
                                                    @foreach ($delegadosExternos as $delegado)
                                                        <option value="{{ $delegado->nome_simplificado ?: $delegado->nome_completo }}" @selected($row['delegada'] === ($delegado->nome_simplificado ?: $delegado->nome_completo))>
                                                            {{ $delegado->nome_simplificado ?: $delegado->nome_completo }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit">OK</button>
                                            </form>
                                        @else
                                            {{ $row['delegada'] ?: '—' }}
                                        @endif
                                    @else
                                        {{ $row['delegada'] ?: '—' }}
                                    @endif
                                </td>
                            @endif

                            <td class="plantao-cell">
                                @if (! empty($row['plantao_items']))
                                    {{ implode(', ', $row['plantao_items']) }}
                                @else
                                    {{ $row['plantao_externo'] ?: '—' }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">Nenhuma linha de escala encontrada para o período selecionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

    @if ($canManageEscalas)
    <div id="modal-add-dia" class="grom-overlay">
        <div class="card" style="width:480px; max-width:96vw;">
            <h2 style="margin-top:0;">Adicionar dia à escala</h2>
            <form method="POST" action="{{ route('escalas.dias.store') }}">
                @csrf
                <input type="hidden" name="versao" value="{{ $phpVersao ?? 1 }}">
                <input type="hidden" name="ano" value="{{ $filters['ano'] }}">
                <input type="hidden" name="mes" value="{{ $filters['mes'] }}">
                <div class="grid" style="grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="field"><label>Data</label><input type="date" name="data" required value="{{ now()->format('Y-m-') }}01"></div>
                    <div class="field"><label>Escrivão</label><input type="text" name="escrivao" maxlength="100"></div>
                    <div class="field"><label>Operacional</label><input type="text" name="operacional" maxlength="100"></div>
                    <div class="field"><label>Fechar</label><input type="text" name="fechar_nome" maxlength="100"></div>
                    <div class="field"><label>Delegada</label><input type="text" name="delegada" maxlength="100"></div>
                    <div class="field"><label>Plantão externo (texto)</label><input type="text" name="plantao_externo" maxlength="200"></div>
                </div>
                <div class="actions" style="margin-top:14px; justify-content:flex-end;">
                    <button type="button" data-close-modal="modal-add-dia">Cancelar</button>
                    <button type="submit">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-add-plantao-func" class="grom-overlay">
        <div class="card" style="width:560px; max-width:96vw;">
            <h2 style="margin-top:0;">Atribuir plantão a funcionário</h2>
            <form method="POST" action="{{ route('escalas.plantoes-funcionarios.store') }}" id="plantao-func-form">
                @csrf
                <div class="field"><label>Funcionário (apenas policiais civis)</label>
                    <select name="funcionario_id" required>
                        <option value="">Selecionar...</option>
                        @foreach ($phpFuncionarios as $funcionario)
                            @if (!($funcionario->is_delegado_externo))
                                <option value="{{ $funcionario->id }}">{{ $funcionario->short_name ?? $funcionario->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="field"><label>Plantão externo</label>
                    <select name="plantao_externo_id" required>
                        <option value="">Selecionar...</option>
                        @foreach ($catalogo as $pe)
                            <option value="{{ $pe->id }}">{{ $pe->sigla }} — {{ $pe->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Dias</label>
                    @php
                        $calendarStart = \Carbon\Carbon::create($filters['ano'], $filters['mes'], 1);
                        $calendarDays = $calendarStart->daysInMonth;
                        $firstWeekday = $calendarStart->dayOfWeek;
                        $weekLabels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                    @endphp
                    <div class="plantao-calendar" data-plantao-calendar>
                        @foreach ($weekLabels as $weekLabel)
                            <div class="plantao-calendar-head">{{ $weekLabel }}</div>
                        @endforeach
                        @for ($blank = 0; $blank < $firstWeekday; $blank++)
                            <div></div>
                        @endfor
                        @for ($day = 1; $day <= $calendarDays; $day++)
                            @php($dateValue = \Carbon\Carbon::create($filters['ano'], $filters['mes'], $day)->toDateString())
                            <button type="button" class="plantao-day" data-plantao-date="{{ $dateValue }}">{{ $day }}</button>
                        @endfor
                    </div>
                    <div id="plantao-selected-dates" class="plantao-selected-list">Nenhum dia selecionado.</div>
                    <div id="plantao-date-inputs"></div>
                </div>
                <div class="actions" style="margin-top:14px; justify-content:flex-end;">
                    <button type="button" data-close-modal="modal-add-plantao-func">Cancelar</button>
                    <button type="submit">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-add-plantao-ext" class="grom-overlay">
        <div class="card" style="width:440px; max-width:96vw;">
            <h2 style="margin-top:0;">Novo plantão externo</h2>
            <form method="POST" action="{{ route('escalas.plantoes-externos.store') }}">
                @csrf
                <div class="grid" style="grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="field"><label>Sigla *</label><input type="text" name="sigla" required maxlength="20"></div>
                    <div class="field"><label>Nome *</label><input type="text" name="nome" required maxlength="120"></div>
                    <div class="field"><label>Unidade</label><input type="text" name="unidade" maxlength="100"></div>
                    <div class="field"><label>Regra</label><input type="text" name="regra" maxlength="200"></div>
                    <div class="field" style="grid-column:1/-1;"><label>Observação</label><textarea name="observacao" maxlength="400" rows="2"></textarea></div>
                </div>
                <div class="actions" style="margin-top:14px; justify-content:flex-end;">
                    <button type="button" data-close-modal="modal-add-plantao-ext">Cancelar</button>
                    <button type="submit">Salvar</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <script>
        document.querySelectorAll('[data-open-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = button.getAttribute('data-open-modal');
                var modal = id ? document.getElementById(id) : null;
                if (modal) {
                    modal.style.display = 'flex';
                }
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = button.getAttribute('data-close-modal');
                var modal = id ? document.getElementById(id) : null;
                if (modal) {
                    modal.style.display = 'none';
                }
            });
        });

        (function () {
            var form = document.getElementById('plantao-func-form');
            var calendar = document.querySelector('[data-plantao-calendar]');
            var inputs = document.getElementById('plantao-date-inputs');
            var selectedList = document.getElementById('plantao-selected-dates');
            var selected = [];

            function formatDate(iso) {
                var parts = iso.split('-');
                return parts.length === 3 ? parts[2] + '/' + parts[1] : iso;
            }

            function renderSelected() {
                inputs.innerHTML = '';
                selected.sort();

                selected.forEach(function (date) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'datas[]';
                    input.value = date;
                    inputs.appendChild(input);
                });

                selectedList.textContent = selected.length
                    ? selected.map(formatDate).join(', ')
                    : 'Nenhum dia selecionado.';
            }

            if (calendar) {
                calendar.querySelectorAll('[data-plantao-date]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var date = button.getAttribute('data-plantao-date');
                        var index = selected.indexOf(date);

                        if (index >= 0) {
                            selected.splice(index, 1);
                            button.classList.remove('is-selected');
                        } else {
                            selected.push(date);
                            button.classList.add('is-selected');
                        }

                        renderSelected();
                    });
                });
            }

            if (form) {
                form.addEventListener('submit', function (event) {
                    if (selected.length > 0) {
                        return;
                    }

                    event.preventDefault();
                    selectedList.textContent = 'Selecione pelo menos um dia.';
                });
            }
        }());
    </script>
@endsection
