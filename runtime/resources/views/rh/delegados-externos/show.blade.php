@extends('layouts.app')

@section('title', $delegadoExterno->name . ' | Delegados Externos | RH | Grom.Seg')

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
    .rh-perfil-name  { font-size: 1.05rem; font-weight: 600; margin: 0 0 2px; }
    .rh-perfil-sub   { font-size: 0.82rem; color: var(--ink-soft, #666); margin: 0; }
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
    .rh-add-form {
        background: var(--surface-alt, #f7f8fa);
        border: 1px solid var(--border, #e0e0e0);
        border-radius: 6px;
        padding: 14px 16px;
        margin-top: 14px;
    }
    .rh-add-form legend { font-size: 0.82rem; font-weight: 600; color: var(--ink-soft, #666); margin-bottom: 10px; display: block; }
    .rh-form-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
    .rh-form-row .field { margin: 0; min-width: 150px; flex: 1; }
    .rh-form-row .field label { font-size: 0.79rem; }
    .rh-form-row .field input, .rh-form-row .field select, .rh-form-row .field textarea { font-size: 0.83rem; }
    .rh-periodo-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 8px;
        margin-bottom: 14px;
    }
    .rh-periodo-summary-card {
        background: var(--surface-alt, #f7f8fa);
        border: 1px solid var(--border, #e0e0e0);
        border-radius: 6px;
        padding: 10px 12px;
    }
    .rh-periodo-item {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 8px 12px;
        align-items: start;
        padding: 10px 0;
        border-bottom: 1px solid var(--border, #eee);
        font-size: 0.84rem;
    }
    .rh-periodo-item:last-child { border-bottom: none; }
    .rh-periodo-period  { font-size: 0.79rem; color: var(--ink-soft, #777); white-space: nowrap; }
    .rh-periodo-motivo  { font-weight: 500; }
    .rh-periodo-notes   { font-size: 0.78rem; color: var(--ink-soft, #888); margin-top: 2px; }
</style>

<div class="section-head" style="align-items:flex-start;">
    <div class="rh-perfil-header" style="margin-bottom:0; flex:1;">
        <div class="rh-perfil-avatar">
            {{ mb_strtoupper(mb_substr($delegadoExterno->name, 0, 2)) }}
        </div>
        <div style="flex:1;">
            <p class="rh-perfil-name">{{ $delegadoExterno->name }}</p>
            <p class="rh-perfil-sub">{{ $delegadoExterno->role_title }} · {{ $delegadoExterno->origin_unit }}</p>
            <div class="rh-perfil-badges">
                <span class="tag {{ $delegadoExterno->statusTone() }}">{{ $delegadoExterno->statusLabel() }}</span>
                @if ($currentPeriodo)
                    <span class="tag good">Substituto DDM em vigor</span>
                @endif
                @if ($delegadoExterno->registration_code)
                    <span class="tag" style="background:transparent; color:var(--ink-soft,#777); border:1px solid var(--border,#ddd); font-size:0.76rem; padding:2px 8px;">
                        {{ $delegadoExterno->registration_code }}
                    </span>
                @endif
            </div>
        </div>
    </div>
    <div class="actions" style="flex-shrink:0;">
        <a href="{{ route('rh.index') }}#rh-delegados-externos" class="btn secondary">← Delegados Externos</a>
        @if (auth()->user()->hasPermission('rh.manage'))
            <button type="button" onclick="document.getElementById('edit-delegado-modal').showModal()">Editar dados</button>
        @endif
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

<div style="display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1.4fr); gap:18px; margin-top:20px; align-items:start;">

    {{-- ── Coluna esquerda: dados cadastrais ──────────────────── --}}
    <div>
        <section class="card" style="padding:16px 18px;">
            <p class="rh-section-title">Dados cadastrais</p>
            <dl class="rh-dl">
                <dt>Código</dt>
                <dd>{{ $delegadoExterno->registration_code ?: '—' }}</dd>
                <dt>Nome</dt>
                <dd>{{ $delegadoExterno->name }}</dd>
                <dt>Origem</dt>
                <dd>{{ $delegadoExterno->origin_unit }}</dd>
                <dt>Função / Cargo</dt>
                <dd>{{ $delegadoExterno->role_title }}</dd>
                <dt>Contato</dt>
                <dd>{{ $delegadoExterno->contact ?: '—' }}</dd>
                <dt>E-mail</dt>
                <dd>{{ $delegadoExterno->email ?: '—' }}</dd>
                <dt>Vigência — De</dt>
                <dd>{{ $delegadoExterno->start_date?->format('d/m/Y') ?: '—' }}</dd>
                <dt>Vigência — Até</dt>
                <dd>{{ $delegadoExterno->end_date?->format('d/m/Y') ?: 'Indefinido' }}</dd>
                <dt>Status</dt>
                <dd><span class="tag {{ $delegadoExterno->statusTone() }}">{{ $delegadoExterno->statusLabel() }}</span></dd>
            </dl>
            @if ($delegadoExterno->notes)
                <p style="margin:12px 0 0; font-size:0.82rem; color:var(--ink-soft,#777);">
                    <strong style="font-size:0.78rem; font-weight:600; letter-spacing:0.04em; text-transform:uppercase; display:block; margin-bottom:4px;">Observações</strong>
                    {{ $delegadoExterno->notes }}
                </p>
            @endif
        </section>
    </div>

    {{-- ── Coluna direita: períodos como Substituto DDM ───────── --}}
    <div>
        <section class="card" style="padding:16px 18px;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                <p class="rh-section-title" style="margin:0; border:none; padding:0;">
                    Períodos como Substituto DDM
                    <span style="font-weight:400; color:var(--ink-soft,#999); font-size:0.78rem; margin-left:6px;">{{ $delegadoExterno->periodos->count() }} registro(s)</span>
                </p>
                @if (auth()->user()->hasPermission('rh.manage'))
                    <button type="button" style="font-size:0.78rem; padding:3px 12px;"
                        onclick="document.getElementById('add-periodo-form').style.display = document.getElementById('add-periodo-form').style.display === 'none' ? 'block' : 'none'">
                        + Registrar
                    </button>
                @endif
            </div>

            @if (auth()->user()->hasPermission('rh.manage'))
                <div id="add-periodo-form" style="display:none; margin-bottom:14px;">
                    <div class="rh-add-form">
                        <legend>Novo período de substituição</legend>
                        <form method="POST" action="{{ route('rh.delegados-externos.periodos.store', $delegadoExterno) }}" class="js-del-ext-periodo-form">
                            @csrf
                            <div class="rh-form-row">
                                <div class="field" style="margin:0; flex:2; min-width:180px;">
                                    <label>Motivo</label>
                                    <input name="motivo" type="text" required
                                        placeholder="Ex: Férias, Licença Prêmio…"
                                        list="motivo-suggestions"
                                        value="{{ old('motivo') }}">
                                    <datalist id="motivo-suggestions">
                                        <option value="Férias">
                                        <option value="Licença Prêmio">
                                        <option value="Substituição eventual">
                                        <option value="Afastamento para curso">
                                        <option value="Licença médica">
                                    </datalist>
                                </div>
                                <div class="field" style="margin:0; min-width:130px;">
                                    <label>De</label>
                                    <input name="start_date" type="date" required value="{{ old('start_date') }}"
                                        class="del-ext-periodo-start">
                                </div>
                                <div class="field" style="margin:0; min-width:130px;">
                                    <label>Até <span class="muted">(vazio = em aberto)</span></label>
                                    <input name="end_date" type="date" value="{{ old('end_date') }}"
                                        class="del-ext-periodo-end">
                                </div>
                            </div>
                            <div style="margin:8px 0 0;">
                                <span class="muted del-ext-periodo-counter" style="font-size:0.79rem;">Selecione De e Até para contar os dias.</span>
                            </div>
                            <div class="field" style="margin:8px 0 0;">
                                <label>Anotações</label>
                                <textarea name="notes" rows="2" style="font-size:0.83rem;" placeholder="Observações opcionais…">{{ old('notes') }}</textarea>
                            </div>
                            <div style="margin-top:10px; display:flex; gap:8px;">
                                <button type="submit" style="font-size:0.82rem; padding:5px 16px;">Salvar</button>
                                <button type="button" class="secondary" style="font-size:0.82rem; padding:5px 12px;"
                                    onclick="document.getElementById('add-periodo-form').style.display='none'">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Cards de resumo --}}
            <div class="rh-periodo-summary">
                <div class="rh-periodo-summary-card">
                    <div class="muted" style="font-size:0.76rem; margin-bottom:3px;">Total contabilizado</div>
                    <strong>{{ $periodosSummary['total_dias'] }} dias</strong><br>
                    <span class="muted" style="font-size:0.76rem;">{{ $periodosSummary['total_registros'] }} registro(s)</span>
                </div>
                <div class="rh-periodo-summary-card">
                    <div class="muted" style="font-size:0.76rem; margin-bottom:3px;">Atualmente em vigor</div>
                    <strong>{{ $periodosSummary['em_vigor'] }} período(s)</strong><br>
                    @if ($periodosSummary['agendados'] > 0)
                        <span class="muted" style="font-size:0.76rem;">{{ $periodosSummary['agendados'] }} agendado(s)</span>
                    @else
                        <span class="muted" style="font-size:0.76rem;">Nenhum agendado</span>
                    @endif
                </div>
                <div class="rh-periodo-summary-card">
                    <div class="muted" style="font-size:0.76rem; margin-bottom:3px;">Períodos em aberto</div>
                    <strong>{{ $periodosSummary['em_aberto'] }} registro(s)</strong><br>
                    <span class="muted" style="font-size:0.76rem;">Dias só entram no total quando há término.</span>
                </div>
            </div>

            {{-- Histórico de períodos --}}
            @forelse ($delegadoExterno->periodos as $periodo)
                <?php
                    $pLabel = $periodo->statusLabel();
                    $pTone  = match ($pLabel) {
                        'Em vigor'  => 'good',
                        'Agendado'  => 'info',
                        default     => '',
                    };
                ?>
                <div class="rh-periodo-item">
                    <div>
                        <span class="tag {{ $pTone }}"
                            style="{{ !$pTone ? 'background:transparent; border:1px solid var(--border,#ddd); color:var(--ink-soft,#888);' : '' }} font-size:0.74rem; padding:2px 7px;">
                            {{ $pLabel }}
                        </span>
                    </div>
                    <div>
                        <div class="rh-periodo-motivo">{{ $periodo->motivo }}</div>
                        <div class="rh-periodo-period">
                            {{ $periodo->start_date?->format('d/m/Y') }}
                            @if ($periodo->end_date)
                                → {{ $periodo->end_date->format('d/m/Y') }}
                                ({{ $periodo->durationInDays() }} dias)
                            @else
                                → <em>em aberto</em>
                            @endif
                        </div>
                        @if ($periodo->notes)
                            <div class="rh-periodo-notes">{{ $periodo->notes }}</div>
                        @endif
                    </div>
                    @if (auth()->user()->hasPermission('rh.manage'))
                        <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-end;">
                            <form method="POST" action="{{ route('rh.delegados-externos.periodos.toggle-active', [$delegadoExterno, $periodo]) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="secondary" style="font-size:0.74rem; padding:2px 8px;">
                                    {{ $periodo->is_active ? 'Inativar' : 'Reativar' }}
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <p style="color:var(--ink-soft,#999); font-size:0.83rem; margin:8px 0 0;">Nenhum período de substituição registrado.</p>
            @endforelse
        </section>
    </div>
</div>

{{-- Modal: editar dados do delegado externo --}}
@if (auth()->user()->hasPermission('rh.manage'))
    <dialog id="edit-delegado-modal" class="grom-modal grom-modal--lg">
        <div class="grom-modal-card">
            <div class="grom-modal-head">
                <strong>Editar — {{ $delegadoExterno->name }}</strong>
                <button type="button" class="secondary grom-modal-close"
                    onclick="document.getElementById('edit-delegado-modal').close()">×</button>
            </div>
            <form method="POST" action="{{ route('rh.delegados-externos.update', $delegadoExterno) }}" class="form-grid">
                @csrf @method('PUT')
                <div class="field">
                    <label>Código</label>
                    <input name="registration_code" type="text" value="{{ old('registration_code', $delegadoExterno->registration_code) }}">
                </div>
                <div class="field">
                    <label>Nome <span style="color:red;">*</span></label>
                    <input name="name" type="text" required value="{{ old('name', $delegadoExterno->name) }}">
                </div>
                <div class="field">
                    <label>Unidade de origem <span style="color:red;">*</span></label>
                    <input name="origin_unit" type="text" required value="{{ old('origin_unit', $delegadoExterno->origin_unit) }}">
                </div>
                <div class="field">
                    <label>Função / Cargo <span style="color:red;">*</span></label>
                    <input name="role_title" type="text" required value="{{ old('role_title', $delegadoExterno->role_title) }}">
                </div>
                <div class="field">
                    <label>Contato</label>
                    <input name="contact" type="text" value="{{ old('contact', $delegadoExterno->contact) }}">
                </div>
                <div class="field">
                    <label>E-mail</label>
                    <input name="email" type="email" value="{{ old('email', $delegadoExterno->email) }}">
                </div>
                <div class="field">
                    <label>Início da vigência <span style="color:red;">*</span></label>
                    <input name="start_date" type="date" required value="{{ old('start_date', $delegadoExterno->start_date?->toDateString()) }}">
                </div>
                <div class="field">
                    <label>Fim da vigência <span class="muted">(vazio = indefinido)</span></label>
                    <input name="end_date" type="date" value="{{ old('end_date', $delegadoExterno->end_date?->toDateString()) }}">
                </div>
                <div class="field" style="grid-column:1/-1;">
                    <label>Observações</label>
                    <textarea name="notes" rows="3">{{ old('notes', $delegadoExterno->notes) }}</textarea>
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="is_active">
                        <option value="1" @selected(old('is_active', $delegadoExterno->is_active ? '1' : '0') == '1')>Ativo</option>
                        <option value="0" @selected(old('is_active', $delegadoExterno->is_active ? '1' : '0') == '0')>Inativo</option>
                    </select>
                </div>
                <div class="actions" style="grid-column:1/-1;">
                    <button type="submit">Salvar alterações</button>
                    <button type="button" class="secondary"
                        onclick="document.getElementById('edit-delegado-modal').close()">Cancelar</button>
                </div>
            </form>
        </div>
    </dialog>
@endif

<script>
(function () {
    function parseDelExtPeriodoDate(s) {
        if (!s) return null;
        const p = s.split('-');
        if (p.length !== 3) return null;
        const d = new Date(parseInt(p[0]), parseInt(p[1]) - 1, parseInt(p[2]));
        return isNaN(d.getTime()) ? null : d;
    }

    function diffInclusiveDays(start, end) {
        const ms = end - start;
        return Math.round(ms / 86400000) + 1;
    }

    function updateDelExtPeriodoCounter(form) {
        const counter = form.querySelector('.del-ext-periodo-counter');
        if (!counter) return;
        const startVal = form.querySelector('.del-ext-periodo-start')?.value;
        const endVal   = form.querySelector('.del-ext-periodo-end')?.value;
        const start    = parseDelExtPeriodoDate(startVal);
        const end      = parseDelExtPeriodoDate(endVal);
        if (!start) { counter.textContent = 'Selecione De e Até para contar os dias.'; return; }
        if (!end)   { counter.textContent = 'Período em aberto — fim não definido.'; return; }
        if (end < start) { counter.textContent = 'A data de término deve ser igual ou posterior ao início.'; return; }
        const days = diffInclusiveDays(start, end);
        counter.textContent = days + ' dia(s) corridos.';
    }

    function bindDelExtPeriodoCounters() {
        document.querySelectorAll('.js-del-ext-periodo-form').forEach(function (form) {
            ['input', 'change'].forEach(function (evt) {
                form.addEventListener(evt, function () { updateDelExtPeriodoCounter(form); });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindDelExtPeriodoCounters);
    } else {
        bindDelExtPeriodoCounters();
    }
})();
</script>
@endsection
