@php
    use Carbon\Carbon;

    $brasaoSrc = \App\Support\ReportAsset::dataUri('assets/brasao.png');
    $logoSrc = \App\Support\ReportAsset::dataUri('assets/logo_grom.png');
    $watermarkSrc = \App\Support\ReportAsset::dataUri('assets/marca_dagua.png');
@endphp

<x-report.default
    title="Relatório de Flagrantes"
    :period="$periodoLabel"
    :generatedAt="now()"
    origin="Produtividade / Flagrantes"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="$brasaoSrc"
    :logo-src="$logoSrc"
    :watermark-src="$watermarkSrc"
>
    <x-slot:toolbar>
        <button onclick="window.print()">Imprimir / Salvar PDF</button>
        <a href="{{ route('produtividade.flagrantes.index') }}">← Voltar</a>
        <span style="color:var(--ink-soft); font-size:.85em;">{{ $periodoLabel }}</span>
    </x-slot:toolbar>

    <x-slot:summary>
        <article class="card">
            <small>Total no período</small>
            <strong>{{ $totalGeral }}</strong>
            <span>flagrantes consolidados.</span>
        </article>
        <article class="card">
            <small>Lavrados DDM</small>
            <strong>{{ $totalDdm }}</strong>
            <span>procedimentos da unidade.</span>
        </article>
        <article class="card">
            <small>Outras unidades</small>
            <strong>{{ $totalGeral - $totalDdm }}</strong>
            <span>registros complementares.</span>
        </article>
        <article class="card">
            <small>Cartórios com registro</small>
            <strong>{{ $porCartorio->count() }}</strong>
            <span>cartórios no recorte.</span>
        </article>
    </x-slot:summary>

    <style>
        .report-note {
            font-size: 8.8pt;
            color: var(--ink-soft);
            margin-bottom: 4mm;
            line-height: 1.5;
        }
        .section-title {
            font-size: 10pt;
            font-weight: 700;
            margin: 5mm 0 2mm;
            border-left: 3px solid var(--ink);
            padding-left: 3mm;
        }
        .section-title span {
            font-weight: 400;
            color: var(--ink-soft);
            font-size: 8pt;
        }
        .flagrante-table {
            table-layout: fixed;
            font-size: 8pt;
            margin-bottom: 4mm;
        }
        .flagrante-table th {
            font-size: 7.6pt;
            text-transform: none;
            letter-spacing: 0;
        }
        .td-center { text-align: center; }
        .td-right  { text-align: right; }
        .td-light  { color: var(--ink-soft); font-style: italic; }
        .td-total  { font-weight: bold; background: var(--soft) !important; }
    </style>

    <section>
        <div class="report-note">
            Relatório consolidado de flagrantes por cartório e período, padronizado no timbrado oficial do sistema.
            @if ($cartorio)
                Cartório filtrado: <strong>{{ str_pad($cartorio->number, 3, '0', STR_PAD_LEFT) }} — {{ $cartorio->name }}</strong>.
            @else
                Considerando todos os cartórios visíveis ao usuário.
            @endif
        </div>

        @forelse ($porCartorio as $grupo)
            @php
                $cart = $grupo['cartorio'];
                $label = $cart ? str_pad($cart->number, 3, '0', STR_PAD_LEFT) . ' — ' . $cart->name : 'Sem cartório';
            @endphp

            <div class="section-title">
                {{ $label }}
                <span>— {{ $grupo['total'] }} flagrante(s) · DDM: {{ $grupo['ddm'] }} · Outras: {{ $grupo['outras'] }}</span>
            </div>

            <table class="flagrante-table">
                <thead>
                    <tr>
                        <th style="width:8%">Mês</th>
                        <th style="width:10%">Data do Fato</th>
                        <th style="width:18%">SPJ</th>
                        <th style="width:12%">Nº IP</th>
                        <th style="width:12%">CNJ</th>
                        <th style="width:28%">Natureza(s)</th>
                        <th style="width:12%">Lavrado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grupo['flagrantes'] as $f)
                        <tr>
                            <td class="td-center">{{ Carbon::create($f->reference_year, $f->reference_month, 1)->translatedFormat('M/y') }}</td>
                            <td class="td-center">{{ $f->data_fato?->format('d/m/Y') ?: '—' }}</td>
                            <td>{{ $f->spj ?: '—' }}</td>
                            <td>{{ $f->num_ip ?: '—' }}</td>
                            <td class="td-light">{{ $f->num_cnj ?: '—' }}</td>
                            <td>{{ $f->naturezas ?: '—' }}</td>
                            <td class="td-center">{{ $f->lavrado_unidade?->value ?: '—' }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="6" class="td-total td-right">Total cartório — {{ $label }}:</td>
                        <td class="td-total td-center">{{ $grupo['total'] }}</td>
                    </tr>
                </tbody>
            </table>
        @empty
            <p style="font-style:italic; color:var(--ink-soft); margin-top: 4mm;">Nenhum flagrante registrado no período selecionado.</p>
        @endforelse
    </section>
</x-report.default>
