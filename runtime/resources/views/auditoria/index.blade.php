@extends('layouts.app')

@section('title', 'Auditoria | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Auditoria operacional</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Consulta central da trilha de eventos do sistema, com filtros simples e paginação para uso interno.
            </p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('auditoria.export', request()->query()) }}">Exportar CSV</a>
            <a class="btn secondary" href="{{ route('dashboard') }}">Voltar ao dashboard</a>
            <a class="btn secondary" href="{{ route('relatorios.index') }}">Abrir relatorios</a>
        </div>
    </div>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Eventos filtrados</small>
            <strong>{{ $summary['total'] }}</strong>
            <span>Eventos dentro dos criterios atuais.</span>
        </article>
        <article class="card">
            <small>Modulos representados</small>
            <strong>{{ $summary['modules'] }}</strong>
            <span>Base de rastreio distribuida no sistema.</span>
        </article>
        <article class="card">
            <small>Usuarios ativos na trilha</small>
            <strong>{{ $summary['actors'] }}</strong>
            <span>Quantidade de contas que produziram eventos.</span>
        </article>
        <article class="card">
            <small>Ultimo evento</small>
            <strong>{{ $summary['latest'] ? \Illuminate\Support\Carbon::parse($summary['latest'])->format('d/m H:i') : 'N/A' }}</strong>
            <span>Registro mais recente dentro do filtro aplicado.</span>
        </article>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Filtros</h2>
        <form method="GET" action="{{ route('auditoria.index') }}" class="form-grid">
            <div class="field full">
                <label for="q">Busca geral</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Modulo, evento, entidade, descricao, usuario ou IP">
            </div>
            <div class="field">
                <label for="module_code">Modulo</label>
                <input id="module_code" name="module_code" value="{{ $filters['module_code'] ?? '' }}" placeholder="ex.: access, analise, produtividade">
            </div>
            <div class="field">
                <label for="event_type">Evento</label>
                <input id="event_type" name="event_type" value="{{ $filters['event_type'] ?? '' }}" placeholder="ex.: users.create, flagrantes.import">
            </div>
            <div class="field">
                <label for="entity_type">Entidade</label>
                <input id="entity_type" name="entity_type" value="{{ $filters['entity_type'] ?? '' }}" placeholder="ex.: user, role, import_batch">
            </div>
            <div class="field">
                <label for="actor_username">Usuario</label>
                <input id="actor_username" name="actor_username" value="{{ $filters['actor_username'] ?? '' }}" placeholder="username ex.: admin">
            </div>
            <div class="field">
                <label for="source_ip">IP de origem</label>
                <input id="source_ip" name="source_ip" value="{{ $filters['source_ip'] ?? '' }}" placeholder="127.0.0.1">
            </div>
            <div class="field">
                <label for="date_from">Data inicial</label>
                <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="field">
                <label for="date_to">Data final</label>
                <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="field full">
                <div class="actions">
                    <button type="submit">Aplicar filtros</button>
                    <a class="btn secondary" href="{{ route('auditoria.index') }}">Limpar</a>
                </div>
            </div>
        </form>
    </section>

    <div class="grid" style="grid-template-columns: .88fr 1.12fr; margin-bottom: 18px;">
        <section class="card">
            <h2 style="margin-top: 0;">Distribuicao por modulo</h2>
            <table>
                <thead>
                    <tr>
                        <th>Modulo</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($moduleBreakdown as $module)
                        <tr>
                            <td><strong>{{ $module->module_code }}</strong></td>
                            <td>{{ (int) $module->total }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">Nenhum modulo registrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Trilha recente</h2>
            <table>
                <thead>
                    <tr>
                        <th>Quando</th>
                        <th>Usuario</th>
                        <th>Evento</th>
                        <th>Entidade</th>
                        <th>Descricao</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>{{ $event->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td>
                                <strong>{{ $event->actor?->username ?? 'Sistema' }}</strong><br>
                                <span class="muted">{{ $event->source_ip ?: 'IP não informado' }}</span>
                            </td>
                            <td>
                                <span class="tag good">{{ $event->module_code }}</span><br>
                                <span class="muted">{{ $event->event_type }}</span>
                            </td>
                            <td>
                                {{ $event->entity_type }}<br>
                                <span class="muted">{{ $event->entity_id }}</span>
                            </td>
                            <td>
                                {{ $event->description ?: 'Sem descricao' }}
                                @if (! empty($event->metadata))
                                    <details style="margin-top: 10px;">
                                        <summary>Ver metadados</summary>
                                        <pre style="margin: 10px 0 0; white-space: pre-wrap;">{{ json_encode($event->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Nenhum evento encontrado com os filtros atuais.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="grid" style="grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 14px;">
                <section class="card" style="padding: 14px;">
                    <h3 style="margin-top: 0;">Eventos por tipo</h3>
                    <div class="grid">
                        @forelse ($eventTypes as $eventType)
                            <div class="actions" style="justify-content: space-between;">
                                <span>{{ $eventType->event_type }}</span>
                                <strong>{{ (int) $eventType->total }}</strong>
                            </div>
                        @empty
                            <p class="muted" style="margin: 0;">Nenhum evento consolidado.</p>
                        @endforelse
                    </div>
                </section>
                <section class="card" style="padding: 14px;">
                    <h3 style="margin-top: 0;">Exportacao rapida</h3>
                    <p class="muted" style="margin-top: 0;">
                        A exportacao respeita os filtros atuais e gera um CSV pronto para conferencia, auditoria ou arquivamento.
                    </p>
                    <div class="actions">
                        <a class="btn" href="{{ route('auditoria.export', request()->query()) }}">Baixar CSV</a>
                    </div>
                </section>
            </div>

            <div class="actions" style="justify-content: space-between; margin-top: 14px;">
                <div class="muted">
                    Pagina {{ $events->currentPage() }} de {{ $events->lastPage() }} | {{ $events->total() }} registros
                </div>
                <div class="actions">
                    @if ($events->onFirstPage())
                        <span class="btn secondary" style="opacity: .55; cursor: not-allowed;">Anterior</span>
                    @else
                        <a class="btn secondary" href="{{ $events->previousPageUrl() }}">Anterior</a>
                    @endif

                    @if ($events->hasMorePages())
                        <a class="btn secondary" href="{{ $events->nextPageUrl() }}">Proxima</a>
                    @else
                        <span class="btn secondary" style="opacity: .55; cursor: not-allowed;">Proxima</span>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
