<x-report.default
    :title="'Relatório de Flagrantes'"
    period="Base consolidada de BOs"
    :generatedAt="$generatedAt"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="$brasaoSrc"
    :logo-src="$logoSrc"
    :watermark-src="$watermarkSrc"
>
    <x-slot:toolbar>
        <a href="{{ route('analise.relatorios.index') }}">← Voltar</a>
        <button onclick="window.print()">Imprimir / Salvar PDF</button>
        <a href="{{ route('analise.relatorios.pdf', $tipo) }}" style="text-decoration:none;">⬇ Baixar PDF</a>
        <span style="font-size:.85em; color:var(--ink-soft);">{{ $geradoEm }}</span>
    </x-slot:toolbar>

    <x-slot:summary>
        <article class="card">
            <small>Total de BOs</small>
            <strong>{{ $kpi->total_bos }}</strong>
            <span>Ocorrências na base consolidada.</span>
        </article>
        <article class="card">
            <small>Flagrantes</small>
            <strong>{{ $kpi->total_flagrantes }}</strong>
            <span>{{ $kpi->total_bos > 0 ? number_format(($kpi->total_flagrantes / $kpi->total_bos) * 100, 1, ',', '.') : '0,0' }}% do total de BOs.</span>
        </article>
        <article class="card">
            <small>Atos Infracionais</small>
            <strong>{{ $kpi->atos_infracionais }}</strong>
            <span>Menor Infrator como autor.</span>
        </article>
    </x-slot:summary>

    {{-- Evolução mensal --}}
    @if (!empty($evolucao))
        <h3 style="font-size:8.5pt; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); margin:2mm 0 1mm;">
            Evolução mensal
        </h3>
        <table style="table-layout:fixed; font-size:8pt;">
            <colgroup>
                <col style="width:16%">
                <col style="width:16%">
                <col style="width:16%">
                <col style="width:52%">
            </colgroup>
            <thead>
                <tr>
                    <th>Período</th>
                    <th style="text-align:center;">Total BOs</th>
                    <th style="text-align:center;">Flagrantes</th>
                    <th style="text-align:center;">% Flagrante</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($evolucao as $row)
                    <tr>
                        <td>{{ $row->periodo }}</td>
                        <td style="text-align:center;">{{ $row->total }}</td>
                        <td style="text-align:center; font-weight:700; color:#991b1b;">{{ $row->flagrantes }}</td>
                        <td style="text-align:center;">
                            @php $pct = $row->total > 0 ? round(($row->flagrantes / $row->total) * 100, 1) : 0; @endphp
                            {{ number_format($pct, 1, ',', '.') }}%
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Por área --}}
    @if (!empty($porArea))
        <h3 style="font-size:8.5pt; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); margin:3mm 0 1mm;">
            Flagrantes por área do fato
        </h3>
        <table style="table-layout:fixed; font-size:8pt;">
            <colgroup>
                <col style="width:40%"><col style="width:16%"><col style="width:16%"><col style="width:28%">
            </colgroup>
            <thead>
                <tr>
                    <th>Área</th>
                    <th style="text-align:center;">Total</th>
                    <th style="text-align:center;">AI</th>
                    <th style="text-align:center;">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($porArea as $row)
                    <tr>
                        <td>{{ $row->area }}</td>
                        <td style="text-align:center; font-weight:700;">{{ $row->total }}</td>
                        <td style="text-align:center;">{{ $row->atos_infracionais }}</td>
                        <td style="text-align:center;">{{ $kpi->total_flagrantes > 0 ? number_format(($row->total / $kpi->total_flagrantes) * 100, 1, ',', '.') : '0,0' }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Por lavrado --}}
    @if (!empty($porLavrado))
        <h3 style="font-size:8.5pt; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); margin:3mm 0 1mm;">
            Flagrantes por unidade de lavratura
        </h3>
        <table style="table-layout:fixed; font-size:8pt;">
            <colgroup><col style="width:55%"><col style="width:20%"><col style="width:25%"></colgroup>
            <thead>
                <tr><th>Unidade</th><th style="text-align:center;">Flagrantes</th><th style="text-align:center;">%</th></tr>
            </thead>
            <tbody>
                @foreach ($porLavrado as $row)
                    <tr>
                        <td>{{ $row->lavrado }}</td>
                        <td style="text-align:center; font-weight:700;">{{ $row->total }}</td>
                        <td style="text-align:center;">{{ $kpi->total_flagrantes > 0 ? number_format(($row->total / $kpi->total_flagrantes) * 100, 1, ',', '.') : '0,0' }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Naturezas --}}
    @if (!empty($naturezas))
        <h3 style="font-size:8.5pt; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); margin:3mm 0 1mm;">
            Naturezas dos flagrantes
        </h3>
        <table style="table-layout:fixed; font-size:8pt;">
            <colgroup><col style="width:10%"><col style="width:55%"><col style="width:15%"><col style="width:20%"></colgroup>
            <thead>
                <tr>
                    <th style="text-align:center;">#</th>
                    <th>Natureza</th>
                    <th style="text-align:center;">Total</th>
                    <th style="text-align:center;">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($naturezas as $i => $row)
                    <tr>
                        <td style="text-align:center; color:var(--ink-soft);">{{ $i + 1 }}</td>
                        <td>{{ $row['natureza'] }}</td>
                        <td style="text-align:center; font-weight:700;">{{ $row['total'] }}</td>
                        <td style="text-align:center;">{{ $kpi->total_flagrantes > 0 ? number_format(($row['total'] / $kpi->total_flagrantes) * 100, 1, ',', '.') : '0,0' }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</x-report.default>
