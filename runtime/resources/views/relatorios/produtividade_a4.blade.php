<x-report.default
    :title="'Produtividade de Cartórios - Fechamento Mensal'"
    :period="$monthLabel"
    :generatedAt="$generatedAt"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="$brasaoSrc"
    :logo-src="$logoSrc"
    :watermark-src="$watermarkSrc"
>
    <x-slot:toolbar>
        <a href="{{ route('relatorios.index') }}">Voltar aos relatorios</a>
        <a href="{{ route('relatorios.produtividade.a4.pdf', ['year' => $year, 'month' => $month]) }}">Baixar PDF</a>
    </x-slot:toolbar>

    <x-slot:summary>
        <article class="card">
            <small>Boletins Lavrados</small>
            <strong>{{ $grandDdm + $grandOutras }}</strong>
            <span>Soma dos SPJ lavrados na DDM e de Outras Unidades no período.</span>
        </article>
        <article class="card">
            <small>IP instaurados</small>
            <strong>{{ $grandIpInstaurados }}</strong>
            <span>Volume consolidado no periodo selecionado.</span>
        </article>
        <article class="card">
            <small>IP relatados</small>
            <strong>{{ $grandIpRelatados }}</strong>
            <span>Resultado da base mensal do cartorio.</span>
        </article>
        <article class="card">
            <small>Cotas</small>
            <strong>{{ $grandCotas }}</strong>
            <span>Campo preexistente do fechamento mensal.</span>
        </article>

    </x-slot:summary>

    <section style="margin-bottom: 6mm;">
        <table style="table-layout: fixed; font-size: 8.2pt; text-transform: none;">
            <thead>
                <tr>
                    <th style="width: 30%; text-align:left;">Cartórios</th>
                    <th style="width: 8%; text-align:center;">IP<br>Inst.</th>
                    <th style="width: 8%; text-align:center;">IP<br>Rel.</th>
                    <th style="width: 6%; text-align:center;">Cotas</th>
                    <th style="width: 9%; text-align:center;">Em<br>Cart.</th>
                    <th style="width: 7%; text-align:center;">DDM</th>
                    <th style="width: 7%; text-align:center;">Outras</th>
                    <th style="width: 7%; text-align:center;">Total</th>
                    <th style="width: 8%; text-align:center;">%</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ str_pad((string) $row['cartorio']->number, 3, '0', STR_PAD_LEFT) }} - {{ $row['cartorio']->name }}</strong><br>
                            <span style="color: #5a6a7a;">{{ $row['cartorio']->code }}</span>
                            @if ($row['cartorio']->designacao)
                                <br><span style="color: #5a6a7a;">{{ $row['cartorio']->designacao }}</span>
                            @endif
                        </td>
                        <td style="text-align:center;">{{ $row['ip_instaurados'] }}</td>
                        <td style="text-align:center;">{{ $row['ip_relatados'] }}</td>
                        <td style="text-align:center;">{{ $row['cotas'] }}</td>
                        <td style="text-align:center;">{{ $row['ips_andamento'] }}</td>
                        <td style="text-align:center;">{{ $row['flagrantes_ddm'] }}</td>
                        <td style="text-align:center;">{{ $row['flagrantes_outras'] }}</td>
                        <td style="text-align:center;">{{ $row['flagrantes_total'] }}</td>
                        <td style="text-align:center;">
                            {{ $grandTotal > 0 ? number_format(($row['flagrantes_total'] / $grandTotal) * 100, 1, ',', '.') : '0,0' }}%
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">Nenhum cartorio cadastrado para o periodo informado.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="font-weight:700; background: var(--soft, #eef3f8);">
                    <td><strong>Total</strong></td>
                    <td style="text-align:center;">{{ $rows->sum('ip_instaurados') }}</td>
                    <td style="text-align:center;">{{ $rows->sum('ip_relatados') }}</td>
                    <td style="text-align:center;">{{ $rows->sum('cotas') }}</td>
                    <td style="text-align:center;">{{ $rows->sum('ips_andamento') }}</td>
                    <td style="text-align:center;">{{ $rows->sum('flagrantes_ddm') }}</td>
                    <td style="text-align:center;">{{ $rows->sum('flagrantes_outras') }}</td>
                    <td style="text-align:center;">{{ $rows->sum('flagrantes_total') }}</td>
                    <td style="text-align:center;">100%</td>
                </tr>
            </tfoot>
        </table>
    </section>
</x-report.default>
