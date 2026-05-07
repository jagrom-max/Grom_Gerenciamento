@php
    $canManage = auth()->user()->hasPermission('calendarios.manage');
    $legacyHolidays = $legacyHolidays ?? collect();
    $contextHolidays = $contextHolidays ?? collect();
@endphp

@extends('layouts.app')

@section('title', 'Calendario de afastamentos | Grom.Seg')

@section('content')
    @if (session('status'))
        <section class="card" style="margin-bottom: 18px;">
            <span class="tag good">{{ session('status') }}</span>
        </section>
    @endif

    <div class="section-head">
        <div>
            <h1>Calendario de afastamentos</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Consulta mensal dos afastamentos ja lancados no RH.
            </p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('rh.index') }}#rh-afastamentos">Afastamentos RH</a>
            <a class="btn secondary" href="{{ route('dashboard') }}">Dashboard</a>
            @if ($canManage)
                <a class="btn secondary" href="#feriados-contexto">Feriados de apoio</a>
            @endif
        </div>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <form method="GET" action="{{ route('calendarios.index') }}" class="actions">
            <div class="field" style="min-width: 140px;">
                <label for="ano">Ano</label>
                <select id="ano" name="ano">
                    @foreach ($snapshot['available_years'] as $year)
                        <option value="{{ $year }}" @selected($filters['ano'] === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="min-width: 180px;">
                <label for="mes">Mes</label>
                <select id="mes" name="mes">
                    @foreach ($snapshot['available_months'] as $month)
                        <option value="{{ $month }}" @selected($filters['mes'] === $month)>{{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }} - {{ \Carbon\Carbon::create()->month($month)->locale('pt_BR')->isoFormat('MMMM') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions" style="align-self: end;">
                <button type="submit">Atualizar</button>
            </div>
        </form>
    </section>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Periodo</small>
            <strong>{{ ucfirst($snapshot['month_label']) }}</strong>
            <span>{{ $snapshot['year'] }} | agenda de afastamentos.</span>
        </article>
        <article class="card">
            <small>Afastamentos no mes</small>
            <strong>{{ $summary['afastamentos_total'] }}</strong>
            <span>Registros que atravessam o periodo selecionado.</span>
        </article>
        <article class="card">
            <small>Servidores afastados</small>
            <strong>{{ $summary['funcionarios_afastados'] }}</strong>
            <span>Funcionarios distintos impedidos no mes.</span>
        </article>
        <article class="card">
            <small>Dias criticos</small>
            <strong>{{ $summary['dias_com_sobreposicao'] }}</strong>
            <span>Janelas com mais de um impedimento simultaneo.</span>
        </article>
        <article class="card">
            <small>Afastamentos em aberto</small>
            <strong>{{ $summary['afastamentos_em_aberto'] }}</strong>
            <span>Registros sem data final definida.</span>
        </article>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Afastamentos por dia</h2>
        <p class="muted" style="margin-top: 0;">
            Cada linha mostra os servidores impedidos naquele dia, com alerta imediato quando ha sobreposicao.
        </p>

        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Impedimentos</th>
                    <th>Equipe envolvida</th>
                    <th>Contexto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($days as $day)
                    <tr>
                        <td>
                            <strong>{{ str_pad((string) $day['day'], 2, '0', STR_PAD_LEFT) }}/{{ str_pad((string) $filters['mes'], 2, '0', STR_PAD_LEFT) }}/{{ $filters['ano'] }}</strong><br>
                            <span class="muted">{{ $day['weekday'] }}</span>
                        </td>
                        <td>
                            <span class="tag {{ $day['absence_count'] > 0 ? 'warn' : 'good' }}">
                                {{ $day['absence_count'] }} afast.
                            </span>
                            @if ($day['has_conflict'])
                                <br><span class="tag warn" style="margin-top: 6px;">Sobreposicao</span>
                            @endif
                        </td>
                        <td>
                            @forelse ($day['absences'] as $absence)
                                <div style="margin-bottom: 10px;">
                                    <strong>{{ $absence['funcionario_short'] }}</strong><br>
                                    <span class="muted">{{ $absence['reason'] }}</span><br>
                                    <span class="muted">{{ $absence['start_label'] }} - {{ $absence['end_label'] }}</span>
                                </div>
                            @empty
                                <span class="muted">Sem afastamento neste dia.</span>
                            @endforelse
                        </td>
                        <td>
                            @if (! empty($day['holiday']))
                                <div class="tag good">{{ $day['holiday']['name'] }}</div>
                                <div class="muted" style="margin-top: 6px;">Escopo: {{ $day['holiday']['scope'] }}</div>
                            @else
                                <span class="muted">Sem feriado de apoio.</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Nenhum dia encontrado para o periodo selecionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @if ($canManage)
        <details class="card" id="feriados-contexto">
            <summary style="cursor: pointer; font-weight: 700;">Feriados de apoio e manutencao</summary>
            <p class="muted" style="margin-top: 12px;">
                Esta area continua disponivel para ajustes de feriados, mas o eixo principal da pagina agora e a agenda de afastamentos do RH.
            </p>

            <div class="grid" style="grid-template-columns: 1fr 1fr; margin-top: 12px;">
                <section class="card" style="background: #f9fbfd;">
                    <h3 style="margin-top: 0;">Manter feriado</h3>
                    <form method="POST" action="{{ route('calendarios.feriados.store') }}" class="grid">
                        @csrf
                        @if ($editingHoliday)
                            <input type="hidden" name="holiday_id" value="{{ $editingHoliday->id }}">
                        @endif
                        <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                            <div class="field">
                                <label for="holiday_date">Data</label>
                                <input id="holiday_date" name="holiday_date" type="date" value="{{ old('holiday_date', $editingHoliday?->holiday_date?->format('Y-m-d')) }}" required {{ $editingHoliday ? 'readonly' : '' }}>
                            </div>
                            <div class="field">
                                <label for="scope">Escopo</label>
                                <select id="scope" name="scope" required>
                                    @foreach (['nacional' => 'Nacional', 'estadual' => 'Estadual', 'municipal' => 'Municipal', 'facultativo' => 'Ponto facultativo'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('scope', $editingHoliday?->scope ?? 'nacional') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label for="name">Nome</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $editingHoliday?->name) }}" required maxlength="255">
                        </div>
                        <div class="field">
                            <label for="notes">Observacoes</label>
                            <textarea id="notes" name="notes" rows="3">{{ old('notes', $editingHoliday?->notes) }}</textarea>
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600;">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingHoliday?->is_active ?? true))>
                            Ativo
                        </label>
                        <div class="actions">
                            <button type="submit">{{ $editingHoliday ? 'Atualizar feriado' : 'Salvar feriado' }}</button>
                            @if ($editingHoliday)
                                <a class="btn secondary" href="{{ route('calendarios.index', $filters) }}">Cancelar edicao</a>
                            @endif
                        </div>
                    </form>
                </section>

                <section class="card" style="background: #f9fbfd;">
                    <h3 style="margin-top: 0;">Importar da base legada</h3>
                    <p class="muted" style="margin-top: 0;">
                        Importa todos os feriados do Python para o espelho PHP, preservando a base real do legado.
                    </p>
                    <form method="POST" action="{{ route('calendarios.legacy.sync') }}" class="grid">
                        @csrf
                        <input type="hidden" name="ano" value="{{ $filters['ano'] }}">
                        <input type="hidden" name="mes" value="{{ $filters['mes'] }}">
                        <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                            <div class="field">
                                <label>Ano</label>
                                <input type="text" value="{{ $filters['ano'] }}" disabled>
                            </div>
                            <div class="field">
                                <label>Mes</label>
                                <input type="text" value="{{ str_pad((string) $filters['mes'], 2, '0', STR_PAD_LEFT) }}" disabled>
                            </div>
                        </div>
                        <div class="actions">
                            @if (config('grom_legacy.enabled'))
                            <button type="submit">Sincronizar feriados do legado</button>
                            @endif
                            <a class="btn secondary" href="{{ route('calendarios.index', $filters) }}">Atualizar visao</a>
                        </div>
                    </form>
                </section>
            </div>

            <div class="grid" style="grid-template-columns: 1fr 1fr; margin-top: 12px;">
                <section class="card" style="background: #f9fbfd;">
                    <h3 style="margin-top: 0;">Feriados do mes</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Nome</th>
                                <th>Escopo</th>
                                @if ($canManage)
                                    <th>Acoes</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contextHolidays as $holiday)
                                <tr>
                                    <td>{{ $holiday->holiday_date?->format('d/m/Y') ?? 'N/A' }}</td>
                                    <td>{{ $holiday->name }}</td>
                                    <td><span class="tag good">{{ $holiday->scope }}</span></td>
                                    @if ($canManage)
                                        <td>
                                            <div class="actions">
                                                <a class="btn secondary" href="{{ route('calendarios.index', ['ano' => $filters['ano'], 'mes' => $filters['mes'], 'holiday' => $holiday->id]) }}">Editar</a>
                                                <form method="POST" action="{{ route('calendarios.feriados.toggle-active', $holiday) }}" style="display: inline;">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="ano" value="{{ $filters['ano'] }}">
                                                    <input type="hidden" name="mes" value="{{ $filters['mes'] }}">
                                                    <button type="submit">{{ $holiday->is_active ? 'Inativar' : 'Ativar' }}</button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canManage ? 4 : 3 }}">Nenhum feriado ativo do RH para o mes selecionado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>

                <section class="card" style="background: #f9fbfd;">
                    <h3 style="margin-top: 0;">Legado do mes</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Nome</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($legacyHolidays as $holiday)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($holiday['date'])->format('d/m/Y') }}</td>
                                    <td>{{ $holiday['tipo'] ?: 'nacional' }}</td>
                                    <td>{{ $holiday['descricao'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">Nenhum feriado legado encontrado para o mes selecionado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            </div>
        </details>
    @endif
@endsection
