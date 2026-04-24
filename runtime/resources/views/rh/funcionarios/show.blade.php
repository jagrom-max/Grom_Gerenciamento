@extends('layouts.app')

@section('title', $funcionario->name . ' | RH | Grom.Seg')

@section('content')
<style>
    .rh-perfil-header {
        display: flex;
        align-items: flex-start;
        gap: 18px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .rh-perfil-avatar {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: var(--surface-alt, #e8eaf0);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--ink-soft, #666);
        flex-shrink: 0;
    }
    .rh-perfil-name { font-size: 1.05rem; font-weight: 600; margin: 0 0 2px; }
    .rh-perfil-sub { font-size: 0.82rem; color: var(--ink-soft, #666); margin: 0; }
    .rh-perfil-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
    .rh-dl {
        display: grid;
        grid-template-columns: 160px 1fr;
        gap: 4px 12px;
        font-size: 0.84rem;
    }
    .rh-dl dt { color: var(--ink-soft, #777); font-weight: 400; }
    .rh-dl dd { font-weight: 500; margin: 0; }
    .rh-dl dt, .rh-dl dd { padding: 3px 0; border-bottom: 1px solid var(--border, #eee); }
    .rh-section-title {
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--ink-soft, #888);
        margin: 0 0 10px;
        padding-bottom: 5px;
        border-bottom: 1px solid var(--border, #e0e0e0);
    }
    .rh-afastamento-item {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 8px 12px;
        align-items: start;
        padding: 10px 0;
        border-bottom: 1px solid var(--border, #eee);
        font-size: 0.84rem;
    }
    .rh-afastamento-item:last-child { border-bottom: none; }
    .rh-afastamento-period { font-size: 0.79rem; color: var(--ink-soft, #777); white-space: nowrap; }
    .rh-afastamento-reason { font-weight: 500; }
    .rh-afastamento-notes { font-size: 0.78rem; color: var(--ink-soft, #888); margin-top: 2px; }
    .rh-add-form {
        background: var(--surface-alt, #f7f8fa);
        border: 1px solid var(--border, #e0e0e0);
        border-radius: 6px;
        padding: 14px 16px;
        margin-top: 14px;
    }
    .rh-add-form legend {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--ink-soft, #666);
        margin-bottom: 10px;
        display: block;
    }
    .rh-form-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
    .rh-form-row .field { margin: 0; min-width: 150px; flex: 1; }
    .rh-form-row .field label { font-size: 0.79rem; }
    .rh-form-row .field input, .rh-form-row .field select, .rh-form-row .field textarea {
        font-size: 0.83rem;
    }
    .rh-print-bar {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 8px;
    }
    .rh-print-bar select { font-size: 0.80rem; padding: 4px 8px; min-width: 90px; }
    .rh-print-bar button, .rh-print-bar a { font-size: 0.80rem; padding: 4px 12px; }
    .rh-afastamento-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 8px;
        margin-bottom: 12px;
    }
    .rh-afastamento-summary-card {
        background: var(--surface-alt, #f7f8fa);
        border: 1px solid var(--border, #e0e0e0);
        border-radius: 6px;
        padding: 10px 12px;
    }
</style>

<div class="section-head" style="align-items:flex-start;">
    <div class="rh-perfil-header" style="margin-bottom:0; flex:1;">
        <div class="rh-perfil-avatar">
            {{ mb_strtoupper(mb_substr($funcionario->short_name ?: $funcionario->name, 0, 2)) }}
        </div>
        <div style="flex:1;">
            <p class="rh-perfil-name">{{ $funcionario->name }}</p>
            <p class="rh-perfil-sub">{{ $funcionario->cargo?->name ?: 'Cargo não definido' }}{{ $funcionario->sector ? ' · ' . $funcionario->sector : '' }}</p>
            <div class="rh-perfil-badges">
                @if ($currentAfastamento)
                    <span class="tag warn">Afastado — {{ $currentAfastamento->reason }}</span>
                @else
                    <span class="tag {{ $funcionario->is_active ? 'good' : 'warn' }}">
                        {{ $funcionario->is_active ? 'Ativo' : 'Inativo' }}
                    </span>
                @endif
                @if ($funcionario->concorre_escala)
                    <span class="tag good">Concorre à escala</span>
                @endif
                <span class="tag" style="background:transparent; color:var(--ink-soft,#777); border:1px solid var(--border,#ddd); font-size:0.76rem; padding:2px 8px;">
                    Matrícula {{ $funcionario->matricula }}
                </span>
            </div>
        </div>
    </div>
    <div class="actions" style="flex-shrink:0;">
        <a href="{{ route('rh.index') }}" class="btn secondary">← Servidores</a>
        @if (auth()->user()->hasPermission('rh.manage'))
            <button type="button" onclick="document.getElementById('edit-func-modal').showModal()">Editar dados</button>
        @endif
        <a href="{{ route('rh.funcionarios.ficha', $funcionario) }}" class="btn secondary" target="_blank">Imprimir ficha</a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success" style="margin: 10px 0 16px; font-size:0.85rem;">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-error" style="margin: 10px 0 16px; font-size:0.85rem;">
        @foreach ($errors->all() as $err) <div>{{ $err }}</div> @endforeach
    </div>
@endif

<div style="display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1.3fr); gap:18px; margin-top: 20px; align-items:start;">

    {{-- ── Coluna esquerda: dados cadastrais ──────────────────── --}}
    <div>
        <section class="card" style="padding: 16px 18px;">
            <p class="rh-section-title">Dados cadastrais</p>
            <dl class="rh-dl">
                <dt>Nome completo</dt>
                <dd>{{ $funcionario->name }}</dd>
                <dt>Nome simplificado</dt>
                <dd>{{ $funcionario->short_name ?: '—' }}</dd>
                <dt>Matrícula</dt>
                <dd>{{ $funcionario->matricula }}</dd>
                <dt>Cargo</dt>
                <dd>{{ $funcionario->cargo?->name ?: '—' }}</dd>
                <dt>Setor</dt>
                <dd>{{ $funcionario->sector ?: '—' }}</dd>
                <dt>E-mail</dt>
                <dd>{{ $funcionario->email ?: '—' }}</dd>
                <dt>Telefone</dt>
                <dd>{{ $funcionario->phone ?: '—' }}</dd>
                <dt>CPF</dt>
                <dd>{{ $funcionario->cpf ?: '—' }}</dd>
                <dt>RG</dt>
                <dd>{{ $funcionario->rg ?: '—' }}</dd>
                <dt>Nascimento</dt>
                <dd>{{ $funcionario->birth_date?->format('d/m/Y') ?: '—' }}</dd>
                <dt>Admissão</dt>
                <dd>{{ $funcionario->admission_date?->format('d/m/Y') ?: '—' }}</dd>
                <dt>Designação</dt>
                <dd>{{ $funcionario->designation_date?->format('d/m/Y') ?: '—' }}</dd>
                <dt>Saída</dt>
                <dd>{{ $funcionario->departure_date?->format('d/m/Y') ?: '—' }}</dd>
                <dt>Remoção</dt>
                <dd>{{ $funcionario->removal_date?->format('d/m/Y') ?: '—' }}</dd>
                <dt>Concorre escala</dt>
                <dd>{{ $funcionario->concorre_escala ? 'Sim' : 'Não' }}</dd>
            </dl>
            @if ($funcionario->notes)
                <p style="margin: 12px 0 0; font-size: 0.82rem; color: var(--ink-soft, #777);">
                    <strong style="font-size:0.78rem; font-weight:600; letter-spacing:0.04em; text-transform:uppercase; display:block; margin-bottom:4px;">Observações</strong>
                    {{ $funcionario->notes }}
                </p>
            @endif
        </section>

        {{-- Impressão de afastamentos por período --}}
        <section class="card" style="padding: 16px 18px; margin-top: 14px;">
            <p class="rh-section-title">Imprimir afastamentos</p>
            <form method="GET" action="{{ route('rh.afastamentos.relatorio') }}" target="_blank" class="rh-form-row" style="flex-wrap:wrap;">
                <input type="hidden" name="funcionario_id" value="{{ $funcionario->id }}">
                <div class="field" style="margin:0; min-width:80px; flex:1;">
                    <label style="font-size:0.79rem;">Ano</label>
                    <input name="year" type="number" min="2000" max="2099" value="{{ now()->year }}" style="font-size:0.83rem;">
                </div>
                <div class="field" style="margin:0; min-width:100px; flex:1;">
                    <label style="font-size:0.79rem;">Mês (0 = ano inteiro)</label>
                    <select name="month" style="font-size:0.83rem;">
                        <option value="0">Ano inteiro</option>
                        @foreach ([1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'] as $n => $m)
                            <option value="{{ $n }}" @selected($n == now()->month)>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="padding-top:18px;">
                    <button type="submit" class="secondary" style="font-size:0.80rem; padding:5px 14px;">Gerar relatório</button>
                </div>
            </form>
        </section>
    </div>

    {{-- ── Coluna direita: afastamentos ───────────────────────── --}}
    <div>
        <section class="card" style="padding: 16px 18px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <p class="rh-section-title" style="margin:0; border:none; padding:0;">
                    Afastamentos
                    <span style="font-weight:400; color:var(--ink-soft,#999); font-size:0.78rem; margin-left:6px;">{{ $funcionario->afastamentos->count() }} registros</span>
                </p>
                @if (auth()->user()->hasPermission('rh.manage'))
                    <button type="button" style="font-size:0.78rem; padding:3px 12px;"
                        onclick="document.getElementById('add-afas-form').style.display = document.getElementById('add-afas-form').style.display === 'none' ? 'block' : 'none'">
                        + Registrar
                    </button>
                @endif
            </div>

            @if (auth()->user()->hasPermission('rh.manage'))
                <div id="add-afas-form" style="display:none; margin-bottom:14px;">
                    <div class="rh-add-form">
                        <legend>Novo afastamento</legend>
                        <form method="POST" action="{{ route('rh.afastamentos.store') }}" class="js-afastamento-form">
                            @csrf
                            <input type="hidden" name="funcionario_id" value="{{ $funcionario->id }}">
                            <input type="hidden" name="redirect_to_show" value="1">
                            <div class="rh-form-row">
                                <div class="field" style="margin:0; flex:2; min-width:160px;">
                                    <label>Motivo</label>
                                    <input name="reason" type="text" required placeholder="Ex: Férias, Licença médica…" value="{{ old('reason') }}">
                                </div>
                                <div class="field" style="margin:0; min-width:130px;">
                                    <label>Início</label>
                                    <input name="start_date" type="date" required value="{{ old('start_date') }}">
                                </div>
                                <div class="field" style="margin:0; min-width:130px;">
                                    <label>Término <span class="muted">(vazio = indefinido)</span></label>
                                    <input name="end_date" type="date" value="{{ old('end_date') }}">
                                </div>
                            </div>
                            <div style="margin:8px 0 0;">
                                <span class="muted js-afastamento-counter" style="font-size:0.79rem;">Selecione início e fim para contar os dias.</span>
                            </div>
                            <div class="field" style="margin: 8px 0 0;">
                                <label>Anotações</label>
                                <textarea name="notes" rows="2" style="font-size:0.83rem;" placeholder="Observações opcionais…">{{ old('notes') }}</textarea>
                            </div>
                            <div style="margin-top:10px; display:flex; gap:8px;">
                                <button type="submit" style="font-size:0.82rem; padding:5px 16px;">Salvar</button>
                                <button type="button" class="secondary" style="font-size:0.82rem; padding:5px 12px;"
                                    onclick="document.getElementById('add-afas-form').style.display='none'">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <div class="rh-afastamento-summary">
                <div class="rh-afastamento-summary-card">
                    <div class="muted" style="font-size:0.76rem; margin-bottom:3px;">Férias acumuladas</div>
                    <strong>{{ $afastamentosSummary['ferias_dias'] }} dias contabilizados</strong><br>
                    <span class="muted" style="font-size:0.76rem;">{{ $afastamentosSummary['ferias_registros'] }} registro(s)</span>
                </div>
                <div class="rh-afastamento-summary-card">
                    <div class="muted" style="font-size:0.76rem; margin-bottom:3px;">Demais afastamentos</div>
                    <strong>{{ $afastamentosSummary['outros_dias'] }} dias contabilizados</strong><br>
                    <span class="muted" style="font-size:0.76rem;">{{ $afastamentosSummary['outros_registros'] }} registro(s)</span>
                </div>
                <div class="rh-afastamento-summary-card">
                    <div class="muted" style="font-size:0.76rem; margin-bottom:3px;">Períodos em aberto</div>
                    <strong>{{ $afastamentosSummary['registros_em_aberto'] }} registro(s)</strong><br>
                    <span class="muted" style="font-size:0.76rem;">Dias só entram no total quando há término.</span>
                </div>
            </div>

            @forelse ($funcionario->afastamentos as $afas)
                <?php
                    $statusLabel = $afas->statusLabel();
                    $statusTone  = $afas->statusTone();
                    $tone        = match ($statusLabel) {
                        'Em vigor'   => 'warn',
                        'Agendado'   => 'info',
                        'Encerrado'  => '',
                        default      => '',
                    };
                ?>
                <div class="rh-afastamento-item">
                    <div>
                        <span class="tag {{ $tone ?: '' }}" style="{{ !$tone ? 'background:transparent; border:1px solid var(--border,#ddd); color:var(--ink-soft,#888);' : '' }} font-size:0.74rem; padding:2px 7px;">
                            {{ $statusLabel }}
                        </span>
                    </div>
                    <div>
                        <div class="rh-afastamento-reason">{{ $afas->reason }}</div>
                        <div class="rh-afastamento-period">
                            {{ $afas->start_date?->format('d/m/Y') }}
                            @if ($afas->end_date)
                                → {{ $afas->end_date->format('d/m/Y') }}
                                ({{ $afas->durationInDays() }} dias)
                            @else
                                → <em>em aberto</em>
                            @endif
                        </div>
                        @if ($afas->notes)
                            <div class="rh-afastamento-notes">{{ $afas->notes }}</div>
                        @endif
                    </div>
                    @if (auth()->user()->hasPermission('rh.manage'))
                        <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-end;">
                            <button type="button" class="secondary" style="font-size:0.74rem; padding:2px 8px;"
                                onclick="document.getElementById('edit-afas-{{ $afas->id }}').showModal()">Editar</button>
                            <form method="POST" action="{{ route('rh.afastamentos.toggle-active', $afas) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="redirect_to_show" value="1">
                                <button type="submit" class="secondary" style="font-size:0.74rem; padding:2px 8px;">
                                    {{ $afas->is_active ? 'Inativar' : 'Reativar' }}
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                @if (auth()->user()->hasPermission('rh.manage'))
                    <dialog id="edit-afas-{{ $afas->id }}" class="grom-modal">
                        <div class="grom-modal-card">
                            <div class="grom-modal-head">
                                <strong>Editar afastamento</strong>
                                <button type="button" class="secondary grom-modal-close"
                                    onclick="document.getElementById('edit-afas-{{ $afas->id }}').close()">×</button>
                            </div>
                            <form method="POST" action="{{ route('rh.afastamentos.update', $afas) }}" class="form-grid js-afastamento-form" style="gap:10px;">
                                @csrf @method('PUT')
                                <input type="hidden" name="redirect_to_show" value="1">
                                <div class="field">
                                    <label>Motivo</label>
                                    <input name="reason" type="text" required value="{{ old('reason', $afas->reason) }}">
                                </div>
                                <div class="field">
                                    <label>Início</label>
                                    <input name="start_date" type="date" required value="{{ old('start_date', $afas->start_date?->toDateString()) }}">
                                </div>
                                <div class="field">
                                    <label>Término <span class="muted">(vazio = indefinido)</span></label>
                                    <input name="end_date" type="date" value="{{ old('end_date', $afas->end_date?->toDateString()) }}">
                                </div>
                                <div class="field" style="grid-column:1/-1; margin-top:-4px;">
                                    <span class="muted js-afastamento-counter" style="font-size:0.79rem;">Selecione início e fim para contar os dias.</span>
                                </div>
                                <div class="field" style="grid-column:1/-1;">
                                    <label>Anotações</label>
                                    <textarea name="notes" rows="3">{{ old('notes', $afas->notes) }}</textarea>
                                </div>
                                <div class="actions" style="grid-column:1/-1;">
                                    <button type="submit">Salvar</button>
                                    <button type="button" class="secondary"
                                        onclick="document.getElementById('edit-afas-{{ $afas->id }}').close()">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </dialog>
                @endif
            @empty
                <p style="color:var(--ink-soft,#999); font-size:0.83rem; margin: 8px 0 0;">Nenhum afastamento registrado.</p>
            @endforelse
        </section>
    </div>
</div>

{{-- Modal: editar funcionário --}}
@if (auth()->user()->hasPermission('rh.manage'))
    <dialog id="edit-func-modal" class="grom-modal grom-modal--lg">
        <div class="grom-modal-card">
            <div class="grom-modal-head">
                <strong>Editar dados — {{ $funcionario->short_name ?: $funcionario->name }}</strong>
                <button type="button" class="secondary grom-modal-close"
                    onclick="document.getElementById('edit-func-modal').close()">×</button>
            </div>
            <form method="POST" action="{{ route('rh.funcionarios.update', $funcionario) }}" class="form-grid">
                @csrf @method('PUT')
                <div class="field">
                    <label>Nome</label>
                    <input name="name" type="text" required value="{{ old('name', $funcionario->name) }}">
                </div>
                <div class="field">
                    <label>Nome simplificado</label>
                    <input name="short_name" type="text" value="{{ old('short_name', $funcionario->short_name) }}">
                </div>
                <div class="field">
                    <label>E-mail</label>
                    <input name="email" type="email" value="{{ old('email', $funcionario->email) }}">
                </div>
                <div class="field">
                    <label>Cargo</label>
                    <select name="cargo_id" required>
                        @foreach ($cargos as $cargo)
                            <option value="{{ $cargo->id }}" @selected($cargo->id === $funcionario->cargo_id)>
                                {{ $cargo->code }} — {{ $cargo->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Setor</label>
                    <input name="sector" type="text" value="{{ old('sector', $funcionario->sector) }}">
                </div>
                <div class="field">
                    <label>Telefone</label>
                    <input name="phone" type="text" value="{{ old('phone', $funcionario->phone) }}">
                </div>
                <div class="field">
                    <label>CPF</label>
                    <input name="cpf" type="text" value="{{ old('cpf', $funcionario->cpf) }}">
                </div>
                <div class="field">
                    <label>RG</label>
                    <input name="rg" type="text" value="{{ old('rg', $funcionario->rg) }}">
                </div>
                <div class="field">
                    <label>Nascimento</label>
                    <input name="birth_date" type="date" value="{{ old('birth_date', $funcionario->birth_date?->toDateString()) }}">
                </div>
                <div class="field">
                    <label>Admissão</label>
                    <input name="admission_date" type="date" required value="{{ old('admission_date', $funcionario->admission_date?->toDateString()) }}">
                </div>
                <div class="field">
                    <label>Designação</label>
                    <input name="designation_date" type="date" value="{{ old('designation_date', $funcionario->designation_date?->toDateString()) }}">
                </div>
                <div class="field">
                    <label>Saída</label>
                    <input name="departure_date" type="date" value="{{ old('departure_date', $funcionario->departure_date?->toDateString()) }}">
                </div>
                <div class="field">
                    <label>Remoção</label>
                    <input name="removal_date" type="date" value="{{ old('removal_date', $funcionario->removal_date?->toDateString()) }}">
                </div>
                <div class="field" style="display:flex; gap:8px; align-items:center;">
                    <input name="concorre_escala" type="checkbox" value="1" id="ce_edit"
                        @checked(old('concorre_escala', $funcionario->concorre_escala))>
                    <label for="ce_edit" style="margin:0; font-weight:400;">Concorre à escala</label>
                    <input type="hidden" name="concorre_escala" value="0">
                </div>
                <div class="field" style="display:flex; gap:8px; align-items:center;">
                    <input name="is_active" type="checkbox" value="1" id="ia_edit"
                        @checked(old('is_active', $funcionario->is_active))>
                    <label for="ia_edit" style="margin:0; font-weight:400;">Servidor ativo</label>
                    <input type="hidden" name="is_active" value="0">
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label>Observações</label>
                    <textarea name="notes" rows="3">{{ old('notes', $funcionario->notes) }}</textarea>
                </div>
                <div class="actions" style="grid-column:1/-1;">
                    <button type="submit">Salvar alterações</button>
                    <button type="button" class="secondary"
                        onclick="document.getElementById('edit-func-modal').close()">Cancelar</button>
                </div>
            </form>
        </div>
    </dialog>
@endif

@if (session('_old_input.reason') || $errors->has('reason'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('add-afas-form').style.display = 'block';
        });
    </script>
@endif

<script>
    function parseAfastamentoIsoDate(value) {
        if (!value) return null;
        var parts = value.split('-');
        if (parts.length !== 3) return null;
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    }

    function calcAfastamentoDays(startValue, endValue) {
        var start = parseAfastamentoIsoDate(startValue);
        var end = parseAfastamentoIsoDate(endValue);
        if (!start || !end || end < start) return null;
        return Math.round((end - start) / (24 * 60 * 60 * 1000)) + 1;
    }

    function afastamentoCategory(reasonValue) {
        var normalized = (reasonValue || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();

        return normalized.indexOf('ferias') >= 0 ? 'Férias' : 'Demais afastamentos';
    }

    function updateShowAfastamentoCounter(form) {
        var startInput = form.querySelector('input[name="start_date"]');
        var endInput = form.querySelector('input[name="end_date"]');
        var reasonInput = form.querySelector('input[name="reason"]');
        var counter = form.querySelector('.js-afastamento-counter');

        if (!startInput || !endInput || !counter) return;

        if (!startInput.value || !endInput.value) {
            counter.textContent = 'Selecione início e fim para contar os dias.';
            return;
        }

        var totalDays = calcAfastamentoDays(startInput.value, endInput.value);
        if (totalDays === null) {
            counter.textContent = 'Período inválido para contagem.';
            return;
        }

        counter.textContent = afastamentoCategory(reasonInput ? reasonInput.value : '') + ': ' + totalDays + ' dia(s) corridos.';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-afastamento-form').forEach(function (form) {
            ['reason', 'start_date', 'end_date'].forEach(function (name) {
                var input = form.querySelector('[name="' + name + '"]');
                if (!input) return;

                input.addEventListener('input', function () {
                    updateShowAfastamentoCounter(form);
                });
            });

            updateShowAfastamentoCounter(form);
        });
    });
</script>
@endsection
