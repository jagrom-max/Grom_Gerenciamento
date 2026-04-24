<x-report.default
    title="Relatório de Mandados"
    :period="$period"
    :generatedAt="now()"
    origin="Operacional / Mandados"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="$brasaoSrc ?? asset('assets/brasao.png')"
    :logo-src="$logoSrc ?? asset('assets/logo_grom.png')"
    :watermark-src="$watermarkSrc ?? asset('assets/marca_dagua.png')"
>
    {{-- ── Toolbar (oculta na impressão) ──────────────────────────────── --}}
    <x-slot:toolbar>
        <a href="{{ route('operacional.mandados.index') }}" class="btn secondary" style="font-size:0.85rem;">← Mandados</a>

        <form method="GET" action="{{ route('operacional.mandados.relatorio') }}"
              style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; flex:1;">
            <div>
                <label style="font-size:0.75rem; display:block; margin-bottom:2px; color:#ccc;">Início</label>
                <input type="date" name="data_inicio" value="{{ $filters['data_inicio'] ?? '' }}"
                       style="font-size:0.82rem; padding:3px 6px;">
            </div>
            <div>
                <label style="font-size:0.75rem; display:block; margin-bottom:2px; color:#ccc;">Fim</label>
                <input type="date" name="data_fim" value="{{ $filters['data_fim'] ?? '' }}"
                       style="font-size:0.82rem; padding:3px 6px;">
            </div>
            <div>
                <label style="font-size:0.75rem; display:block; margin-bottom:2px; color:#ccc;">Tipo</label>
                <select name="tipo_sigla" style="font-size:0.82rem; padding:3px 6px;">
                    <option value="todos">Todos</option>
                    @foreach ($tiposSigla as $sigla => $desc)
                        <option value="{{ $sigla }}" @selected(($filters['tipo_sigla'] ?? '') === $sigla)>{{ $sigla }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:0.75rem; display:block; margin-bottom:2px; color:#ccc;">Procedimento</label>
                <select name="procedimento" style="font-size:0.82rem; padding:3px 6px;">
                    <option value="todos">Todos</option>
                    @foreach ($procedimentos as $proc)
                        <option value="{{ $proc }}" @selected(($filters['procedimento'] ?? '') === $proc)>{{ $proc }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex; align-items:center; gap:4px; padding-bottom:1px;">
                <input type="checkbox" id="cb-venc" name="vencidos" value="1"
                       @checked(!empty($filters['vencidos'])) style="margin:0;">
                <label for="cb-venc" style="font-size:0.78rem; color:#ccc; cursor:pointer;">Vencidos</label>
            </div>
            <button type="submit" style="font-size:0.82rem; padding:4px 12px;">Filtrar</button>
            <a href="{{ route('operacional.mandados.relatorio') }}"
               style="font-size:0.78rem; color:#aaa; align-self:center;">Limpar</a>
        </form>

        <a href="{{ route('operacional.mandados.relatorio.pdf', $filters) }}"
           style="font-size:0.85rem; white-space:nowrap; display:inline-flex; align-items:center;">
            Baixar PDF
        </a>
    </x-slot:toolbar>

    {{-- ── Summary cards ──────────────────────────────────────────────── --}}
    <x-slot:summary>
        <div class="report-summary-grid" style="grid-column:1 / -1;">
            <article class="card">
                <small>Total exibido</small>
                <strong>{{ $summary['total'] }}</strong>
            </article>
            <article class="card">
                <small>Em Aberto</small>
                <strong>{{ $summary['em_aberto'] }}</strong>
            </article>
            <article class="card">
                <small>Cumpridos</small>
                <strong>{{ $summary['cumpridos'] }}</strong>
            </article>
            <article class="card">
                <small>Revogados</small>
                <strong>{{ $summary['revogados'] }}</strong>
            </article>
        </div>
    </x-slot:summary>

    {{-- ── Breakdown por tipo ──────────────────────────────────────────── --}}
    @if ($porTipo->isNotEmpty())
        <p class="report-breakdown" style="font-size:8pt; margin-bottom:10px; color:#444;">
            <strong>Por tipo:</strong>
            @foreach ($porTipo as $sigla => $qtd)
                <span style="margin-right:10px;"><strong>{{ $sigla }}</strong>: {{ $qtd }}</span>
            @endforeach
        </p>
            @endif

    {{-- ── Tabela ──────────────────────────────────────────────────────── --}}
    @if ($mandados->isEmpty())
        <p style="font-style:italic; color:#666; font-size:9pt; text-align:center; padding:20px 0;">
            Nenhum mandado encontrado para os filtros selecionados.
        </p>
    @else
        @php
            $mandadosPorPagina = $mandados->values()->chunk(9);
        @endphp

        @foreach ($mandadosPorPagina as $pagina => $mandadosPagina)
            <section class="report-page" @if ($pagina > 0) style="break-before: page; page-break-before: always;" @endif>
                <table>
                    <thead>
                        <tr>
                            <th style="text-align:center; width:38px;">Tipo</th>
                            <th>CNJ</th>
                            <th>Nome / Alvo</th>
                            <th style="text-align:center;">Emissão</th>
                            <th style="text-align:center;">Validade</th>
                            <th>Tipificação</th>
                            <th>Procedimento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mandadosPagina as $m)
                            @php
                                $vencido = $m->procedimento === 'Em Aberto'
                                    && $m->validade !== null
                                    && $m->validade < $today;
                            @endphp
                            <tr>
                                <td style="text-align:center; font-weight:700; font-size:7.5pt;">
                                    {{ $m->tipo_sigla }}
                                </td>
                                <td style="font-size:7.5pt; white-space:nowrap;">{{ $m->cnj_numero ?: '—' }}</td>
                                <td>
                                    <strong style="font-size:8pt;">{{ $m->nome }}</strong>
                                    @if ($m->cpf_formatado)
                                        <br><span style="font-size:7pt; color:#666;">CPF: {{ $m->cpf_formatado }}</span>
                                    @endif
                                </td>
                                <td style="text-align:center; white-space:nowrap;">
                                    {{ $m->data_emissao?->format('d/m/Y') ?: '—' }}
                                </td>
                                <td style="text-align:center; white-space:nowrap; {{ $vencido ? 'color:#c0392b; font-weight:700;' : '' }}">
                                    {{ $m->validade?->format('d/m/Y') ?: '—' }}
                                    @if ($vencido)
                                        <br><span style="font-size:6.5pt;">⚠ VENCIDO</span>
                                    @endif
                                </td>
                                <td style="font-size:7.5pt;">
                                    @if ($m->tipificacao_penal)
                                        {{ $m->tipificacao_penal }}
                                        @if ($m->artigo)
                                            · Art. {{ $m->artigo }}{{ $m->paragrafo ? ', §' . $m->paragrafo : '' }}
                                        @endif
                                    @else
                                        —
                                    @endif
                                    @if (!empty($m->tipificacoes_extra))
                                        @foreach ($m->tipificacoes_extra as $extra)
                                            <br>+ {{ $extra['lei'] ?? '' }}{{ !empty($extra['artigo']) ? ' Art. ' . $extra['artigo'] : '' }}
                                        @endforeach
                                    @endif
                                </td>
                                <td style="font-size:7.5pt; line-height:1.35;">
                                    @if ($m->procedimento === 'Cumprido')
                                        <strong style="color:#155724;">Cumprido</strong>
                                        @if ($m->cumprido_por_exibicao !== '—')
                                            <br><span style="color:#444;">{{ $m->cumprido_por_exibicao }}</span>
                                        @endif
                                        @if ($m->data_cumprimento)
                                            <br><span style="color:#444;">{{ $m->data_cumprimento->format('d/m/Y') }}</span>
                                        @endif
                                        @if ($m->bo_numero)
                                            <br><span style="color:#555;">BO {{ $m->bo_numero }}</span>
                                        @endif
                                    @else
                                        <span style="font-weight:600; color: {{ $m->procedimento === 'Revogado' ? '#856404' : '#1a1a1a' }};">
                                            {{ $m->procedimento }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    @endif

</x-report.default>
