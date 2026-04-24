<x-report.default
    :title="'Ocorrências por Natureza'"
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
            <small>Naturezas distintas</small>
            <strong>{{ count($naturezas) }}</strong>
            <span>Tipos penais registrados.</span>
        </article>
        <article class="card">
            <small>Total de BOs</small>
            <strong>{{ $totalGeral }}</strong>
            <span>Ocorrências com natureza vinculada.</span>
        </article>
        <article class="card">
            <small>Natureza principal</small>
            <strong style="font-size:9pt;">{{ $naturezas[0]['natureza'] ?? '—' }}</strong>
            <span>{{ $naturezas[0]['total'] ?? 0 }} registros ({{ $totalGeral > 0 && isset($naturezas[0]) ? number_format(($naturezas[0]['total'] / $totalGeral) * 100, 1, ',', '.') : '0,0' }}%).</span>
        </article>
        <article class="card">
            <small>Flagrantes totais</small>
            <strong>{{ array_sum(array_column($naturezas, 'flagrantes')) }}</strong>
            <span>Prisões em flagrante registradas.</span>
        </article>
    </x-slot:summary>

    <table style="table-layout:fixed; font-size:8pt;">
        <colgroup>
            <col style="width:6%">
            <col style="width:50%">
            <col style="width:14%">
            <col style="width:14%">
            <col style="width:16%">
        </colgroup>
        <thead>
            <tr>
                <th style="text-align:center;">#</th>
                <th>Natureza</th>
                <th style="text-align:center;">Total</th>
                <th style="text-align:center;">%</th>
                <th style="text-align:center;">Flag.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($naturezas as $i => $row)
                <tr>
                    <td style="text-align:center; color:var(--ink-soft);">{{ $i + 1 }}</td>
                    <td style="font-weight:700;">{{ $row['natureza'] }}</td>
                    <td style="text-align:center; font-weight:700;">{{ $row['total'] }}</td>
                    <td style="text-align:center;">
                        {{ $totalGeral > 0 ? number_format(($row['total'] / $totalGeral) * 100, 1, ',', '.') : '0,0' }}%
                    </td>
                    <td style="text-align:center; color:#991b1b;">{{ $row['flagrantes'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight:700; border-top:2px solid var(--ink-line);">
                <td colspan="2" style="text-align:right;">TOTAL</td>
                <td style="text-align:center;">{{ $totalGeral }}</td>
                <td style="text-align:center;">100%</td>
                <td style="text-align:center;">{{ array_sum(array_column($naturezas, 'flagrantes')) }}</td>
            </tr>
        </tfoot>
    </table>

</x-report.default>
