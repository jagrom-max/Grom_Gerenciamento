@extends('layouts.app')

@section('title', 'Analise de Dados | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Analise de Dados</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Entrada da consolidacao web, com fila auditavel, controle de qualidade e ponte direta para a produtividade.
            </p>
            @if (! empty($scopeNotice))
                <p class="muted" style="margin: 6px 0 0;">
                    <strong>{{ $scopeNotice }}</strong>
                </p>
            @endif
        </div>
    </div>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Lotes importados</small>
            <strong>{{ $metrics['lotes_total'] }}</strong>
            <span>Base de consolidacao recebida no piloto.</span>
        </article>
        <article class="card">
            <small>Itens pendentes</small>
            <strong>{{ $metrics['itens_pendentes'] }}</strong>
            <span>Fila aguardando confirmacao ou saneamento.</span>
        </article>
        <article class="card">
            <small>Confirmados</small>
            <strong>{{ $metrics['itens_confirmados'] }}</strong>
            <span>Itens que ja viraram flagrante consolidado.</span>
        </article>
        <article class="card">
            <small>Flagrantes ativos</small>
            <strong>{{ $metrics['flagrantes_ativos'] }}</strong>
            <span>Base operacional absorvida na produtividade.</span>
        </article>
        <article class="card">
            <small>Pendencias completas</small>
            <strong>{{ $metrics['itens_pendentes_completos'] }}</strong>
            <span>Itens com cartorio, SPJ, IP, CNJ e unidade.</span>
        </article>
    </div>

    <div class="section-head" style="margin-top: 8px;">
        <div>
            <h2>Qualidade da fila</h2>
            <p class="muted" style="margin: 6px 0 0;">
                Recorte rapido dos pontos que mais exigem saneamento na importacao atual.
            </p>
        </div>
    </div>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Sem cartorio</small>
            <strong>{{ $metrics['itens_sem_cartorio'] }}</strong>
            <span>Necessitam de vinculacao administrativa.</span>
        </article>
        <article class="card">
            <small>Sem SPJ</small>
            <strong>{{ $metrics['itens_sem_spj'] }}</strong>
            <span>Registros ainda sem identificador principal.</span>
        </article>
        <article class="card">
            <small>Sem IP</small>
            <strong>{{ $metrics['itens_sem_num_ip'] }}</strong>
            <span>Campos essenciais para a consolidacao.</span>
        </article>
        <article class="card">
            <small>Sem CNJ</small>
            <strong>{{ $metrics['itens_sem_num_cnj'] }}</strong>
            <span>Registros que pedem saneamento tecnico.</span>
        </article>
        <article class="card">
            <small>Sem unidade</small>
            <strong>{{ $metrics['itens_sem_lavrado_unidade'] }}</strong>
            <span>Itens sem classificacao de lavrado.</span>
        </article>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Pendências por origem</h2>
        <table>
            <thead>
                <tr>
                    <th>Origem</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($statusOriginBreakdown as $statusOrigin)
                    <tr>
                        <td>{{ $statusOrigin->status_origem_label }}</td>
                        <td>{{ (int) $statusOrigin->total }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2">Nenhuma origem registrada ainda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 18px;">
        <section class="card">
            <h2 style="margin-top: 0;">Origem dos lotes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Lotes</th>
                        <th>Linhas</th>
                        <th>Staged</th>
                        <th>Atualizados</th>
                        <th>Erros</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sourceBreakdown as $source)
                        <tr>
                            <td>{{ $source->source_type_label }}</td>
                            <td>{{ (int) $source->batches_total }}</td>
                            <td>{{ (int) $source->total_rows }}</td>
                            <td>{{ (int) $source->rows_staged }}</td>
                            <td>{{ (int) $source->rows_updated }}</td>
                            <td>{{ (int) $source->error_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Nenhuma origem registrada ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Cartorios em atencao</h2>
            <table>
                <thead>
                    <tr>
                        <th>Cartorio</th>
                        <th>Pendentes</th>
                        <th>Confirmados</th>
                        <th>Rejeitados</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cartorioBreakdown as $cartorio)
                        <tr>
                            <td>
                                <strong>{{ $cartorio->name }}</strong><br>
                                <span class="muted">{{ $cartorio->designacao ?: 'Sem designacao' }}</span>
                            </td>
                            <td>{{ (int) $cartorio->pending_import_items_count }}</td>
                            <td>{{ (int) $cartorio->confirmed_import_items_count }}</td>
                            <td>{{ (int) $cartorio->rejected_import_items_count }}</td>
                            <td>{{ (int) $cartorio->import_items_total_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Nenhum cartorio registrado ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <div class="grid" style="grid-template-columns: 1.05fr .95fr; margin-bottom: 18px;">
        <section class="card">
            <h2 style="margin-top: 0;">Ponto de migracao</h2>
            <div class="grid">
                <div class="tag good">Consolidacao de Excel</div>
                <div class="tag good">Sincronizacao do legado SQLite</div>
                <div class="tag good">Fila de sugestoes auditavel</div>
                <div class="tag good">Vinculo manual por cartorio</div>
                <div class="tag good">Aproximacao direta com produtividade</div>
                <div class="tag good">Leitura de completude dos registros</div>
                <div class="tag good">Escopo por cartorio respeitado</div>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Acoes rapidas</h2>
            <div class="actions" style="margin-bottom: 12px;">
                <a class="btn" href="{{ route('produtividade.flagrantes.index') }}">Abrir fila de flagrantes</a>
                <a class="btn secondary" href="{{ route('relatorios.index') }}">Abrir relatorios</a>
                <a class="btn secondary" href="{{ route('analise.exports.pending') }}">Exportar pendencias</a>
                <a class="btn" href="{{ route('analise.bos.import') }}">Importar planilha de BOs</a>
                <a class="btn secondary" href="{{ route('analise.bos.search') }}">Pesquisar vítima / autor</a>
                <a class="btn secondary" href="{{ route('analise.estatisticas') }}">Estatísticas avançadas</a>
                <a class="btn secondary" href="{{ route('analise.relatorios.index') }}"
                   style="border-color:#1d4ed8; color:#1d4ed8;">Relatórios de Análise</a>
                <a class="btn secondary" href="{{ route('analise.bos.auditoria-flagrantes') }}"
                   style="border-color:#f59e0b; color:#b45309;">Auditoria de flagrantes</a>
            </div>
            <p class="muted" style="margin: 0;">
                Esta tela funciona como o ponto de leitura da migração de Análise de Dados, sem duplicar timbrado ou regra visual.
            </p>
        </section>
    </div>

    {{-- ═════════════════════════════════════════════════════════════════════
         BANCO PHP — estatísticas dos BOs importados no sistema web
         ═════════════════════════════════════════════════════════════════════ --}}
    @if (($phpBoStats->total ?? 0) > 0)
        <div class="section-head" style="margin-top: 8px;">
            <div>
                <h2>Base analise_bos — banco web</h2>
                <p class="muted" style="margin: 6px 0 0;">
                    Dados dos BOs importados para o banco PHP. Total: {{ number_format($phpBoStats->total) }} BOs.
                </p>
            </div>
        </div>

        <div class="cards" style="margin-bottom: 18px;">
            <article class="card">
                <small>Total de BOs</small>
                <strong>{{ number_format($phpBoStats->total) }}</strong>
                <span>Boletins importados no banco web.</span>
            </article>
            <article class="card">
                <small>Com MPU</small>
                <strong>{{ number_format($phpBoStats->com_mpu) }}</strong>
                <span>BOs com processo no MP / Tribunal.</span>
            </article>
            <article class="card">
                <small>Com IP</small>
                <strong>{{ number_format($phpBoStats->com_ip) }}</strong>
                <span>Inquéritos policiais vinculados.</span>
            </article>
            <article class="card">
                <small>Flagrantes</small>
                <strong>{{ number_format($phpBoStats->flagrantes) }}</strong>
                <span>BOs com lavrado de flagrante.</span>
            </article>
            <article class="card">
                <small>Atos infracionais</small>
                <strong>{{ number_format($phpBoStats->atos_infracionais) }}</strong>
                <span>BOs registrados como ato infracional.</span>
            </article>
        </div>

        <div class="grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 18px;">

            @if ($phpBoNaturezas->isNotEmpty())
            <section class="card">
                <h2 style="margin-top: 0;">Top naturezas — banco web</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Natureza</th>
                            <th style="text-align:right;">Total</th>
                            <th style="text-align:right;">Tent.</th>
                            <th style="text-align:right;">Cons.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($phpBoNaturezas as $nat)
                            <tr>
                                <td>{{ $nat->natureza_label }}</td>
                                <td style="text-align:right;">{{ $nat->total }}</td>
                                <td style="text-align:right;">{{ $nat->tentado }}</td>
                                <td style="text-align:right;">{{ $nat->consumado }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
            @endif

            @if ($phpBoAreas->isNotEmpty())
            <section class="card">
                <h2 style="margin-top: 0;">BOs por área — banco web</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Área do fato</th>
                            <th style="text-align:right;">Total</th>
                            <th style="text-align:right;">Flagrantes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($phpBoAreas as $area)
                            <tr>
                                <td>{{ $area->area }}</td>
                                <td style="text-align:right;">{{ $area->total }}</td>
                                <td style="text-align:right;">{{ $area->flagrantes }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
            @endif

        </div>
    @endif

    {{-- ═════════════════════════════════════════════════════════════════════
         BANCO LEGADO — estatísticas ao vivo do banco SQLite principal
         (leitura somente leitura, conexão por demanda, sem impacto de performance)
         ═════════════════════════════════════════════════════════════════════ --}}
    @if (!empty($legadoStats) && ($legadoStats['total'] ?? 0) > 0)
        <div class="section-head" style="margin-top: 8px;">
            <div>
                <h2>Banco legado — BOs em tempo real</h2>
                <p class="muted" style="margin: 6px 0 0;">
                    Dados lidos diretamente do banco Python (somente leitura). Base oficial: {{ number_format($legadoStats['total']) }} BOs.
                </p>
            </div>
        </div>

        <div class="cards" style="margin-bottom: 18px;">
            <article class="card">
                <small>Total de BOs</small>
                <strong>{{ number_format($legadoStats['total']) }}</strong>
                <span>Ocorrências registradas no legado.</span>
            </article>
            <article class="card">
                <small>Com número MPU</small>
                <strong>{{ number_format($legadoStats['com_mpu']) }}</strong>
                <span>BOs com processo no MP ou Tribunal.</span>
            </article>
            <article class="card">
                <small>Com número IP</small>
                <strong>{{ number_format($legadoStats['com_ip']) }}</strong>
                <span>Inquéritos policiais vinculados.</span>
            </article>
            <article class="card">
                <small>Flagrantes</small>
                <strong>{{ number_format($legadoStats['flagrantes']) }}</strong>
                <span>BOs com lavrado de flagrante.</span>
            </article>
        </div>

        <div class="grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 18px;">

            @if (!empty($legadoNaturezas))
            <section class="card">
                <h2 style="margin-top: 0;">Top naturezas (BOs)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Natureza</th>
                            <th style="text-align:right;">Total</th>
                            <th style="text-align:right;">Tentados</th>
                            <th style="text-align:right;">Consumados</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($legadoNaturezas as $nat)
                            <tr>
                                <td>{{ $nat['natureza_label'] }}</td>
                                <td style="text-align:right;">{{ $nat['total'] }}</td>
                                <td style="text-align:right;">{{ $nat['tentado'] }}</td>
                                <td style="text-align:right;">{{ $nat['consumado'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
            @endif

            @if (!empty($legadoAreas))
            <section class="card">
                <h2 style="margin-top: 0;">BOs por área do fato</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Área</th>
                            <th style="text-align:right;">Total</th>
                            <th style="text-align:right;">Flagrantes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($legadoAreas as $area)
                            <tr>
                                <td>{{ $area['area'] }}</td>
                                <td style="text-align:right;">{{ $area['total'] }}</td>
                                <td style="text-align:right;">{{ $area['flagrantes'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
            @endif

        </div>

        @if (!empty($legadoStats['cartorios']))
            <section class="card" style="margin-bottom: 18px;">
                <h2 style="margin-top: 0;">BOs por cartório IP (legado)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Cartório</th>
                            <th style="text-align:right;">BOs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($legadoStats['cartorios'] as $cartorio => $total)
                            <tr>
                                <td>{{ $cartorio }}</td>
                                <td style="text-align:right;">{{ $total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    @endif

    <section class="card">
        <h2 style="margin-top: 0;">Ultimos lotes</h2>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Arquivo</th>
                    <th>Itens</th>
                    <th>Completo</th>
                    <th>Total</th>
                    <th>Pendentes</th>
                    <th>Confirmados</th>
                    <th>Rejeitados</th>
                    <th>Staged</th>
                    <th>Atualizados</th>
                    <th>Erros</th>
                    <th>Detalhe</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentBatches as $batch)
                    <tr>
                        <td>{{ $batch->imported_at?->format('d/m/Y H:i') ?? $batch->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            <strong>{{ $batch->source_name }}</strong><br>
                            <span class="muted">{{ $batch->source_type ?: 'Origem não informada' }}</span>
                        </td>
                        <td>{{ (int) $batch->items_count }}</td>
                        <td>{{ (int) $batch->quality_complete_items_count }}</td>
                        <td>{{ (int) $batch->total_rows }}</td>
                        <td>{{ (int) $batch->pending_items_count }}</td>
                        <td>{{ (int) $batch->confirmed_items_count }}</td>
                        <td>{{ (int) $batch->rejected_items_count }}</td>
                        <td>{{ (int) $batch->rows_staged }}</td>
                        <td>{{ (int) $batch->rows_updated }}</td>
                        <td>{{ (int) $batch->error_count }}</td>
                        <td><a class="btn secondary" href="{{ route('analise.batches.show', $batch) }}">Abrir</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12">Nenhum lote registrado ainda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="card" style="margin-top: 18px;">
        <h2 style="margin-top: 0;">Pendencias recentes</h2>
        <table>
            <thead>
                <tr>
                    <th>SPJ / ID</th>
                    <th>Cartorio</th>
                    <th>Origem</th>
                    <th>Referencia</th>
                    <th>Unidade</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentPendingItems as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->spj ?: $item->source_process_key }}</strong><br>
                            <span class="muted">{{ $item->batch?->source_name }}</span><br>
                            <span class="muted">{{ $item->batch?->source_type ?: 'Origem não informada' }}</span>
                        </td>
                        <td>
                            {{ $item->cartorio?->name ?: 'Sem cartorio' }}<br>
                            <span class="muted">{{ $item->cartorio_hint ?: 'Não informado' }}</span>
                        </td>
                        <td>
                            {{ $item->status_origem ?: 'Não informado' }}<br>
                            <span class="muted">{{ data_get($item->payload, 'kind') ?: 'Sem payload tipado' }}</span>
                        </td>
                        <td>
                            {{ $item->reference_year ?? '----' }}/{{ $item->reference_month ? str_pad((string) $item->reference_month, 2, '0', STR_PAD_LEFT) : '--' }}<br>
                            <span class="muted">{{ $item->data_fato?->format('d/m/Y') ?? 'N/A' }}</span>
                        </td>
                        <td>{{ $item->lavrado_unidade?->label() ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Nenhuma pendência recente.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
