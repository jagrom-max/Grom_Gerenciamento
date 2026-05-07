@extends('layouts.app')

@section('title', 'Lote | Analise de Dados | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Detalhe do lote</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Conferencia por lote com trilha completa de consolidacao, pendencias, qualidade e resultado final.
            </p>
            @if (! empty($scopeNotice))
                <p class="muted" style="margin: 6px 0 0;">
                    <strong>{{ $scopeNotice }}</strong>
                </p>
            @endif
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('analise.index') }}">Voltar para analise</a>
            <a class="btn secondary" href="{{ route('produtividade.flagrantes.index') }}">Abrir produtividade</a>
            <a class="btn secondary" href="{{ route('analise.exports.batch', $batch) }}">Exportar CSV</a>
        </div>
    </div>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Itens totais</small>
            <strong>{{ $summary['total'] }}</strong>
            <span>Quantidade de registros visiveis no lote.</span>
        </article>
        <article class="card">
            <small>Pendentes</small>
            <strong>{{ $summary['pending'] }}</strong>
            <span>Itens aguardando saneamento.</span>
        </article>
        <article class="card">
            <small>Confirmados</small>
            <strong>{{ $summary['confirmed'] }}</strong>
            <span>Itens ja promovidos para produtividade.</span>
        </article>
        <article class="card">
            <small>Rejeitados</small>
            <strong>{{ $summary['rejected'] }}</strong>
            <span>Itens descartados por criterio operacional.</span>
        </article>
        <article class="card">
            <small>Completos</small>
            <strong>{{ $summary['complete'] }}</strong>
            <span>Registros com cartorio, SPJ, IP, CNJ e unidade.</span>
        </article>
    </div>

    <div class="grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 18px;">
        <section class="card">
            <h2 style="margin-top: 0;">Dados do lote</h2>
            <table>
                <tbody>
                    <tr>
                        <th>Arquivo</th>
                        <td>{{ $batch->source_name }}</td>
                    </tr>
                    <tr>
                        <th>Tipo de origem</th>
                        <td>{{ $batch->source_type ?: 'Origem não informada' }}</td>
                    </tr>
                    <tr>
                        <th>Planilha</th>
                        <td>{{ $batch->sheet_name ?: 'Não informada' }}</td>
                    </tr>
                    <tr>
                        <th>Linha de cabecalho</th>
                        <td>{{ $batch->header_row ?: 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Periodo</th>
                        <td>
                            {{ $batch->source_period_start?->format('d/m/Y') ?? 'N/A' }}
                            ate
                            {{ $batch->source_period_end?->format('d/m/Y') ?? 'N/A' }}
                        </td>
                    </tr>
                    <tr>
                        <th>Hash de origem</th>
                        <td>{{ $batch->source_hash ?: 'Nao informado' }}</td>
                    </tr>
                    <tr>
                        <th>Importado em</th>
                        <td>{{ $batch->imported_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Processado em</th>
                        <td>{{ $batch->processed_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Linhas totais</th>
                        <td>{{ (int) $batch->total_rows }}</td>
                    </tr>
                    <tr>
                        <th>Linhas staged</th>
                        <td>{{ (int) $batch->rows_staged }}</td>
                    </tr>
                    <tr>
                        <th>Linhas atualizadas</th>
                        <td>{{ (int) $batch->rows_updated }}</td>
                    </tr>
                    <tr>
                        <th>Linhas ignoradas</th>
                        <td>{{ (int) $batch->rows_skipped }}</td>
                    </tr>
                    <tr>
                        <th>Erros</th>
                        <td>{{ (int) $batch->error_count }}</td>
                    </tr>
                    <tr>
                        <th>Observacoes</th>
                        <td>{{ $batch->notes ?: 'Sem observacoes' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Diagnostico de qualidade</h2>
            <div class="grid">
                <div class="tag good">Total: {{ $summary['total'] }}</div>
                <div class="tag good">Pendentes: {{ $summary['pending'] }}</div>
                <div class="tag good">Confirmados: {{ $summary['confirmed'] }}</div>
                <div class="tag good">Rejeitados: {{ $summary['rejected'] }}</div>
                <div class="tag good">Completos: {{ $summary['complete'] }}</div>
                <div class="tag good">Sem cartorio: {{ $summary['without_cartorio'] }}</div>
                <div class="tag good">Sem SPJ: {{ $summary['without_spj'] }}</div>
                <div class="tag good">Sem IP: {{ $summary['without_num_ip'] }}</div>
                <div class="tag good">Sem CNJ: {{ $summary['without_num_cnj'] }}</div>
                <div class="tag good">Sem unidade: {{ $summary['without_lavrado_unidade'] }}</div>
            </div>
            <p class="muted" style="margin: 18px 0 0; line-height: 1.6;">
                Este detalhe ajuda a auditar cada lote antes de seguir para a fila operacional de produtividade.
            </p>
        </section>
    </div>

    <section class="card">
        <h2 style="margin-top: 0;">Itens do lote</h2>
        <table>
            <thead>
                <tr>
                    <th>Data / Ref.</th>
                    <th>SPJ / Identificador</th>
                    <th>Cartorio</th>
                    <th>Origem / Status</th>
                    <th>Unidade</th>
                    <th>IP / IPE / CNJ</th>
                    <th>Detalhes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>
                            {{ $item->data_fato?->format('d/m/Y') ?? 'N/A' }}<br>
                            <span class="muted">
                                {{ $item->reference_year ?? '----' }}/{{ $item->reference_month ? str_pad((string) $item->reference_month, 2, '0', STR_PAD_LEFT) : '--' }}
                            </span>
                        </td>
                        <td>
                            <strong>{{ $item->spj ?: $item->source_process_key }}</strong><br>
                            <span class="muted">{{ $item->naturezas ?: 'Sem natureza informada' }}</span>
                        </td>
                        <td>
                            {{ $item->cartorio?->name ?: 'Sem cartorio' }}<br>
                            <span class="muted">{{ $item->cartorio_hint ?: 'Não mapeado' }}</span>
                        </td>
                        <td>
                            <span class="tag {{ $item->import_status->value === 'pending' ? 'warn' : 'good' }}">
                                {{ $item->import_status->name }}
                            </span><br>
                            <span class="muted">{{ $item->status_origem ?: 'Origem nao informada' }}</span>
                        </td>
                        <td>{{ $item->lavrado_unidade?->label() ?? 'N/A' }}</td>
                        <td>
                            <strong>{{ $item->num_ip ?: 'N/A' }}</strong><br>
                            <span class="muted">{{ $item->num_ipe ?: 'Sem IPE' }}</span><br>
                            <span class="muted">{{ $item->num_cnj ?: 'N/A' }}</span>
                        </td>
                        <td>
                            <span class="muted">Payload: {{ data_get($item->payload, 'source') ?: 'N/A' }}</span><br>
                            <span class="muted">Tipo: {{ data_get($item->payload, 'kind') ?: 'N/A' }}</span>
                            @if ($item->import_status->value === 'rejected' && filled($item->rejected_reason))
                                <br><span class="muted">Motivo: {{ $item->rejected_reason }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">Nenhum item associado ao lote.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection

