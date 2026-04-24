@php
    $brasaoSrc = \App\Support\ReportAsset::dataUri('assets/brasao.png');
    $logoSrc = \App\Support\ReportAsset::dataUri('assets/logo_grom.png');
    $watermarkSrc = \App\Support\ReportAsset::dataUri('assets/marca_dagua.png');
@endphp

<x-report.default
    title="Composição dos Cartórios"
    :period="$hoje->format('d/m/Y')"
    :generatedAt="now()"
    origin="RH / Funcionários"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="$brasaoSrc"
    :logo-src="$logoSrc"
    :watermark-src="$watermarkSrc"
>
    <x-slot:toolbar>
        <a href="{{ route('rh.index') }}">← Voltar ao RH</a>
        <button onclick="window.print()">Imprimir / Salvar PDF</button>
        <span style="font-size:.85em; color:var(--ink-soft);">Composição dos cartórios em {{ $hoje->format('d/m/Y') }}</span>
    </x-slot:toolbar>

    <x-slot:summary>
        <article class="card">
            <small>Efetivo total ativo</small>
            <strong>{{ $estatisticas['total_ativos'] }}</strong>
            <span>servidores habilitados no cadastro.</span>
        </article>
        <article class="card">
            <small>Concorrem à escala</small>
            <strong>{{ $estatisticas['concorrem_escala'] }}</strong>
            <span>pessoas aptas ao rodízio.</span>
        </article>
        <article class="card">
            <small>Em afastamento</small>
            <strong>{{ $estatisticas['em_afastamento'] }}</strong>
            <span>registros ativos na data base.</span>
        </article>
        <article class="card">
            <small>Setores / Cartórios</small>
            <strong>{{ $estatisticas['setores'] }}</strong>
            <span>agrupamentos funcionais em uso.</span>
        </article>
    </x-slot:summary>

    <style>
        .report-intro {
            margin-bottom: 4mm;
            color: var(--ink-soft);
            font-size: 8.8pt;
            line-height: 1.5;
        }
        .setor-title {
            margin: 5mm 0 2mm;
            padding-left: 3mm;
            border-left: 3px solid var(--ink);
            font-weight: 700;
            font-size: 10pt;
        }
        .setor-title span {
            font-weight: 400;
            color: var(--ink-soft);
            font-size: 8pt;
        }
        .rh-table {
            table-layout: fixed;
            font-size: 8pt;
            margin-bottom: 4mm;
        }
        .rh-table th {
            font-size: 7.6pt;
            text-transform: none;
            letter-spacing: 0;
        }
        .rh-table td {
            vertical-align: top;
        }
        .tag-ok {
            display: inline-block;
            background: #d4edda;
            color: #155724;
            padding: 1px 5px;
            border-radius: 999px;
            font-size: 7.2pt;
            font-weight: 700;
        }
        .tag-warn {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 1px 5px;
            border-radius: 999px;
            font-size: 7.2pt;
            font-weight: 700;
        }
        .tag-muted {
            display: inline-block;
            background: #e9ecef;
            color: #555;
            padding: 1px 5px;
            border-radius: 999px;
            font-size: 7.2pt;
            font-weight: 700;
        }
        .leg-badge {
            display: inline-block;
            margin-left: 2mm;
            background: #e8f4f8;
            color: #1a6a9a;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: 700;
        }
    </style>

    <section>
        <div class="report-intro">
            Consolidação do efetivo por setor/cartório, com indicação de escala e afastamentos vigentes na data base.
        </div>

        @forelse ($porSetor as $setor => $funcionarios)
            <div class="setor-title">
                {{ $setor }}
                <span>— {{ $funcionarios->count() }} servidor(es)</span>
            </div>
            <table class="rh-table">
                <thead>
                    <tr>
                        <th style="width: 16%;">Matrícula</th>
                        <th style="width: 30%;">Nome</th>
                        <th style="width: 20%;">Cargo</th>
                        <th style="width: 8%; text-align: center;">Escala</th>
                        <th style="width: 12%; text-align: center;">Status</th>
                        <th style="width: 14%;">Afastamento vigente</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($funcionarios->sortBy('name') as $f)
                        @php
                            $afAtual = $f->afastamentos->first();
                            $emAf = $afAtual !== null;
                        @endphp
                        <tr @class(['afastado' => $emAf])>
                            <td>
                                <strong>{{ $f->matricula }}</strong>
                                @if ($f->legacy_id)
                                    <span class="leg-badge">LEG</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $f->name }}</strong>
                                @if ($f->short_name && $f->short_name !== $f->name)
                                    <br><span style="color: var(--ink-soft); font-size: 7.5pt;">{{ $f->short_name }}</span>
                                @endif
                            </td>
                            <td>{{ $f->cargo?->name ?? '—' }}</td>
                            <td style="text-align: center;">
                                @if ($f->concorre_escala)
                                    <span class="tag-ok">Sim</span>
                                @else
                                    <span class="tag-muted">Não</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @if ($emAf)
                                    <span class="tag-warn">Afastado(a)</span>
                                @else
                                    <span class="tag-ok">Presente</span>
                                @endif
                            </td>
                            <td style="font-size: 7.5pt;">
                                @if ($emAf)
                                    <strong>{{ $afAtual->reason }}</strong><br>
                                    @php
                                        $ini = \Illuminate\Support\Carbon::parse($afAtual->start_date);
                                        $fim = $afAtual->end_date ? \Illuminate\Support\Carbon::parse($afAtual->end_date) : null;
                                    @endphp
                                    {{ $ini->format('d/m/Y') }} → {{ $fim ? $fim->format('d/m/Y') : 'Aberto' }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @empty
            <p style="text-align: center; font-style: italic; padding: 18px 0; color: var(--ink-soft);">
                Nenhum funcionário ativo cadastrado no sistema.
            </p>
        @endforelse
    </section>
</x-report.default>
