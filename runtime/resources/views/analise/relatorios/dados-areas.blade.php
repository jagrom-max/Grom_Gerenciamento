<x-report.default
    :title="'Ocorrências por Área do Fato'"
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
            <small>Total de Ocorrências</small>
            <strong>{{ $totalGeral }}</strong>
            <span>BOs com área do fato registrada.</span>
        </article>
        <article class="card">
            <small>Áreas distintas</small>
            <strong>{{ count($areas) }}</strong>
            <span>Regiões/bairros distintos.</span>
        </article>
        <article class="card">
            <small>Área c/ mais BOs</small>
            <strong style="font-size:9pt;">{{ $areas[0]->area ?? '—' }}</strong>
            <span>{{ $areas[0]->total ?? 0 }} ocorrências registradas.</span>
        </article>
        <article class="card">
            <small>Flagrantes totais</small>
            <strong>{{ array_sum(array_column(array_map(fn($r) => ['f' => $r->flagrantes], $areas), 'f')) }}</strong>
            <span>Prisões em flagrante por área.</span>
        </article>
    </x-slot:summary>

    <table style="table-layout:fixed; font-size:8pt;">
        <colgroup>
            <col style="width:6%">
            <col style="width:28%">
            <col style="width:10%">
            <col style="width:10%">
            <col style="width:10%">
            <col style="width:10%">
            <col style="width:10%">
            <col style="width:16%">
        </colgroup>
        <thead>
            <tr>
                <th style="text-align:center;">#</th>
                <th>Área do Fato</th>
                <th style="text-align:center;">Total</th>
                <th style="text-align:center;">%</th>
                <th style="text-align:center;">Flag.</th>
                <th style="text-align:center;">AI</th>
                <th style="text-align:center;">Com IP</th>
                <th style="text-align:center;">Com MPU</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($areas as $i => $row)
                <tr>
                    <td style="text-align:center; color:var(--ink-soft);">{{ $i + 1 }}</td>
                    <td style="font-weight:700;">{{ $row->area }}</td>
                    <td style="text-align:center; font-weight:700;">{{ $row->total }}</td>
                    <td style="text-align:center;">
                        {{ $totalGeral > 0 ? number_format(($row->total / $totalGeral) * 100, 1, ',', '.') : '0,0' }}%
                    </td>
                    <td style="text-align:center; color:#991b1b;">{{ $row->flagrantes }}</td>
                    <td style="text-align:center;">{{ $row->atos_infracionais }}</td>
                    <td style="text-align:center;">{{ $row->com_ip }}</td>
                    <td style="text-align:center;">{{ $row->com_mpu }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight:700; border-top:2px solid var(--ink-line);">
                <td colspan="2" style="text-align:right;">TOTAL</td>
                <td style="text-align:center;">{{ $totalGeral }}</td>
                <td style="text-align:center;">100%</td>
                <td style="text-align:center;">{{ $areas->sum('flagrantes') }}</td>
                <td style="text-align:center;">{{ $areas->sum('atos_infracionais') }}</td>
                <td style="text-align:center;">{{ $areas->sum('com_ip') }}</td>
                <td style="text-align:center;">{{ $areas->sum('com_mpu') }}</td>
            </tr>
        </tfoot>
    </table>

</x-report.default>
