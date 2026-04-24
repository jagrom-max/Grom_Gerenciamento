<x-report.default
    :title="'IPs Totais — Lista Completa'"
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
            <small>IPs Instaurados</small>
            <strong>{{ $totalIps }}</strong>
            <span>Total de Inquéritos Policiais.</span>
        </article>
        <article class="card">
            <small>Com CNJ</small>
            <strong>{{ $totalComCnj }}</strong>
            <span>{{ $totalIps > 0 ? number_format(($totalComCnj / $totalIps) * 100, 1, ',', '.') : '0,0' }}% dos IPs.</span>
        </article>
        <article class="card">
            <small>Com MPU</small>
            <strong>{{ $totalComMpu }}</strong>
            <span>{{ $totalIps > 0 ? number_format(($totalComMpu / $totalIps) * 100, 1, ',', '.') : '0,0' }}% dos IPs.</span>
        </article>
        <article class="card">
            <small>Oriundos de Flagrante</small>
            <strong>{{ $totalFlag }}</strong>
            <span>{{ $totalIps > 0 ? number_format(($totalFlag / $totalIps) * 100, 1, ',', '.') : '0,0' }}% dos IPs.</span>
        </article>
    </x-slot:summary>

    {{-- Distribuição por natureza --}}
    @if (!empty($porNatureza))
        <h3 style="font-size:9pt; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); margin:4mm 0 2mm;">
            Distribuição por natureza principal
        </h3>
        <table style="table-layout:fixed; font-size:8pt; margin-bottom:5mm;">
            <colgroup>
                <col style="width:5%"><col style="width:40%"><col style="width:12%"><col style="width:43%">
            </colgroup>
            <thead>
                <tr><th style="text-align:center;">#</th><th>Natureza</th><th style="text-align:center;">IPs</th><th>Proporção</th></tr>
            </thead>
            <tbody>
                @php $maxNat = max(1, ...array_values($porNatureza)); @endphp
                @foreach ($porNatureza as $nat => $qtd)
                    <tr>
                        <td style="text-align:center; color:var(--ink-soft);">{{ $loop->iteration }}</td>
                        <td>{{ $nat }}</td>
                        <td style="text-align:center; font-weight:700;">{{ $qtd }}</td>
                        <td>
                            <div style="background:#f3f4f6; border-radius:3px; height:7px; overflow:hidden;">
                                <div style="width:{{ ($qtd / $maxNat) * 100 }}%; height:100%; background:#1d4ed8; border-radius:3px;"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Lista completa --}}
    <h3 style="font-size:9pt; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); margin:5mm 0 2mm;">
        Lista completa de IPs ({{ $totalIps }})
    </h3>
    <table style="table-layout:fixed; font-size:7.5pt;">
        <colgroup>
            <col style="width:4%">
            <col style="width:12%">
            <col style="width:8%">
            <col style="width:6%">
            <col style="width:16%">
            <col style="width:10%">
            <col style="width:12%">
            <col style="width:12%">
            <col style="width:11%">
            <col style="width:9%">
        </colgroup>
        <thead>
            <tr>
                <th style="text-align:center;">#</th>
                <th>Nº RDO</th>
                <th>Data</th>
                <th style="text-align:center;">Flag.</th>
                <th>Natureza</th>
                <th>Área</th>
                <th>Nº IP</th>
                <th>Cartório (IP)</th>
                <th>CNJ-IP</th>
                <th>MPU</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ips as $i => $row)
                <tr>
                    <td style="text-align:center; color:var(--ink-soft);">{{ $i + 1 }}</td>
                    <td style="font-family:monospace; font-size:7pt;">{{ $row->spj_fmt }}</td>
                    <td style="font-size:7pt;">
                        @php
                            try {
                                echo \Carbon\Carbon::parse($row->data_ocorrencia)->format('d/m/Y');
                            } catch (\Exception $e) {
                                echo $row->data_ocorrencia ?? '—';
                            }
                        @endphp
                    </td>
                    <td style="text-align:center; color:{{ $row->flagrante ? '#991b1b' : '#9ca3af' }};">
                        {{ $row->flagrante ? 'Sim' : 'Não' }}
                    </td>
                    <td style="font-weight:{{ $row->flagrante ? '700' : '400' }};">{{ $row->natureza_principal ?? '—' }}</td>
                    <td>{{ $row->area_fato ?? '—' }}</td>
                    <td style="font-family:monospace; font-size:7pt;">{{ $row->num_ip ?? '—' }}</td>
                    <td>{{ $row->cartorio_ip ?? '—' }}</td>
                    <td style="font-family:monospace; font-size:7pt;">{{ $row->cnj_ip ?? '—' }}</td>
                    <td style="font-family:monospace; font-size:7pt;">{{ $row->mpu_numero ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</x-report.default>
