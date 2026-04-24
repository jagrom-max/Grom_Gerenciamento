<x-report.default
    :title="'IPs por Cartório Destinatário'"
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
            <strong>{{ $totalBos }}</strong>
            <span>Ocorrências na base consolidada.</span>
        </article>
        <article class="card">
            <small>IPs Instaurados</small>
            <strong>{{ $totalIps }}</strong>
            <span>{{ $totalBos > 0 ? number_format(($totalIps / $totalBos) * 100, 1, ',', '.') : '0,0' }}% dos BOs.</span>
        </article>
        <article class="card">
            <small>Cartórios distintos</small>
            <strong>{{ $porCartorio->count() }}</strong>
            <span>Destinatários de IP.</span>
        </article>
        <article class="card">
            <small>Maior volume</small>
            <strong style="font-size:9pt;">{{ $porCartorio->first()->cartorio ?? '—' }}</strong>
            <span>{{ $porCartorio->first()->total_ips ?? 0 }} IPs recebidos.</span>
        </article>
    </x-slot:summary>

    <table style="table-layout:fixed; font-size:8pt;">
        <colgroup>
            <col style="width:6%">
            <col style="width:28%">
            <col style="width:12%">
            <col style="width:12%">
            <col style="width:12%">
            <col style="width:12%">
            <col style="width:18%">
        </colgroup>
        <thead>
            <tr>
                <th style="text-align:center;">#</th>
                <th>Cartório</th>
                <th style="text-align:center;">Total BOs</th>
                <th style="text-align:center;">IPs</th>
                <th style="text-align:center;">MPUs</th>
                <th style="text-align:center;">Flag.</th>
                <th style="text-align:center;">Taxa IP</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($porCartorio as $i => $row)
                <tr>
                    <td style="text-align:center; color:var(--ink-soft);">{{ $i + 1 }}</td>
                    <td style="font-weight:700;">{{ $row->cartorio }}</td>
                    <td style="text-align:center;">{{ $row->total_bos }}</td>
                    <td style="text-align:center; font-weight:700; color:#1d4ed8;">{{ $row->total_ips }}</td>
                    <td style="text-align:center;">{{ $row->com_mpu }}</td>
                    <td style="text-align:center; color:#991b1b;">{{ $row->flagrantes }}</td>
                    <td style="text-align:center;">
                        {{ $row->total_bos > 0 ? number_format(($row->total_ips / $row->total_bos) * 100, 1, ',', '.') : '0,0' }}%
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight:700; border-top:2px solid var(--ink-line);">
                <td colspan="2" style="text-align:right;">TOTAL</td>
                <td style="text-align:center;">{{ $totalBos }}</td>
                <td style="text-align:center;">{{ $totalIps }}</td>
                <td style="text-align:center;">{{ $porCartorio->sum('com_mpu') }}</td>
                <td style="text-align:center;">{{ $porCartorio->sum('flagrantes') }}</td>
                <td style="text-align:center;">
                    {{ $totalBos > 0 ? number_format(($totalIps / $totalBos) * 100, 1, ',', '.') : '0,0' }}%
                </td>
            </tr>
        </tfoot>
    </table>

</x-report.default>
