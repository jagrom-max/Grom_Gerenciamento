@php
    use Carbon\Carbon;

    $brasaoSrc = \App\Support\ReportAsset::dataUri('assets/brasao.png');
    $logoSrc = \App\Support\ReportAsset::dataUri('assets/logo_grom.png');
    $watermarkSrc = \App\Support\ReportAsset::dataUri('assets/marca_dagua.png');
@endphp

<x-report.default
    title="Relatorio de Boletins de Ocorrencia"
    :period="$periodoLabel"
    :generatedAt="now()"
    origin="Produtividade / Boletins"
    footer-note="Cartorio Central - Gerenciamento"
    :brasao-src="$brasaoSrc"
    :logo-src="$logoSrc"
    :watermark-src="$watermarkSrc"
>
    <x-slot:toolbar>
        <button onclick="window.print()">Imprimir / Salvar PDF</button>
        <a href="{{ route('produtividade.boletins.index') }}">← Voltar</a>
        <a href="{{ route('produtividade.boletins.export', array_filter(['cartorio_id' => $cartorio?->id, 'year' => $year, 'month' => $month, 'is_flagrante' => $filters['is_flagrante'] ?? null, 'has_mpu' => $filters['has_mpu'] ?? null, 'without_ip' => $filters['without_ip'] ?? null, 'lavrado_unidade' => $filters['lavrado_unidade'] ?? null], fn ($value) => $value !== null && $value !== '')) }}">Exportar CSV</a>
        <span style="color:var(--ink-soft); font-size:.85em;">{{ $periodoLabel }} · Timbrado Consolidado Oficial</span>
    </x-slot:toolbar>

    <x-slot:summary>
        <article class="card">
            <small>Total BOs</small>
            <strong>{{ $totalGeral }}</strong>
            <span>boletins consolidados.</span>
        </article>
        <article class="card">
            <small>Flagrantes</small>
            <strong>{{ $totalFlagrantes }}</strong>
            <span>do total no periodo.</span>
        </article>
        <article class="card">
            <small>Nao-flagrantes</small>
            <strong>{{ $totalGeral - $totalFlagrantes }}</strong>
            <span>ocorrencias sem flagrante.</span>
        </article>
        <article class="card">
            <small>Flagrantes DDM</small>
            <strong>{{ $totalDdm }}</strong>
            <span>Outras {{ $totalFlagrantes - $totalDdm }}.</span>
        </article>
        <article class="card">
            <small>Com MPU</small>
            <strong>{{ $totalComMpu }}</strong>
            <span>boletins com MPU registrada.</span>
        </article>
        <article class="card">
            <small>Sem IP</small>
            <strong>{{ $totalSemIp }}</strong>
            <span>boletins sem numero de IP.</span>
        </article>
        <article class="card">
            <small>MPU sem IP</small>
            <strong>{{ $totalMpuSemIp }}</strong>
            <span>com MPU registrada sem num. de IP.</span>
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
        .boletim-table {
            table-layout: fixed;
            font-size: 8pt;
            margin-bottom: 4mm;
        }
        .boletim-table th {
            font-size: 7.6pt;
            text-transform: none;
            letter-spacing: 0;
        }
        .td-center { text-align: center; }
        .td-right  { text-align: right; }
        .td-total  { font-weight: bold; background: var(--soft) !important; }
    </style>

    <section>
        <div class="report-note">
            Relatorio consolidado de boletins de ocorrencia por cartorio e periodo.
            @if ($cartorio)
                Cartorio filtrado: <strong>{{ str_pad((string) $cartorio->number, 3, '0', STR_PAD_LEFT) }} — {{ $cartorio->name }}</strong>.
            @else
                Considerando todos os cartorios visiveis ao usuario.
            @endif
        </div>

        @if ($porCartorio->isNotEmpty())
            <table class="boletim-table" style="margin-bottom:5mm;">
                <thead>
                    <tr>
                        <th style="width:34%">Cartorio</th>
                        <th style="width:11%">Total BO</th>
                        <th style="width:11%">Flagrantes</th>
                        <th style="width:11%">Nao-Flagr.</th>
                        <th style="width:11%">Com MPU</th>
                        <th style="width:11%">Sem IP</th>
                        <th style="width:11%">MPU sem IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($porCartorio as $grupo)
                        @php
                            $cart = $grupo['cartorio'];
                            $label = $cart ? str_pad((string) $cart->number, 3, '0', STR_PAD_LEFT) . ' — ' . $cart->name : 'Sem cartorio';
                            $comMpu = $grupo['boletins']->filter(fn ($boletim) => filled($boletim->mpu_numero))->count();
                            $semIp = $grupo['boletins']->filter(fn ($boletim) => blank($boletim->num_ip))->count();
                        @endphp
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="td-center">{{ $grupo['total'] }}</td>
                            <td class="td-center">{{ $grupo['flagrantes'] }}</td>
                            <td class="td-center">{{ $grupo['nao_flagrantes'] }}</td>
                            <td class="td-center">{{ $comMpu }}</td>
                            <td class="td-center">{{ $semIp }}</td>
                            <td class="td-center">{{ $grupo['mpu_sem_ip'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="section-title">
            Pendencias criticas (MPU sem IP)
            <span>— Itens com solicitacao de MPU sem instauracao de IP no recorte selecionado.</span>
        </div>

        <table class="boletim-table" style="margin-bottom:5mm;">
            <thead>
                <tr>
                    <th style="width:8%">Mes</th>
                    <th style="width:10%">Data do Fato</th>
                    <th style="width:20%">Cartorio</th>
                    <th style="width:14%">SPJ</th>
                    <th style="width:14%">MPU</th>
                    <th style="width:8%">Decisao</th>
                    <th style="width:8%">Desp. fund.</th>
                    <th style="width:8%">Encaminhado?</th>
                    <th style="width:10%">Nº IP</th>
                    <th style="width:10%">Atualizado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pendenciasCriticas as $item)
                    <tr>
                        <td class="td-center">{{ Carbon::create($item->reference_year, $item->reference_month, 1)->translatedFormat('M/y') }}</td>
                        <td class="td-center">{{ $item->data_fato?->format('d/m/Y') ?: '—' }}</td>
                        <td>
                            @if ($item->cartorio)
                                {{ str_pad((string) $item->cartorio->number, 3, '0', STR_PAD_LEFT) }} — {{ $item->cartorio->name }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $item->spj ?: '—' }}</td>
                        <td>{{ $item->mpu_numero ?: '—' }}</td>
                        <td class="td-center">{{ $item->mpu_decisao ?: '—' }}</td>
                        <td class="td-center">{{ $item->despacho_fundamentado ? 'Sim' : 'Nao' }}</td>
                        <td class="td-center">{{ $item->encaminhado_outra_unidade ? 'Sim' : 'Nao' }}</td>
                        <td>{{ $item->num_ip ?: '—' }}</td>
                        <td class="td-center">{{ $item->updated_at?->format('d/m/Y') ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="td-center">Nenhuma pendencia critica no periodo selecionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @forelse ($porCartorio as $grupo)
            @php
                $cart = $grupo['cartorio'];
                $label = $cart ? str_pad((string) $cart->number, 3, '0', STR_PAD_LEFT) . ' — ' . $cart->name : 'Sem cartorio';
            @endphp

            <div class="section-title">
                {{ $label }}
                <span>— Total: {{ $grupo['total'] }} · Flagrantes: {{ $grupo['flagrantes'] }} · Nao-flagrantes: {{ $grupo['nao_flagrantes'] }} · MPU sem IP: {{ $grupo['mpu_sem_ip'] }}</span>
            </div>

            <table class="boletim-table">
                <thead>
                    <tr>
                        <th style="width:8%">Mes</th>
                        <th style="width:10%">Data do Fato</th>
                        <th style="width:14%">SPJ</th>
                        <th style="width:10%">Tipo</th>
                        <th style="width:10%">Lavrado</th>
                        <th style="width:11%">MPU</th>
                        <th style="width:7%">Decisao</th>
                        <th style="width:7%">Desp. fund.</th>
                        <th style="width:7%">Encaminhado?</th>
                        <th style="width:10%">Nº IP</th>
                        <th style="width:8%">Nº CNJ</th>
                        <th style="width:8%">MPU sem IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grupo['boletins'] as $b)
                        <tr>
                            <td class="td-center">{{ Carbon::create($b->reference_year, $b->reference_month, 1)->translatedFormat('M/y') }}</td>
                            <td class="td-center">{{ $b->data_fato?->format('d/m/Y') ?: '—' }}</td>
                            <td>{{ $b->spj ?: '—' }}</td>
                            <td class="td-center">{{ $b->is_flagrante ? 'Flagrante' : 'Nao-flagrante' }}</td>
                            <td class="td-center">{{ $b->lavrado_unidade?->value ?: '—' }}</td>
                            <td>{{ $b->mpu_numero ?: '—' }}</td>
                            <td class="td-center">{{ $b->mpu_decisao ?: '—' }}</td>
                            <td class="td-center">{{ $b->despacho_fundamentado ? 'Sim' : 'Nao' }}</td>
                            <td class="td-center">{{ $b->encaminhado_outra_unidade ? 'Sim' : 'Nao' }}</td>
                            <td>{{ $b->num_ip ?: '—' }}</td>
                            <td>{{ $b->num_cnj ?: '—' }}</td>
                            <td class="td-center">{{ filled($b->mpu_numero) && blank($b->num_ip) ? 'Sim' : 'Nao' }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="11" class="td-total td-right">Total cartorio — {{ $label }}:</td>
                        <td class="td-total td-center">{{ $grupo['total'] }}</td>
                    </tr>
                </tbody>
            </table>
        @empty
            <p style="font-style:italic; color:var(--ink-soft); margin-top: 4mm;">Nenhum boletim registrado no periodo selecionado.</p>
        @endforelse
    </section>
</x-report.default>
