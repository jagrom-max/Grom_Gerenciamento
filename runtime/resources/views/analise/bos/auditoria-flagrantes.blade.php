@extends('layouts.app')

@section('title', 'Auditoria de Flagrantes | Grom.Seg')

@push('styles')
<style>
.badge-status {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 10px;
    font-size: 0.73rem;
    font-weight: 600;
}
.badge-status.pending   { background:#fef3c7; color:#92400e; }
.badge-status.approved  { background:#d1fae5; color:#065f46; }
.badge-status.corrected { background:#dbeafe; color:#1e40af; }
.badge-status.dismissed { background:#f3f4f6; color:#6b7280; }

.aud-row:hover { background: #fafafa; }
.aud-form { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
.aud-form select, .aud-form input[type=text] {
    padding: 5px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.83rem;
}
.aud-form button {
    padding: 5px 14px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-size: 0.83rem;
    font-weight: 600;
}
.btn-approve  { background:#10b981; color:#fff; }
.btn-correct  { background:#3b82f6; color:#fff; }
.btn-dismiss  { background:#6b7280; color:#fff; }
</style>
@endpush

@section('content')

<div class="section-head">
    <div>
        <h1>Auditoria â€” Flagrantes sem cartÃ³rio</h1>
        <p class="muted" style="margin:6px 0 0;">
            Flagrantes identificados na importaÃ§Ã£o com o campo <em>CartÃ³rio do IP</em> vazio ou ausente.
            Atribua o cartÃ³rio correto ou dispense o registro.
        </p>
    </div>
    <div class="actions">
        <a class="btn secondary" href="{{ route('analise.bos.import') }}">Nova importaÃ§Ã£o</a>
        <a class="btn secondary" href="{{ route('analise.index') }}">â† Painel</a>
    </div>
</div>

{{-- â”€â”€ KPIs â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
<div class="cards" style="margin-bottom:18px;">
    <article class="card" style="text-align:center; padding:14px;">
        <small>Pendentes</small>
        <div style="font-size:1.8rem; font-weight:700; color:#d97706;">{{ number_format($totais->pending ?? 0) }}</div>
    </article>
    <article class="card" style="text-align:center; padding:14px;">
        <small>Aprovados</small>
        <div style="font-size:1.8rem; font-weight:700; color:#059669;">{{ number_format($totais->approved ?? 0) }}</div>
    </article>
    <article class="card" style="text-align:center; padding:14px;">
        <small>Corrigidos</small>
        <div style="font-size:1.8rem; font-weight:700; color:#2563eb;">{{ number_format($totais->corrected ?? 0) }}</div>
    </article>
    <article class="card" style="text-align:center; padding:14px;">
        <small>Dispensados</small>
        <div style="font-size:1.8rem; font-weight:700; color:#6b7280;">{{ number_format($totais->dismissed ?? 0) }}</div>
    </article>
    <article class="card" style="text-align:center; padding:14px;">
        <small>Total</small>
        <div style="font-size:1.8rem; font-weight:700;">{{ number_format($totais->total ?? 0) }}</div>
    </article>
</div>

@if (session('success'))
    <div class="alert alert-success" style="margin-bottom:14px;">{{ session('success') }}</div>
@endif

{{-- â”€â”€ Filtros â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
<section class="card" style="margin-bottom:16px; padding:12px 16px;">
    <form method="GET" action="{{ route('analise.bos.auditoria-flagrantes') }}" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
        <input type="text" name="q" value="{{ request('q') }}"
               placeholder="SPJ, natureza, unidade..."
               style="padding:7px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:0.85rem; min-width:200px;">

        <select name="status" style="padding:7px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:0.85rem;">
            <option value="pending"   {{ $status === 'pending'   ? 'selected' : '' }}>Pendentes</option>
            <option value="approved"  {{ $status === 'approved'  ? 'selected' : '' }}>Aprovados</option>
            <option value="corrected" {{ $status === 'corrected' ? 'selected' : '' }}>Corrigidos</option>
            <option value="dismissed" {{ $status === 'dismissed' ? 'selected' : '' }}>Dispensados</option>
            <option value="todos"     {{ $status === 'todos'     ? 'selected' : '' }}>Todos</option>
        </select>

        <select name="ano" style="padding:7px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:0.85rem;">
            <option value="">â€” Ano â€”</option>
            @foreach (range(date('Y'), date('Y') - 5) as $ano)
                <option value="{{ $ano }}" {{ request('ano') == $ano ? 'selected' : '' }}>{{ $ano }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn">Filtrar</button>
        <a class="btn secondary" href="{{ route('analise.bos.auditoria-flagrantes') }}">Limpar</a>
    </form>
</section>

{{-- â”€â”€ AÃ§Ã£o em lote â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
@if ($status === 'pending' && $pendencias->total() > 0)
<section class="card" style="margin-bottom:16px; padding:12px 16px; background:#fffbeb; border:1px solid #f59e0b;">
    <form method="POST" action="{{ route('analise.bos.auditoria-flagrantes.bulk') }}"
          id="bulk-form" onsubmit="return confirmarBulk()">
        @csrf
        @method('PATCH')
        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
            <strong style="font-size:0.88rem;">AÃ§Ã£o em lote:</strong>
            <select name="acao" id="bulk-acao" required style="padding:6px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:0.83rem;">
                <option value="">â€” Selecione â€”</option>
                <option value="approved">Aprovar (confirmar cartÃ³rio)</option>
                <option value="corrected">Corrigir cartÃ³rio</option>
                <option value="dismissed">Dispensar todos</option>
            </select>
            <select name="cartorio_id" id="bulk-cartorio" style="padding:6px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:0.83rem; display:none;">
                <option value="">â€” CartÃ³rio â€”</option>
                @foreach ($cartorios as $cart)
                    <option value="{{ $cart->id }}">{{ $cart->number }} â€” {{ $cart->name }}</option>
                @endforeach
            </select>
            <input type="text" name="notes" placeholder="Obs. opcional" maxlength="500"
                   style="padding:6px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:0.83rem;">
            <button type="submit" class="btn" style="background:#f59e0b; color:#fff;">Aplicar aos selecionados</button>
        </div>
        <div id="bulk-ids-container"></div>
    </form>
</section>
@endif

{{-- â”€â”€ Tabela de pendÃªncias â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
<section class="card" style="padding:0; overflow:hidden;">
    @if ($pendencias->isEmpty())
        <p style="padding:24px; text-align:center; color:#9ca3af;">
            Nenhum registro encontrado com os filtros aplicados.
        </p>
    @else
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:0.83rem;">
            <thead>
                <tr style="border-bottom:2px solid #e5e7eb; background:#f9fafb;">
                    @if ($status === 'pending')
                    <th style="padding:10px 12px; width:32px;">
                        <input type="checkbox" id="sel-all" title="Selecionar todos" onchange="toggleAll(this)">
                    </th>
                    @endif
                    <th style="padding:10px 12px; text-align:left;">SPJ</th>
                    <th style="padding:10px 12px; text-align:left;">Data oc.</th>
                    <th style="padding:10px 12px; text-align:left;">Naturezas</th>
                    <th style="padding:10px 12px; text-align:left;">Lavrado</th>
                    <th style="padding:10px 12px; text-align:left;">NÂº IP</th>
                    <th style="padding:10px 12px; text-align:left;">CartÃ³rio planilha</th>
                    <th style="padding:10px 12px; text-align:left;">Status</th>
                    @if ($status !== 'pending')
                    <th style="padding:10px 12px; text-align:left;">CartÃ³rio atribuÃ­do</th>
                    <th style="padding:10px 12px; text-align:left;">Revisado por</th>
                    <th style="padding:10px 12px; text-align:left;">Obs.</th>
                    @endif
                    @if ($status === 'pending')
                    <th style="padding:10px 12px; text-align:center;">AÃ§Ã£o</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($pendencias as $p)
                <tr class="aud-row" style="border-bottom:1px solid #f3f4f6;">
                    @if ($status === 'pending')
                    <td style="padding:8px 12px;">
                        <input type="checkbox" class="row-cb" value="{{ $p->id }}" onchange="updateBulkIds()">
                    </td>
                    @endif
                    <td style="padding:8px 12px; font-weight:600; white-space:nowrap;">{{ $p->spj }}</td>
                    <td style="padding:8px 12px; white-space:nowrap; color:#6b7280;">{{ $p->data_ocorrencia ?? 'â€”' }}</td>
                    <td style="padding:8px 12px; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                        title="{{ $p->naturezas }}">{{ $p->naturezas ?? 'â€”' }}</td>
                    <td style="padding:8px 12px; white-space:nowrap;">{{ $p->lavrado ?? 'â€”' }}</td>
                    <td style="padding:8px 12px; white-space:nowrap;">{{ $p->num_ip ?? 'â€”' }}</td>
                    <td style="padding:8px 12px; color:#b45309;">
                        {{ $p->cartorio_ip_planilha ? $p->cartorio_ip_planilha : '(vazio)' }}
                    </td>
                    <td style="padding:8px 12px;">
                        <span class="badge-status {{ $p->status }}">{{ $p->statusLabel() }}</span>
                    </td>

                    @if ($status !== 'pending')
                    <td style="padding:8px 12px;">
                        {{ $p->cartorio?->number ? "[{$p->cartorio->number}] {$p->cartorio->name}" : 'â€”' }}
                    </td>
                    <td style="padding:8px 12px; font-size:0.78rem; color:#6b7280;">
                        {{ $p->reviewer?->name ?? 'â€”' }}<br>
                        {{ $p->reviewed_at?->format('d/m/Y H:i') ?? '' }}
                    </td>
                    <td style="padding:8px 12px; max-width:160px; overflow:hidden; text-overflow:ellipsis;"
                        title="{{ $p->notes }}">{{ $p->notes ?? 'â€”' }}</td>
                    @endif

                    @if ($status === 'pending')
                    <td style="padding:8px 12px;">
                        {{-- Mini-formulÃ¡rio inline de aÃ§Ã£o --}}
                        <form method="POST"
                              action="{{ route('analise.bos.auditoria-flagrantes.update', $p) }}"
                              class="aud-form">
                            @csrf
                            @method('PATCH')

                            <select name="acao" required onchange="toggleCartorioField(this)"
                                    style="padding:4px 8px; border:1px solid #d1d5db; border-radius:5px; font-size:0.8rem;">
                                <option value="">â€” aÃ§Ã£o â€”</option>
                                <option value="approved">Aprovar</option>
                                <option value="corrected">Corrigir cartÃ³rio</option>
                                <option value="dismissed">Dispensar</option>
                            </select>

                            <select name="cartorio_id" class="cart-select"
                                    style="display:none; padding:4px 8px; border:1px solid #d1d5db; border-radius:5px; font-size:0.8rem;">
                                <option value="">â€” cartÃ³rio â€”</option>
                                @foreach ($cartorios as $cart)
                                    <option value="{{ $cart->id }}">{{ $cart->number }} â€” {{ $cart->name }}</option>
                                @endforeach
                            </select>

                            <input type="text" name="notes" placeholder="Obs." maxlength="300"
                                   style="padding:4px 8px; border:1px solid #d1d5db; border-radius:5px; font-size:0.8rem; width:120px;">

                            <button type="submit" class="btn" style="padding:4px 12px; font-size:0.8rem;">OK</button>
                        </form>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- PaginaÃ§Ã£o --}}
    @if ($pendencias->hasPages())
    <div style="padding:14px 16px; border-top:1px solid #e5e7eb;">
        {{ $pendencias->links() }}
    </div>
    @endif

    @endif
</section>

@push('scripts')
<script>
function toggleCartorioField(sel) {
    const form = sel.closest('form');
    const cartSel = form.querySelector('.cart-select');
    const needsCart = ['approved', 'corrected'].includes(sel.value);
    cartSel.style.display = needsCart ? 'inline-block' : 'none';
    cartSel.required = needsCart;
}

function toggleAll(master) {
    document.querySelectorAll('.row-cb').forEach(cb => {
        cb.checked = master.checked;
    });
    updateBulkIds();
}

function updateBulkIds() {
    const container = document.getElementById('bulk-ids-container');
    if (!container) return;
    container.innerHTML = '';
    document.querySelectorAll('.row-cb:checked').forEach(cb => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'ids[]';
        inp.value = cb.value;
        container.appendChild(inp);
    });
}

const bulkAcaoSel = document.getElementById('bulk-acao');
const bulkCartSel = document.getElementById('bulk-cartorio');
if (bulkAcaoSel) {
    bulkAcaoSel.addEventListener('change', function() {
        const need = ['approved', 'corrected'].includes(this.value);
        bulkCartSel.style.display = need ? 'inline-block' : 'none';
        bulkCartSel.required = need;
    });
}

function confirmarBulk() {
    const ids = document.querySelectorAll('.row-cb:checked').length;
    if (ids === 0) {
        alert('Selecione ao menos um registro.');
        return false;
    }
    const acao = document.getElementById('bulk-acao').value;
    if (!acao) { alert('Selecione uma aÃ§Ã£o.'); return false; }
    return confirm(`Aplicar aÃ§Ã£o "${acao}" em ${ids} registro(s)?`);
}
</script>
@endpush

@endsection

