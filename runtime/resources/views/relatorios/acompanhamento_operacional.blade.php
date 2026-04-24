@php
    $compact = $compact ?? false;
    $selectedCartorio = $dashboard['selectedCartorio'] ?? null;
    $cartorioLabel = $selectedCartorio
        ? str_pad((string) $selectedCartorio->number, 3, '0', STR_PAD_LEFT) . ' - ' . $selectedCartorio->name
        : 'Todos os cartorios visiveis';
    $rhSummary = $dashboard['rhSummary'] ?? [];
    $legacySummary = $legacyPeople['summary'] ?? [];
    $scaleSummary = $scaleSnapshot['summary'] ?? [];
    $cartoriosPreview = $dashboard['cartoriosPreview'] ?? collect();
    $legacyEmployees = $legacyPeople['employees'] ?? [];
    $legacyScaleRows = $scaleSnapshot['scale_rows'] ?? [];
    $rhEmployees = $dashboard['rhFuncionariosPreview'] ?? collect();
    $rhHolidays = $dashboard['rhHolidaysPreview'] ?? collect();
@endphp

<x-report.default
    title="Acompanhamento Operacional Integrado"
    :period="$periodLabel"
    :generatedAt="$generatedAt"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="$brasaoSrc"
    :logo-src="$logoSrc"
    :watermark-src="$watermarkSrc"
>
    <x-slot:toolbar>
        <a href="{{ route('relatorios.index') }}">Voltar aos relatorios</a>
        <a href="{{ route('relatorios.operacional.integrado.pdf', ['year' => $year, 'month' => $month, 'cartorio_id' => $selectedCartorio?->id]) }}">Baixar PDF</a>
    </x-slot:toolbar>

    <x-slot:summary>
        <article class="card">
            <small>Cartorios visiveis</small>
            <strong>{{ $dashboard['summary']['cartorios_visiveis'] }}</strong>
            <span>Escopo da consulta com RBAC aplicado.</span>
        </article>
        <article class="card">
            <small>Pendencias abertas</small>
            <strong>{{ $dashboard['summary']['pendencias_abertas'] }}</strong>
            <span>Fila ativa do periodo selecionado.</span>
        </article>
        <article class="card">
            <small>Funcionarios RH</small>
            <strong>{{ $rhSummary['funcionarios_total'] ?? 0 }}</strong>
            <span>{{ $rhSummary['funcionarios_ativos'] ?? 0 }} ativos no espelho PHP.</span>
        </article>
        <article class="card">
            <small>Escala legada</small>
            <strong>{{ $scaleSummary['dias_total'] ?? 0 }}</strong>
            <span>Linhas mensais do Python consultadas em leitura.</span>
        </article>
        <article class="card">
            <small>Legado pessoas</small>
            <strong>{{ $legacySummary['total'] ?? 0 }}</strong>
            <span>{{ $legacySummary['ativos'] ?? 0 }} ativos no espelho Python.</span>
        </article>
        <article class="card">
            <small>Flagrantes no periodo</small>
            <strong>{{ $dashboard['selectedStats']['flagrantes_total'] }}</strong>
            <span>Base consolidada da produtividade.</span>
        </article>
    </x-slot:summary>

    <section style="margin-bottom: 6mm;">
        <h2 style="margin: 0 0 3mm; font-size: 10.5pt;">Cartorios reais espelhados</h2>
        <table style="font-size: 8.5pt; table-layout: fixed;">
            <thead>
                <tr>
                    <th style="width: 24%;">Cartorio</th>
                    <th style="width: 18%;">Responsavel</th>
                    <th style="width: 12%;">Periodo</th>
                    <th style="width: 8%;">IP</th>
                    <th style="width: 8%;">Relat.</th>
                    <th style="width: 8%;">Conc.</th>
                    <th style="width: 10%;">Registros</th>
                    <th style="width: 10%;">Andamento</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cartoriosPreview->take(4) as $row)
                    <tr>
                        <td>
                            <strong>{{ str_pad((string) $row['cartorio']->number, 3, '0', STR_PAD_LEFT) }} - {{ $row['cartorio']->name }}</strong><br>
                            <span style="color: #5a6a7a;">{{ $row['cartorio']->code }}</span><br>
                            <span style="color: #5a6a7a;">{{ $row['has_stats'] ? 'Com estatistica' : 'Sem estatistica' }}</span>
                        </td>
                        <td>
                            {{ $row['cartorio']->manager_name ?: 'Nao informado' }}<br>
                            <span style="color: #5a6a7a;">{{ $row['cartorio']->designacao ?: 'Sem designacao' }}</span>
                        </td>
                        <td>{{ $row['period_label'] }}</td>
                        <td>{{ $row['ip_instaurados'] }}</td>
                        <td>{{ $row['ip_relatados'] }}</td>
                        <td>{{ $row['concluidos'] }}</td>
                        <td>{{ $row['registros'] }}</td>
                        <td>{{ $row['ips_andamento'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">Nenhum cartorio visivel para o periodo selecionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section style="margin-bottom: 6mm;">
        <table style="font-size: 8.7pt;">
            <thead>
                <tr>
                    <th style="width: 28%;">Indicador</th>
                    <th style="width: 18%;">Valor</th>
                    <th>Leitura</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Periodo</td>
                    <td>{{ $periodLabel }}</td>
                    <td>Base filtrada para validacao operacional.</td>
                </tr>
                <tr>
                    <td>Cartorio</td>
                    <td>{{ $cartorioLabel }}</td>
                    <td>{{ $selectedCartorio?->code ?: 'Todos os cartorios visiveis' }}</td>
                </tr>
                <tr>
                    <td>Pendencias 7 dias</td>
                    <td>{{ $dashboard['summary']['pendencias_7d'] }}</td>
                    <td>Itens que merecem saneamento prioritario.</td>
                </tr>
                <tr>
                    <td>Pendencias 30 dias</td>
                    <td>{{ $dashboard['summary']['pendencias_30d'] }}</td>
                    <td>Fila com maior risco de represamento.</td>
                </tr>
                <tr>
                    <td>Lotes com erro 30 dias</td>
                    <td>{{ $dashboard['summary']['lotes_com_erro_30d'] }}</td>
                    <td>Entradas que precisam revisao ou reprocessamento.</td>
                </tr>
            </tbody>
        </table>
    </section>

    @unless ($compact)
        <section style="margin-bottom: 6mm;">
            <table style="font-size: 8.2pt; table-layout: fixed;">
                <thead>
                    <tr>
                        <th style="width: 28%;">Base</th>
                        <th style="width: 18%;">Python</th>
                        <th style="width: 18%;">PHP</th>
                        <th>Leitura</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Funcionarios totais</td>
                        <td>{{ $legacySummary['total'] ?? 0 }}</td>
                        <td>{{ $rhSummary['funcionarios_total'] ?? 0 }}</td>
                        <td>Confronto direto do cadastro de pessoas.</td>
                    </tr>
                    <tr>
                        <td>Funcionarios ativos</td>
                        <td>{{ $legacySummary['ativos'] ?? 0 }}</td>
                        <td>{{ $rhSummary['funcionarios_ativos'] ?? 0 }}</td>
                        <td>Base operacional disponivel para uso imediato.</td>
                    </tr>
                    <tr>
                        <td>Concorrem a escala</td>
                        <td>{{ $legacySummary['concorrem_escala'] ?? 0 }}</td>
                        <td>{{ $rhSummary['funcionarios_concorrem'] ?? 0 }}</td>
                        <td>Critério ja consolidado no legado e no espelho web.</td>
                    </tr>
                    <tr>
                        <td>Afastamentos em vigor</td>
                        <td>{{ $legacySummary['em_afastamento'] ?? 0 }}</td>
                        <td>{{ $rhSummary['afastamentos_em_vigor'] ?? 0 }}</td>
                        <td>Ausencias ativas e licencas consultadas em leitura.</td>
                    </tr>
                    <tr>
                        <td>Feriados ativos</td>
                        <td>{{ $scaleSummary['feriados_mes'] ?? 0 }}</td>
                        <td>{{ $rhSummary['feriados_ativos'] ?? 0 }}</td>
                        <td>Calendario legado e calendario de RH alinhados.</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section style="margin-bottom: 6mm;">
            <table style="font-size: 8.2pt; table-layout: fixed;">
                <thead>
                    <tr>
                        <th style="width: 34%;">Funcionarios do RH</th>
                        <th style="width: 33%;">Legado Python</th>
                        <th style="width: 33%;">Escala do periodo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            @forelse ($rhEmployees->take(4) as $funcionario)
                                <div style="margin-bottom: 3mm;">
                                    <strong>{{ $funcionario->matricula }}</strong> - {{ $funcionario->name }}<br>
                                    <span style="color: #5a6a7a;">{{ $funcionario->cargo?->name ?: 'Sem cargo' }} | {{ $funcionario->sector ?: 'Sem setor' }}</span>
                                </div>
                            @empty
                                Nenhum funcionario carregado.
                            @endforelse
                        </td>
                        <td>
                            @forelse (array_slice($legacyEmployees, 0, 4) as $employee)
                                <div style="margin-bottom: 3mm;">
                                    <strong>{{ $employee['legacy_key'] ?? 'LEG' }}</strong> - {{ $employee['nome'] ?? 'Nao informado' }}<br>
                                    <span style="color: #5a6a7a;">{{ $employee['cargo'] ?? 'Sem cargo' }} | {{ $employee['setor'] ?? 'Sem setor' }}</span>
                                </div>
                            @empty
                                Nenhum funcionario legado carregado.
                            @endforelse
                        </td>
                        <td>
                            @forelse (array_slice($legacyScaleRows, 0, 4) as $row)
                                <div style="margin-bottom: 3mm;">
                                    <strong>{{ $row['date_label'] ?? 'N/D' }}</strong> - {{ $row['escrivao'] ?: 'Livre' }}<br>
                                    <span style="color: #5a6a7a;">Operacional: {{ $row['operacional'] ?: 'Livre' }} | Delegada: {{ $row['delegada'] ?: 'Livre' }}</span>
                                </div>
                            @empty
                                Sem leitura da escala legada.
                            @endforelse
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section style="margin-bottom: 6mm;">
            <table style="font-size: 8.2pt; table-layout: fixed;">
                <thead>
                    <tr>
                        <th style="width: 34%;">Pendencias</th>
                        <th style="width: 33%;">Lotes recentes</th>
                        <th style="width: 33%;">Feriados proximos</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            @forelse ($dashboard['pendingItems']->take(4) as $row)
                                <div style="margin-bottom: 3mm;">
                                    <strong>{{ $row['item']->spj ?: $row['item']->source_process_key }}</strong><br>
                                    <span style="color: #5a6a7a;">{{ $row['item']->cartorio?->name ?: 'Sem cartorio' }} | {{ $row['age_days'] }} dias</span>
                                </div>
                            @empty
                                Nenhuma pendencia aberta.
                            @endforelse
                        </td>
                        <td>
                            @forelse ($dashboard['recentBatches']->take(4) as $batch)
                                <div style="margin-bottom: 3mm;">
                                    <strong>{{ $batch->source_name }}</strong><br>
                                    <span style="color: #5a6a7a;">{{ $batch->imported_at?->format('d/m/Y H:i') }} | Erros {{ $batch->error_count }}</span>
                                </div>
                            @empty
                                Nenhum lote recente encontrado.
                            @endforelse
                        </td>
                        <td>
                            @forelse ($rhHolidays->take(4) as $holiday)
                                <div style="margin-bottom: 3mm;">
                                    <strong>{{ $holiday->holiday_date?->format('d/m/Y') }}</strong> - {{ $holiday->name }}<br>
                                    <span style="color: #5a6a7a;">{{ $holiday->scope }}</span>
                                </div>
                            @empty
                                Nenhum feriado carregado.
                            @endforelse
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    @else
        <section class="card" style="margin-top: 6mm;">
            <strong>Versao compacta para impressao</strong>
            <p class="muted" style="margin: 4px 0 0;">
                A versao PDF prioriza estabilidade no Chrome e reduz o volume de layout, preservando a leitura dos cartorios reais e do confronto operacional.
            </p>
        </section>
    @endunless

    @if (! empty($warnings))
        <section style="margin-top: 6mm;">
            <table style="font-size: 8.4pt;">
                <thead>
                    <tr>
                        <th>Avisos operacionais</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($warnings as $warning)
                        <tr>
                            <td>{{ $warning }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</x-report.default>
