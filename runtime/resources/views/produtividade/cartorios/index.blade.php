@extends('layouts.app')

@section('title', 'Cartorios | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Cartorios e produtividade mensal</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Piloto web do modulo de produtividade com foco em cadastro, governanca e fechamento inicial por cartorio.
            </p>
        </div>
        @if (config('grom_legacy.enabled') && auth()->user()->hasPermission('produtividade.cartorios.manage'))
            <div class="actions">
                <form method="POST" action="{{ route('produtividade.cartorios.sync-legacy') }}">
                    @csrf
                    <button type="submit" class="secondary">Sincronizar base legada Python</button>
                </form>
            </div>
        @endif
    </div>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Cartorios</small>
            <strong>{{ $summary['total'] }}</strong>
            <span>{{ $summary['active'] }} ativos e {{ $summary['inactive'] }} inativos.</span>
        </article>
        <article class="card">
            <small>IP instaurados</small>
            <strong>{{ $summary['ip_instaurados'] }}</strong>
            <span>Consolidado de {{ $referenceLabel }}.</span>
        </article>
        <article class="card">
            <small>Flagrantes do mes</small>
            <strong>{{ $summary['flagrantes_total'] }}</strong>
            <span>DDM {{ $summary['flagrantes_ddm'] }} | Outras {{ $summary['flagrantes_outras'] }}.</span>
        </article>
    </div>

    @if (auth()->user()->hasPermission('produtividade.cartorios.manage'))
        <section class="card" style="margin-bottom: 18px;">
            <h2 style="margin-top: 0;">Novo cartorio</h2>
            <form method="POST" action="{{ route('produtividade.cartorios.store') }}" class="grid">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label for="number">Numero</label>
                        <input id="number" name="number" type="number" min="1" max="9999" required>
                    </div>
                    <div class="field">
                        <label for="name">Nome</label>
                        <input id="name" name="name" type="text" required>
                    </div>
                    <div class="field">
                        <label for="designacao">Designacao</label>
                        <input id="designacao" name="designacao" type="text">
                    </div>
                    <div class="field">
                        <label for="manager_name">Responsavel atual</label>
                        <input id="manager_name" name="manager_name" type="text">
                    </div>
                    <div class="field full">
                        <label for="notes">Observacoes</label>
                        <input id="notes" name="notes" type="text">
                    </div>
                    <div class="field">
                        <label for="is_active">Status inicial</label>
                        <select id="is_active" name="is_active">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit">Criar cartorio</button>
                    <span class="muted">A exclusao definitiva ainda nao esta liberada no piloto web.</span>
                </div>
            </form>
        </section>
    @endif

    <section class="card">
        <h2 style="margin-top: 0;">Base de cartorios</h2>
        <table>
            <thead>
                <tr>
                    <th>Cartorio</th>
                    <th>Responsavel</th>
                    <th>Status</th>
                    <th>Mes atual</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cartorios as $cartorio)
                    <?php $currentStats = $cartorio->monthlyStats->first(); ?>
                    <tr>
                        <td>
                            <strong>{{ str_pad((string) $cartorio->number, 3, '0', STR_PAD_LEFT) }} - {{ $cartorio->name }}</strong><br>
                            <span class="muted">
                                {{ $cartorio->code }}
                                @if ($cartorio->designacao)
                                    | {{ $cartorio->designacao }}
                                @endif
                            </span>
                        </td>
                        <td>{{ $cartorio->manager_name ?: 'Nao informado' }}</td>
                        <td>
                            <span class="tag {{ $cartorio->is_active ? 'good' : 'warn' }}">
                                {{ $cartorio->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td>
                            <strong>IP {{ $currentStats?->ip_instaurados ?? 0 }}</strong><br>
                            <span class="muted">
                                Flagrantes {{ $currentStats?->flagrantes_total ?? 0 }}
                                (DDM {{ $currentStats?->flagrantes_ddm ?? 0 }} | Outras {{ $currentStats?->flagrantes_outras ?? 0 }})
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                @if (auth()->user()->hasPermission('produtividade.cartorios.manage'))
                                    <a class="btn secondary" style="font-size: 0.82rem; padding: 6px 10px;"
                                        href="{{ route('produtividade.cartorios.fechamento.create', $cartorio) }}">
                                        Fechar mês
                                    </a>
                                    <button type="button" class="secondary" style="font-size: 0.82rem; padding: 6px 10px;"
                                        onclick="document.getElementById('edit-cartorio-{{ $cartorio->id }}').showModal()">
                                        Editar
                                    </button>
                                    <button type="button" class="secondary" style="font-size: 0.82rem; padding: 6px 10px;"
                                        onclick="document.getElementById('designacao-{{ $cartorio->id }}').showModal()">
                                        Designações
                                    </button>
                                    <form method="POST" action="{{ route('produtividade.cartorios.toggle-active', $cartorio) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="secondary" style="font-size: 0.82rem; padding: 6px 10px;">
                                            {{ $cartorio->is_active ? 'Inativar' : 'Ativar' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Nenhum cartorio cadastrado nesta base web.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Dialogs fora da tabela (HTML válido: <dialog> não pode ser filho de <tbody>) --}}
        @if (auth()->user()->hasPermission('produtividade.cartorios.manage'))
            @foreach ($cartorios as $cartorio)

                {{-- Modal: Editar cartório --}}
                <dialog id="edit-cartorio-{{ $cartorio->id }}" style="width: min(560px, 95vw); border: 1px solid #ccc; border-radius: 8px; padding: 0;">
                    <div style="padding: 18px 22px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">
                            <strong>Editar: {{ str_pad((string) $cartorio->number, 3, '0', STR_PAD_LEFT) }} — {{ $cartorio->name }}</strong>
                            <button type="button" class="secondary" onclick="document.getElementById('edit-cartorio-{{ $cartorio->id }}').close()">✕</button>
                        </div>
                        <form method="POST" action="{{ route('produtividade.cartorios.update', $cartorio) }}" class="grid">
                            @csrf
                            @method('PUT')
                            <div class="form-grid">
                                <div class="field">
                                    <label>Número</label>
                                    <input name="number" type="number" min="1" max="9999" value="{{ $cartorio->number }}" required>
                                </div>
                                <div class="field">
                                    <label>Nome</label>
                                    <input name="name" type="text" value="{{ $cartorio->name }}" required>
                                </div>
                                <div class="field">
                                    <label>Designação (tipo)</label>
                                    <input name="designacao" type="text" value="{{ $cartorio->designacao }}" placeholder="Ex: DDM, DIG, DC...">
                                </div>
                                <div class="field full">
                                    <label>Observações</label>
                                    <input name="notes" type="text" value="{{ $cartorio->notes }}">
                                </div>
                                <div class="field">
                                    <label>Status</label>
                                    <select name="is_active">
                                        <option value="1" @selected($cartorio->is_active)>Ativo</option>
                                        <option value="0" @selected(! $cartorio->is_active)>Inativo</option>
                                    </select>
                                </div>
                            </div>
                            <p class="muted" style="font-size: 0.8rem; margin: 8px 0 0;">
                                Para alterar o responsável, use o botão <strong>Designações</strong>.
                            </p>
                            <div class="actions" style="margin-top: 12px;">
                                <button type="submit">Salvar alterações</button>
                                <button type="button" class="secondary" onclick="document.getElementById('edit-cartorio-{{ $cartorio->id }}').close()">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </dialog>

                {{-- Modal: Designações --}}
                <dialog id="designacao-{{ $cartorio->id }}" style="width: min(680px, 95vw); border: 1px solid #ccc; border-radius: 8px; padding: 0;">
                    <div style="padding: 18px 22px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">
                            <div>
                                <strong>{{ str_pad((string) $cartorio->number, 3, '0', STR_PAD_LEFT) }} — {{ $cartorio->name }}</strong>
                                <p class="muted" style="font-size: 0.8rem; margin: 2px 0 0;">Histórico de designações — use "Corrigir" para ajustar registros existentes</p>
                            </div>
                            <button type="button" class="secondary" onclick="document.getElementById('designacao-{{ $cartorio->id }}').close()">✕</button>
                        </div>

                        {{-- Histórico --}}
                        @if ($cartorio->managerHistory->isNotEmpty())
                            <table style="font-size: 0.82rem; margin-bottom: 18px;">
                                <thead>
                                    <tr>
                                        <th>Responsável</th>
                                        <th>De</th>
                                        <th>Até</th>
                                        <th>Obs</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cartorio->managerHistory as $hist)
                                        <tr>
                                            <td>
                                                {{ $hist->manager_name }}
                                                @if ($hist->isVigente())
                                                    <span class="tag good" style="font-size: 0.72rem; margin-left: 4px;">Vigente</span>
                                                @endif
                                            </td>
                                            <td>{{ $hist->started_at?->format('d/m/Y') ?? '—' }}</td>
                                            <td>{{ $hist->ended_at?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="muted" style="font-size: 0.78rem;">{{ $hist->reason ?? '' }}</td>
                                            <td>
                                                <button type="button" class="secondary" style="font-size: 0.75rem; padding: 3px 8px; white-space: nowrap;"
                                                    onclick="document.getElementById('corr-{{ $hist->id }}').showModal()">
                                                    Corrigir
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="muted" style="font-size: 0.85rem; margin-bottom: 14px;">Nenhum registro de designação encontrado.</p>
                        @endif

                        {{-- Formulário: nova designação --}}
                        <h4 style="margin: 0 0 8px; font-size: 0.88rem; border-top: 1px solid #e0e0e0; padding-top: 12px;">
                            Registrar designação
                        </h4>
                        <p class="muted" style="font-size: 0.78rem; margin: 0 0 12px;">
                            A data de início é a data em que esta pessoa assumiu <strong>este cartório</strong> — independente de designação anterior na DDM ou outro setor.
                            Informe também a data de saída para registrar histórico encerrado; deixe em branco se o período ainda está vigente.
                        </p>
                        <form method="POST" action="{{ route('produtividade.cartorios.designacoes.store', $cartorio) }}" class="grid">
                            @csrf
                            <div class="form-grid">
                                <div class="field">
                                    <label>Responsável <span style="color:#c0392b">*</span></label>
                                    <select name="manager_name" required>
                                        <option value="">— selecione —</option>
                                        @if ($policiais->has('escrivao') && $policiais['escrivao']->isNotEmpty())
                                            <optgroup label="Escrivão de Polícia (preferencial)">
                                                @foreach ($policiais['escrivao'] as $pol)
                                                    <option value="{{ $pol->short_name ?: $pol->name }}">{{ $pol->short_name ?: $pol->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        @if ($policiais->has('outros') && $policiais['outros']->isNotEmpty())
                                            <optgroup label="Outros Policiais de Carreira">
                                                @foreach ($policiais['outros'] as $pol)
                                                    <option value="{{ $pol->short_name ?: $pol->name }}">{{ $pol->short_name ?: $pol->name }}{{ $pol->cargo ? ' (' . $pol->cargo->name . ')' : '' }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Início no cartório <span style="color:#c0392b">*</span></label>
                                    <input name="started_at" type="date" required max="{{ now()->toDateString() }}">
                                </div>
                                <div class="field">
                                    <label>Saída do cartório <small class="muted">(vazio = vigente)</small></label>
                                    <input name="ended_at" type="date" max="{{ now()->toDateString() }}">
                                </div>
                                <div class="field full">
                                    <label>Motivo / Observação</label>
                                    <input name="reason" type="text" placeholder="Ex: Portaria nº 123/2026, substituição por férias...">
                                </div>
                            </div>
                            <div class="actions" style="margin-top: 10px;">
                                <button type="submit">Registrar</button>
                                <button type="button" class="secondary" onclick="document.getElementById('designacao-{{ $cartorio->id }}').close()">Fechar</button>
                            </div>
                        </form>
                    </div>
                </dialog>

                {{-- Dialogs de correção por registro --}}
                @foreach ($cartorio->managerHistory as $hist)
                    <dialog id="corr-{{ $hist->id }}" style="width: min(540px, 95vw); border: 1px solid #ccc; border-radius: 8px; padding: 0;">
                        <div style="padding: 18px 22px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #e0e0e0; padding-bottom: 10px;">
                                <div>
                                    <strong>Corrigir registro</strong>
                                    <p class="muted" style="font-size: 0.78rem; margin: 2px 0 0;">Deixa rastro de auditoria. Use para corrigir dados importados incorretamente.</p>
                                </div>
                                <button type="button" class="secondary" onclick="document.getElementById('corr-{{ $hist->id }}').close()">✕</button>
                            </div>
                            <form method="POST"
                                action="{{ route('produtividade.cartorios.designacoes.update', [$cartorio, $hist]) }}"
                                class="grid">
                                @csrf
                                @method('PATCH')
                                <div class="form-grid">
                                    <div class="field full">
                                        <label>Responsável <span style="color:#c0392b">*</span></label>
                                        <select name="manager_name" required>
                                            <option value="{{ $hist->manager_name }}" selected>{{ $hist->manager_name }}</option>
                                            @if ($policiais->has('escrivao') && $policiais['escrivao']->isNotEmpty())
                                                <optgroup label="Escrivão de Polícia">
                                                    @foreach ($policiais['escrivao'] as $pol)
                                                        @php $v = $pol->short_name ?: $pol->name; @endphp
                                                        @if ($v !== $hist->manager_name)
                                                            <option value="{{ $v }}">{{ $v }}</option>
                                                        @endif
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                            @if ($policiais->has('outros') && $policiais['outros']->isNotEmpty())
                                                <optgroup label="Outros Policiais de Carreira">
                                                    @foreach ($policiais['outros'] as $pol)
                                                        @php $v = $pol->short_name ?: $pol->name; @endphp
                                                        @if ($v !== $hist->manager_name)
                                                            <option value="{{ $v }}">{{ $v }} ({{ $pol->cargo?->name }})</option>
                                                        @endif
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label>Início no cartório <span style="color:#c0392b">*</span></label>
                                        <input name="started_at" type="date" required value="{{ $hist->started_at?->toDateString() }}">
                                    </div>
                                    <div class="field">
                                        <label>Saída do cartório <small class="muted">(vazio = vigente)</small></label>
                                        <input name="ended_at" type="date" value="{{ $hist->ended_at?->toDateString() }}">
                                    </div>
                                    <div class="field full">
                                        <label>Motivo / Observação</label>
                                        <input name="reason" type="text" value="{{ $hist->reason }}"
                                            placeholder="Ex: corrigido — dado importado incorretamente da base legada">
                                    </div>
                                </div>
                                <div class="actions" style="margin-top: 10px;">
                                    <button type="submit">Salvar correção</button>
                                    <button type="button" class="secondary" onclick="document.getElementById('corr-{{ $hist->id }}').close()">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </dialog>
                @endforeach

            @endforeach
        @endif
    </section>
@endsection
