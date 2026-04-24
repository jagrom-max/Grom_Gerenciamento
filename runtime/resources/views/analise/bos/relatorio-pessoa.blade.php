@php
    $papel     = $papel ?? '';
    $periodoLabel = 'Pesquisa nominal' . ($papel === 'vitima' ? ' — somente vítima' : ($papel === 'autor' ? ' — somente autor' : ''));
@endphp

<x-report.default
    :title="'Relatório de Ocorrências por Pessoa'"
    :period="$periodoLabel"
    :generatedAt="$generatedAt"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="$brasaoSrc"
    :logo-src="$logoSrc"
    :watermark-src="$watermarkSrc"
>
    {{-- ── Toolbar ──────────────────────────────────────────────────────── --}}
    <x-slot:toolbar>
        <a href="{{ route('analise.bos.search', array_filter(['q' => $q, 'papel' => $papel])) }}">← Voltar à pesquisa</a>
        <button onclick="window.print()">Imprimir / Salvar PDF</button>
        <span style="font-size:.85em; color:var(--ink-soft);">{{ e($q) }} &nbsp;·&nbsp; {{ $totalBos }} BO(s)</span>
    </x-slot:toolbar>

    {{-- ── Cards de resumo ─────────────────────────────────────────────── --}}
    <x-slot:summary>
        <article class="card">
            <small>BOs encontrados</small>
            <strong>{{ $totalBos }}</strong>
            <span>Ocorrências distintas com "{{ e($q) }}".</span>
        </article>
        <article class="card">
            <small>Com MPU</small>
            <strong>{{ $totalComMpu }}</strong>
            <span>Possuem número de MPU registrado.</span>
        </article>
        <article class="card">
            <small>Com IP instaurado</small>
            <strong>{{ $totalComIp }}</strong>
            <span>Inquérito policial instaurado.</span>
        </article>
        <article class="card">
            <small>Flagrante</small>
            <strong>{{ $totalFlagrante }}</strong>
            <span>Situação de flagrante delito.</span>
        </article>
    </x-slot:summary>

    {{-- ── Aviso legado ─────────────────────────────────────────────────── --}}
    @if ($legadoWarning)
        <p style="background:#fff8e1; border:1px solid #f5c842; border-radius:4px; padding:4px 8px; font-size:8.5pt; margin-bottom:6mm;">
            ⚠ Banco legado indisponível — resultado abrange apenas registros importados pelo sistema web.
        </p>
    @endif

    {{-- ── Cabeçalho de identificação do alvo ──────────────────────────── --}}
    @if ($hasSearch)
        <div style="background:#eef2ff; border:1px solid #c7d2fe; border-radius:4px; padding:3mm 5mm; margin-bottom:5mm; page-break-inside:avoid;">
            <div style="display:flex; align-items:baseline; gap:8px; flex-wrap:wrap;">
                <span style="font-size:9pt; font-weight:600; color:#555; text-transform:uppercase; letter-spacing:.04em;">Alvo da Pesquisa</span>
                <strong style="font-size:12pt; color:#1e1e2e;">{{ e(mb_strtoupper($q)) }}</strong>
                @if ($papel === 'vitima')
                    <span style="background:#dbeafe; color:#1e40af; border-radius:3px; padding:1px 7px; font-size:8pt; font-weight:700;">Somente Vítima</span>
                @elseif ($papel === 'autor')
                    <span style="background:#fee2e2; color:#991b1b; border-radius:3px; padding:1px 7px; font-size:8pt; font-weight:700;">Somente Autor</span>
                @endif
            </div>
            <div style="margin-top:2mm; font-size:8pt; color:#444; display:flex; gap:16px; flex-wrap:wrap;">
                <span><strong>{{ $totalBos }}</strong> ocorrência{{ $totalBos !== 1 ? 's' : '' }}</span>
                @if ($totalFlagrante > 0)
                    <span style="color:#991b1b;"><strong>{{ $totalFlagrante }}</strong> flagrante{{ $totalFlagrante !== 1 ? 's' : '' }}</span>
                @endif
                <span><strong>{{ $totalComMpu }}</strong> com MPU</span>
                <span><strong>{{ $totalComIp }}</strong> com IP instaurado</span>
            </div>
        </div>
    @endif

    {{-- ── Tabela de resultados ─────────────────────────────────────────── --}}
    @if (empty($results))
        <p style="color:var(--ink-soft); margin-top:4mm;">Nenhum resultado encontrado para "{{ e($q) }}".</p>
    @else
        <table style="table-layout:fixed; font-size:7.8pt;">
            <colgroup>
                <col style="width:3%">
                <col style="width:9%">
                <col style="width:6%">
                <col style="width:6%">
                <col style="width:14%">
                <col style="width:14%">
                <col style="width:3%">
                <col style="width:3%">
                <col style="width:7%">
                <col style="width:9%">
                <col style="width:9%">
                <col style="width:8%">
                <col style="width:9%">
            </colgroup>
            <thead>
                <tr>
                    <th style="text-align:center">#</th>
                    <th>Nº RDO</th>
                    <th>Data</th>
                    <th>Lavrado</th>
                    <th>Nome(s) encontrado(s)</th>
                    <th>Naturezas</th>
                    <th style="text-align:center">Fl.</th>
                    <th style="text-align:center">AI</th>
                    <th>Área / Cart. (BO)</th>
                    <th>MPU</th>
                    <th>CNJ MPU</th>
                    <th>Nº IP / Cart. (IP)</th>
                    <th>CNJ IP</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($results as $i => $row)
                    <tr style="{{ !empty($row['flagrante']) ? 'background:#fff0f0;' : '' }}">
                        <td style="text-align:center; color:var(--ink-soft);">{{ $i + 1 }}</td>
                        <td><strong>{{ $row['spj_fmt'] ?? $row['spj'] ?? '' }}</strong></td>
                        <td>{{ $row['data_ocorrencia'] ?? '' }}</td>
                        <td style="font-size:7pt; text-align:center;">
                            @php $lav = strtoupper(trim($row['lavrado'] ?? '')); @endphp
                            @if (str_contains($lav, 'DDM'))
                                <span style="background:#dbeafe; color:#1e40af; border-radius:3px; padding:0 4px; font-size:6.5pt; font-weight:700;">DDM</span>
                            @elseif (!empty($row['lavrado']))
                                {{ $row['lavrado'] }}
                            @else
                                <span style="color:var(--ink-soft);">—</span>
                            @endif
                        </td>
                        <td>
                            @foreach (explode("\n", $row['pessoas'] ?? '') as $pessoaLinha)
                                @if (trim($pessoaLinha) !== '')
                                    @if (str_contains($pessoaLinha, '[Vítima]'))
                                        @php $nomeVit = mb_strtoupper(str_replace(' [Vítima]', '', $pessoaLinha)); @endphp
                                        <span style="display:block; line-height:1.7;">
                                            <strong>{{ $nomeVit }}</strong>
                                            <span style="display:inline-block; background:#dbeafe; color:#1e40af; border-radius:3px; padding:0 4px; font-size:6.5pt; font-weight:700;">Vítima</span>
                                        </span>
                                    @elseif (str_contains($pessoaLinha, '[Autor]'))
                                        @php $nomeAut = mb_strtoupper(str_replace(' [Autor]', '', $pessoaLinha)); @endphp
                                        <span style="display:block; line-height:1.7;">
                                            <strong>{{ $nomeAut }}</strong>
                                            <span style="display:inline-block; background:#fee2e2; color:#991b1b; border-radius:3px; padding:0 4px; font-size:6.5pt; font-weight:700;">Autor</span>
                                        </span>
                                    @else
                                        <span style="display:block; line-height:1.7;"><strong>{{ mb_strtoupper($pessoaLinha) }}</strong></span>
                                    @endif
                                @endif
                            @endforeach
                        </td>
                        <td style="font-size:7.5pt; color:var(--ink-soft);">
                            {{ $row['naturezas'] ?? '' ?: '—' }}
                        </td>
                        <td style="text-align:center;">
                            @if (!empty($row['flagrante']))
                                <span style="background:#fee2e2; color:#991b1b; border-radius:3px; padding:0 4px; font-size:7pt; font-weight:700;">Sim</span>
                            @else
                                <span style="color:var(--ink-soft);">Não</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            @if (!empty($row['ato_infracional']))
                                <span style="background:#fef3c7; color:#92400e; border-radius:3px; padding:0 4px; font-size:7pt; font-weight:700;">AI</span>
                            @else
                                <span style="color:var(--ink-soft);">—</span>
                            @endif
                        </td>
                        <td style="font-size:7.5pt;">
                            {{ $row['area_fato'] ?? '' ?: '—' }}
                            @if (!empty($row['cartorio_designado']))
                                <br><span style="color:var(--ink-soft);">{{ $row['cartorio_designado'] }}</span>
                            @endif
                        </td>
                        <td style="font-size:7.5pt; word-break:break-all;">{{ $row['mpu_numero'] ?? '' ?: '—' }}</td>
                        <td style="font-size:7.5pt; word-break:break-all;">{{ $row['cnj_mpu'] ?? '' ?: '—' }}</td>
                        <td style="font-size:7.5pt;">
                            @if (!empty($row['num_ip']))
                                {{ $row['num_ip'] }}
                                @if (!empty($row['cartorio_ip']))
                                    <br><span style="color:var(--ink-soft);">{{ $row['cartorio_ip'] }}</span>
                                @endif
                            @else
                                <span style="color:var(--ink-soft);">—</span>
                            @endif
                        </td>
                        <td style="font-size:7.5pt; word-break:break-all;">{{ $row['cnj_ip'] ?? '' ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p style="margin-top:4mm; font-size:7pt; color:var(--ink-soft);">
            <strong>Fl.</strong> = Flagrante (Sim/Não) &nbsp;|&nbsp;
            <strong>AI</strong> = Ato Infracional (adolescente como autor) &nbsp;|&nbsp;
            <strong>Lavrado</strong> = DDM (Deleg. Def. Mulher) ou Outras Unidades &nbsp;|&nbsp;
            <strong>Cart. (BO)</strong> = cartório designado na fase de boletim &nbsp;|&nbsp;
            <strong>CNJ IP</strong> = nº CNJ do inquérito policial
        </p>
    @endif

</x-report.default>
