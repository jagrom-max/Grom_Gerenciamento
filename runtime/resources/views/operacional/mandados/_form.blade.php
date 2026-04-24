{{--
    Partial de formulário de mandado.
    Usado tanto no modal de cadastro quanto no de edição.
    Variáveis esperadas: $tiposSigla, $procedimentos, $cumprido_por, $regimes
    Passadas automaticamente pelo controller via view().
--}}
@php $isEdit = isset($edit) && $edit; @endphp

<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">

    {{-- Tipo (sigla) --}}
    <div style="grid-column: 1 / -1;">
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Tipo de Mandado <span style="color:#c0392b">*</span></label>
        <select name="tipo_sigla" required style="width:100%;">
            <option value="">— Selecione —</option>
            @foreach ($tiposSigla as $sigla => $descricao)
                <option value="{{ $sigla }}">{{ $sigla }} — {{ $descricao }}</option>
            @endforeach
        </select>
    </div>

    {{-- Nome --}}
    <div style="grid-column: 1 / -1;">
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Nome do Alvo <span style="color:#c0392b">*</span></label>
        <input type="text" name="nome" required maxlength="255" style="width:100%;" placeholder="Nome completo do investigado / réu">
    </div>

    {{-- CPF --}}
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">CPF</label>
        <input type="text" name="cpf" maxlength="11" pattern="\d{11}" style="width:100%;" placeholder="Somente dígitos (11)" inputmode="numeric">
    </div>

    {{-- RG --}}
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">RG</label>
        <input type="text" name="rg" maxlength="30" style="width:100%;" placeholder="Número do RG">
    </div>

    {{-- CNJ --}}
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Nº CNJ</label>
        <input type="text" name="cnj_numero" maxlength="30" style="width:100%;" placeholder="0000000-00.0000.0.00.0000">
    </div>

    {{-- Vara --}}
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Vara / Comarca</label>
        <input type="text" name="vara" maxlength="120" style="width:100%;" placeholder="Ex.: 1ª Vara Criminal — Rio Claro">
    </div>

    {{-- Emissão --}}
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Data de Emissão <span style="color:#c0392b">*</span></label>
        <input type="date" name="data_emissao" required style="width:100%;">
    </div>

    {{-- Validade --}}
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Validade <span style="color:#c0392b">*</span></label>
        <input type="date" name="validade" required style="width:100%;">
    </div>

    {{-- ─── Tipificação penal ─── --}}
    <div style="grid-column: 1 / -1; border-top:1px solid var(--color-border,#ddd); padding-top:14px; margin-top:4px;">
        <label style="display:block; font-size:.85rem; font-weight:600; margin-bottom:8px;">Tipificação Penal</label>

        {{-- Primeira tipificação --}}
        <div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:10px; margin-bottom:8px;">
            <div>
                <label style="display:block; font-size:.8rem; margin-bottom:3px;">Lei / Norma</label>
                <select name="tipificacao_penal" style="width:100%;">
                    <option value="">— Não informado —</option>
                    @foreach ($leis as $codigo => $descricao)
                        <option value="{{ $codigo }}">{{ $descricao }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block; font-size:.8rem; margin-bottom:3px;">Art.</label>
                <input type="text" name="artigo" maxlength="30" style="width:100%;" placeholder="Ex.: 157">
            </div>
            <div>
                <label style="display:block; font-size:.8rem; margin-bottom:3px;">§ / Inciso</label>
                <input type="text" name="paragrafo" maxlength="30" style="width:100%;" placeholder="Ex.: § 2º, II">
            </div>
        </div>

        {{-- Tipificações extra (dinâmico) --}}
        <div id="{{ $isEdit ? 'edit' : 'cad' }}-extras-container" style="margin-bottom:6px;"></div>

        <button type="button"
                onclick="addTipExtra('{{ $isEdit ? 'edit' : 'cad' }}')"
                style="font-size:.8rem; padding:4px 10px; background:var(--color-primary,#2c5282); color:#fff; border:none; border-radius:4px; cursor:pointer; margin-top:2px;">
            + Adicionar outra tipificação
        </button>

        {{-- Campo hidden para JSONificar o array de extras --}}
        <input type="hidden" name="tipificacoes_extra" id="{{ $isEdit ? 'edit' : 'cad' }}-tipificacoes-json" value="">
    </div>

    {{-- Pena --}}
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Pena — Anos</label>
        <input type="number" name="pena_anos" min="0" max="999" style="width:100%;" placeholder="0">
    </div>
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Pena — Meses</label>
        <input type="number" name="pena_meses" min="0" max="11" style="width:100%;" placeholder="0">
    </div>
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Pena — Dias</label>
        <input type="number" name="pena_dias" min="0" max="365" style="width:100%;" placeholder="0">
    </div>

    {{-- Regime --}}
    <div>
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Regime</label>
        <select name="regime" style="width:100%;">
            <option value="">— Não informado —</option>
            @foreach ($regimes as $r)
                <option value="{{ $r }}">{{ $r }}</option>
            @endforeach
        </select>
    </div>

    {{-- Procedimento --}}
    <div style="grid-column: 1 / -1; border-top:1px solid var(--color-border,#ddd); padding-top:14px; margin-top:4px;">
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Procedimento <span style="color:#c0392b">*</span></label>
        <select name="procedimento" required style="width:100%;" onchange="toggleCumprimento(this)">
            @foreach ($procedimentos as $p)
                <option value="{{ $p }}">{{ $p }}</option>
            @endforeach
        </select>
    </div>

    {{-- Campos de cumprimento (mostrados só quando procedimento = Cumprido) --}}
    <div id="{{ $isEdit ? 'edit' : 'cad' }}-wrap-cumprimento" style="grid-column: 1 / -1; display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; display:none;">
        <div>
            <label style="display:block; font-size:.85rem; margin-bottom:4px;">Cumprido por</label>
            <select name="cumprido_por" style="width:100%;">
                <option value="">— Selecione —</option>
                @foreach ($cumprido_por as $cp)
                    <option value="{{ $cp }}">{{ $cp }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="display:block; font-size:.85rem; margin-bottom:4px;">Data de Cumprimento</label>
            <input type="date" name="data_cumprimento" style="width:100%;">
        </div>
        <div>
            <label style="display:block; font-size:.85rem; margin-bottom:4px;">Nº B.O.</label>
            <input type="text" name="bo_numero" maxlength="20" style="width:100%;" placeholder="Ex.: PM0123/2026">
        </div>
    </div>

    {{-- Observações --}}
    <div style="grid-column: 1 / -1;">
        <label style="display:block; font-size:.85rem; margin-bottom:4px;">Observações</label>
        <textarea name="observacoes" rows="3" maxlength="3000" style="width:100%;" placeholder="Informações complementares…"></textarea>
    </div>

</div>

{{-- Erros de validação --}}
@if ($errors->any())
    <ul style="color:#c0392b; margin-top:16px; padding-left:20px;">
        @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
@endif

@once
<script>
// ─── toggleCumprimento ───────────────────────────────────────────────────────
function toggleCumprimento(sel) {
    const wrap = sel.closest('form').querySelector('[id$="-wrap-cumprimento"]');
    if (wrap) wrap.style.display = sel.value === 'Cumprido' ? 'grid' : 'none';
}
// Inicializa no carregamento (edição)
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[name="procedimento"]').forEach(function (sel) {
        toggleCumprimento(sel);
    });
});

// ─── Tipificações extras ─────────────────────────────────────────────────────
const _LEIS_OPTIONS = @json(array_map(fn($k, $v) => ['val' => $k, 'lbl' => $v], array_keys($leis), array_values($leis)));

function addTipExtra(prefix, lei, artigo, paragrafo) {
    const container = document.getElementById(prefix + '-extras-container');
    if (!container) return;
    const idx = container.children.length;

    const row = document.createElement('div');
    row.style.cssText = 'display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:10px; margin-bottom:6px; align-items:end;';
    row.dataset.idx = idx;

    let opts = '<option value="">— Não informado —</option>';
    _LEIS_OPTIONS.forEach(function(o) {
        const sel = (o.val === lei) ? ' selected' : '';
        opts += '<option value="' + o.val + '"' + sel + '>' + o.lbl + '</option>';
    });

    row.innerHTML =
        '<div><label style="display:block;font-size:.8rem;margin-bottom:3px;">Lei</label>' +
        '<select class="extra-lei" style="width:100%;">' + opts + '</select></div>' +
        '<div><label style="display:block;font-size:.8rem;margin-bottom:3px;">Art.</label>' +
        '<input class="extra-art" type="text" maxlength="30" style="width:100%;" placeholder="Ex.: 129" value="' + (artigo || '') + '"></div>' +
        '<div><label style="display:block;font-size:.8rem;margin-bottom:3px;">§ / Inciso</label>' +
        '<input class="extra-par" type="text" maxlength="30" style="width:100%;" placeholder="Ex.: § 3º" value="' + (paragrafo || '') + '"></div>' +
        '<div><button type="button" onclick="removeTipExtra(this)" ' +
        'style="padding:6px 10px;background:#c0392b;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:.85rem;">✕</button></div>';

    container.appendChild(row);
    syncTipExtrasJson(prefix);
}

function removeTipExtra(btn) {
    const row = btn.closest('[data-idx]');
    const container = row.parentElement;
    const prefix = container.id.replace('-extras-container', '');
    container.removeChild(row);
    syncTipExtrasJson(prefix);
}

function syncTipExtrasJson(prefix) {
    const container = document.getElementById(prefix + '-extras-container');
    const hidden = document.getElementById(prefix + '-tipificacoes-json');
    if (!container || !hidden) return;
    const rows = container.querySelectorAll('[data-idx]');
    const arr = [];
    rows.forEach(function(row) {
        const lei = row.querySelector('.extra-lei')?.value || '';
        const art = row.querySelector('.extra-art')?.value || '';
        const par = row.querySelector('.extra-par')?.value || '';
        if (lei || art) arr.push({ lei: lei, artigo: art, paragrafo: par });
    });
    hidden.value = arr.length ? JSON.stringify(arr) : '';
}

// Carrega extras ao editar (chamado pelo index.blade)
function loadTipExtras(prefix, extrasJson) {
    const container = document.getElementById(prefix + '-extras-container');
    if (!container) return;
    container.innerHTML = '';
    if (!extrasJson) return;
    let arr;
    try { arr = JSON.parse(extrasJson); } catch(e) { return; }
    if (!Array.isArray(arr)) return;
    arr.forEach(function(e) { addTipExtra(prefix, e.lei, e.artigo, e.paragrafo); });
}

// Garante que o JSON é sincronizado antes do submit
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form').forEach(function(frm) {
        frm.addEventListener('change', function() {
            ['cad', 'edit'].forEach(function(pfx) { syncTipExtrasJson(pfx); });
        });
        frm.addEventListener('submit', function() {
            ['cad', 'edit'].forEach(function(pfx) { syncTipExtrasJson(pfx); });
        });
    });
});
</script>
@endonce
