@extends('layouts.app')

@section('title', 'Fechamento Mensal â€” ' . $cartorio->name . ' | Grom.Seg')

@section('content')

<div class="section-head">
    <div>
        <h1>Fechamento Mensal</h1>
        <p class="muted" style="margin: 6px 0 0;">
            <strong>{{ $cartorio->name }}</strong>
            &nbsp;&mdash;&nbsp;
            {{ $months[$month] }} {{ $year }}
            @if ($existing && $existing->source_mode)
                <span class="tag {{ $existing->source_mode === 'MANUAL' ? 'good' : 'info' }}" style="margin-left:8px;">
                    {{ $existing->source_mode }}
                </span>
            @endif
        </p>
    </div>
    <div class="actions">
        <a class="btn secondary" href="{{ route('produtividade.cartorios.index') }}">â† Cartórios</a>
        <a class="btn secondary" href="{{ route('produtividade.stats.index', ['year' => $year, 'month' => $month]) }}">Estatísticas</a>
        <a class="btn secondary" href="{{ route('produtividade.hub') }}">Hub</a>
    </div>
</div>

{{-- â”€â”€ Alertas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
@if (session('status'))
    <div class="alert ok" style="margin-bottom: 16px;">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert error" style="margin-bottom: 16px;">
        <ul style="margin: 0; padding-left: 18px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid" style="grid-template-columns: 1.4fr 1fr; gap: 16px; align-items: start;">

    {{-- â”€â”€ Formulário principal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    <section class="card">
        <h2 style="margin-top: 0;">
            Lançar / editar dados
            @if ($existing)
                <span style="font-size: 0.78rem; font-weight: 400; color: #6b7280;">
                    (atualizado {{ $existing->updated_at->format('d/m/Y H:i') }})
                </span>
            @endif
        </h2>

        <form method="POST" action="{{ route('produtividade.cartorios.fechamento.store', $cartorio) }}" class="form-grid">
            @csrf

            {{-- Período --}}
            <div class="field">
                <label for="year">Ano</label>
                <input id="year" name="year" type="number" min="2020" max="2100"
                       value="{{ old('year', $year) }}" required>
                @error('year') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="month">Mês</label>
                <select id="month" name="month" required>
                    @foreach ($months as $idx => $label)
                        <option value="{{ $idx }}" @selected(old('month', $month) == $idx)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('month') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Inquéritos policiais --}}
            <div class="field" style="grid-column:span 2;">
                <label style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280; font-weight:600;">
                    Inquéritos policiais
                </label>
            </div>

            <div class="field">
                <label for="ip_instaurados">IP instaurados</label>
                <input id="ip_instaurados" name="ip_instaurados" type="number" min="0"
                       value="{{ old('ip_instaurados', $existing?->ip_instaurados ?? 0) }}" required>
                @error('ip_instaurados') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="ip_relatados">IP relatados</label>
                <input id="ip_relatados" name="ip_relatados" type="number" min="0"
                       value="{{ old('ip_relatados', $existing?->ip_relatados ?? 0) }}" required>
                @error('ip_relatados') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="ips_andamento">IPs em andamento</label>
                <input id="ips_andamento" name="ips_andamento" type="number" min="0"
                       value="{{ old('ips_andamento', $existing?->ips_andamento ?? 0) }}" required>
                @error('ips_andamento') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="concluidos">Concluídos</label>
                <input id="concluidos" name="concluidos" type="number" min="0"
                       value="{{ old('concluidos', $existing?->concluidos ?? 0) }}" required>
                @error('concluidos') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Atividades cartoriais --}}
            <div class="field" style="grid-column:span 2;">
                <label style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280; font-weight:600;">
                    Atividades cartoriais
                </label>
            </div>

            <div class="field">
                <label for="cotas">Cotas</label>
                <input id="cotas" name="cotas" type="number" min="0"
                       value="{{ old('cotas', $existing?->cotas ?? 0) }}" required>
                @error('cotas') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="despachos">Despachos</label>
                <input id="despachos" name="despachos" type="number" min="0"
                       value="{{ old('despachos', $existing?->despachos ?? 0) }}" required>
                @error('despachos') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="registros">Registros</label>
                <input id="registros" name="registros" type="number" min="0"
                       value="{{ old('registros', $existing?->registros ?? 0) }}" required>
                @error('registros') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Notas --}}
            <div class="field" style="grid-column:span 2;">
                <label for="manual_notes">Observações (opcional)</label>
                <textarea id="manual_notes" name="manual_notes" rows="3" maxlength="2000"
                          style="resize:vertical;">{{ old('manual_notes', $existing?->manual_notes ?? '') }}</textarea>
                @error('manual_notes') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field" style="grid-column:span 2;">
                <div class="actions">
                    <button type="submit">Salvar fechamento</button>
                    <a class="btn secondary" href="{{ route('produtividade.cartorios.fechamento.create', [
                        'cartorio' => $cartorio,
                        'year'  => $month === 1 ? $year - 1 : $year,
                        'month' => $month === 1 ? 12 : $month - 1,
                    ]) }}">â† Mês anterior</a>
                    <a class="btn secondary" href="{{ route('produtividade.cartorios.fechamento.create', [
                        'cartorio' => $cartorio,
                        'year'  => $month === 12 ? $year + 1 : $year,
                        'month' => $month === 12 ? 1 : $month + 1,
                    ]) }}">Mês seguinte â†’</a>
                </div>
            </div>
        </form>
    </section>

    {{-- â”€â”€ Painel lateral â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    <div style="display:flex; flex-direction:column; gap:16px;">

        {{-- Mês anterior (referência) --}}
        @if ($prevRecord)
        <section class="card">
            <h3 style="margin-top:0; font-size:0.9rem;">
                Referência: {{ $months[$prevMonth] }} {{ $prevYear }}
            </h3>
            <table style="font-size:0.82rem; width:100%;">
                <tbody>
                    <tr><td>IP instaurados</td><td style="text-align:right; font-weight:600;">{{ $prevRecord->ip_instaurados }}</td></tr>
                    <tr><td>IP relatados</td><td style="text-align:right; font-weight:600;">{{ $prevRecord->ip_relatados }}</td></tr>
                    <tr><td>Concluídos</td><td style="text-align:right; font-weight:600;">{{ $prevRecord->concluidos }}</td></tr>
                    <tr><td>IPs andamento</td><td style="text-align:right; font-weight:600;">{{ $prevRecord->ips_andamento }}</td></tr>
                    <tr><td>Cotas</td><td style="text-align:right; font-weight:600;">{{ $prevRecord->cotas }}</td></tr>
                    <tr><td>Despachos</td><td style="text-align:right; font-weight:600;">{{ $prevRecord->despachos }}</td></tr>
                    <tr><td>Registros</td><td style="text-align:right; font-weight:600;">{{ $prevRecord->registros }}</td></tr>
                    <tr><td>Flagrantes</td><td style="text-align:right; font-weight:600;">{{ $prevRecord->flagrantes_total }}</td></tr>
                </tbody>
            </table>
        </section>
        @else
        <section class="card">
            <p class="muted" style="margin:0; font-size:0.83rem;">
                Sem dados do mês anterior ({{ $months[$prevMonth] }} {{ $prevYear }}).
            </p>
        </section>
        @endif

        {{-- Evolução anual --}}
        <section class="card">
            <h3 style="margin-top:0; font-size:0.9rem;">Evolução {{ $year }}</h3>
            <table style="font-size:0.78rem; width:100%;">
                <thead>
                    <tr style="border-bottom:1px solid #e5e7eb;">
                        <th style="text-align:left; padding:4px 6px;">Mês</th>
                        <th style="text-align:right; padding:4px 6px;">IP</th>
                        <th style="text-align:right; padding:4px 6px;">Rel.</th>
                        <th style="text-align:right; padding:4px 6px;">Flag.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($months as $idx => $label)
                        @php $row = $yearBreakdown->get($idx); @endphp
                        <tr style="{{ $idx === $month ? 'background:#eff6ff; font-weight:700;' : '' }} border-bottom:1px solid #f3f4f6;">
                            <td style="padding:3px 6px;">
                                <a href="{{ route('produtividade.cartorios.fechamento.create', ['cartorio' => $cartorio, 'year' => $year, 'month' => $idx]) }}"
                                   style="color:inherit; text-decoration:none;">
                                    {{ substr($label, 0, 3) }}
                                </a>
                            </td>
                            <td style="text-align:right; padding:3px 6px;">{{ $row?->ip_instaurados ?? 'â€”' }}</td>
                            <td style="text-align:right; padding:3px 6px;">{{ $row?->ip_relatados ?? 'â€”' }}</td>
                            <td style="text-align:right; padding:3px 6px; color:#dc2626;">{{ $row?->flagrantes_total ?? 'â€”' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid #e5e7eb; font-weight:700; background:#f9fafb;">
                        <td style="padding:4px 6px;">Total</td>
                        <td style="text-align:right; padding:4px 6px;">{{ $yearBreakdown->sum('ip_instaurados') }}</td>
                        <td style="text-align:right; padding:4px 6px;">{{ $yearBreakdown->sum('ip_relatados') }}</td>
                        <td style="text-align:right; padding:4px 6px; color:#dc2626;">{{ $yearBreakdown->sum('flagrantes_total') }}</td>
                    </tr>
                </tfoot>
            </table>
        </section>

    </div>
</div>

@endsection

