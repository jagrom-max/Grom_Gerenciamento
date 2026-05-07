@php
    $brasaoSrc = \App\Support\ReportAsset::dataUri('assets/brasao.png');
    $logoSrc = \App\Support\ReportAsset::dataUri('assets/logo_grom.png');
    $watermarkSrc = \App\Support\ReportAsset::dataUri('assets/marca_dagua.png');
@endphp

<x-report.default
    title="Confronto de Afastamentos — {{ mb_strtoupper($meses[$mes]) }} / {{ $ano }}"
    :period="$meses[$mes] . ' de ' . $ano"
    :generatedAt="now()"
    origin="RH / Recursos Humanos"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="$brasaoSrc"
    :logo-src="$logoSrc"
    :watermark-src="$watermarkSrc"
>
    <x-slot:toolbar>
        <button onclick="window.print()">Imprimir / Salvar PDF</button>
        <a href="{{ route('rh.index') }}">← Voltar</a>
        <span style="color:var(--ink-soft); font-size:.85em;">Confronto de {{ $meses[$mes] }} / {{ $ano }}</span>
    </x-slot:toolbar>

    <x-slot:summary>
        <article class="card">
            <small>Afastamentos no período</small>
            <strong>{{ $afastamentos->count() }}</strong>
            <span>registros consolidados no mês.</span>
        </article>
        <article class="card">
            <small>Funcionários afastados</small>
            <strong>{{ $afastamentos->pluck('funcionario_id')->unique()->count() }}</strong>
            <span>pessoas distintas no recorte.</span>
        </article>
        <article class="card">
            <small>Dias no mês</small>
            <strong>{{ $diasDoMes }}</strong>
            <span>janela de análise mensal.</span>
        </article>
        <article class="card">
            <small>Colisões críticas</small>
            <strong>{{ $colisoesCriticas->count() }}</strong>
            <span>dias com sobreposição sensível.</span>
        </article>
    </x-slot:summary>

    <style>
        .rh-table {
            table-layout: fixed;
            font-size: 7.5pt;
            margin-bottom: 8mm;
        }
        .rh-table th {
            font-size: 7pt;
            text-transform: none;
            letter-spacing: 0;
        }
        .rh-table td, .rh-table th {
            padding: 1.5mm 2mm;
        }
        .colisao-alert {
            display: none;
        }
        .cal-section {
            margin-top: 3mm;
        }
        @media screen {
            .rh-table { display: none; }
        }
        @media print {
            .rh-table { display: table; }
            .cal-af { font-size: 6.5pt; line-height: 1.4; padding-top: 1px; color: #333; }
        }
        .cal-title {
            font-size: 9pt;
            font-weight: 700;
            text-align: center;
            margin-bottom: 2mm;
            background: #eef3f8;
            padding: 2.5mm 3mm;
            border-radius: 4px;
            color: #213547;
        }
        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            font-size: 7pt;
        }
        .cal-header {
            text-align: center;
            font-weight: 700;
            padding: 3px;
            background: #e8e8e8;
            border: 0.5px solid #bbb;
        }
        .cal-day {
            border: 0.5px solid #bbb;
            padding: 2px 3px;
            min-height: 55px;
            background: #fff;
        }
        .cal-day.empty { border: none; background: transparent; }
        .cal-day.colisao { background: #fff3cd; }
        .cal-day.tem-afastamento { background: #eef6fb; }
        .cal-day-num { font-weight: 700; font-size: 8pt; margin-bottom: 2px; }
        .cal-af { font-size: 6.5pt; line-height: 1.4; padding-top: 1px; color: #333; }
    </style>

    <section>
        <table class="rh-table">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Cargo</th>
                    <th>Setor</th>
                    <th>Motivo</th>
                    <th style="text-align:center;">Início</th>
                    <th style="text-align:center;">Fim</th>
                    <th style="text-align:center;">Dias no mês</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($funcionariosAtivos as $func)
                    @php
                        $afFunc = $afastamentos->where('funcionario_id', $func->id)->values();
                        $cn = mb_strtolower($func->cargo?->name ?? '');
                        $critico = str_contains($cn, 'delegad') || str_contains($cn, 'escrivao') || str_contains($cn, 'escrivã');
                        $pIni = \Carbon\Carbon::create($ano, $mes, 1);
                        $pFim = $pIni->copy()->endOfMonth();
                    @endphp
                    @if ($afFunc->count() > 0)
                        @foreach ($afFunc as $af)
                            @php
                                $inicio = \Carbon\Carbon::parse($af->start_date);
                                $fim = $af->end_date ? \Carbon\Carbon::parse($af->end_date) : null;
                                $iNoMes = $inicio->lt($pIni) ? $pIni->copy()->startOfDay() : $inicio->startOfDay();
                                $fNoMes = ($fim === null || $fim->gt($pFim)) ? $pFim->copy()->startOfDay() : $fim->startOfDay();
                                $dias = (int) $iNoMes->diffInDays($fNoMes) + 1;
                            @endphp
                            <tr @class(['critico' => $critico])>
                                <td><strong>{{ $func->name }}</strong></td>
                                <td>{{ $func->cargo?->name ?? '—' }}</td>
                                <td>{{ $func->sector ?: '—' }}</td>
                                <td>{{ $af->reason }}</td>
                                <td style="text-align:center; white-space:nowrap;">{{ $inicio->format('d/m/Y') }}</td>
                                <td style="text-align:center; white-space:nowrap;">{{ $fim ? $fim->format('d/m/Y') : 'Em aberto' }}</td>
                                <td style="text-align:center; font-weight:bold;">{{ $dias }}d</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>

        <div class="cal-section">
            <div class="cal-title">Grade Diária</div>
            <div class="cal-grid">
                @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $ds)
                    <div class="cal-header">{{ $ds }}</div>
                @endforeach
                @php $offset = (int) $periodoInicio->dayOfWeek; @endphp
                @for ($o = 0; $o < $offset; $o++)
                    <div class="cal-day empty"></div>
                @endfor
                @foreach ($calendarioDias as $nd => $dd)
                    @php
                        $temCol = $colisoesCriticas->contains($nd);
                        $temAf = $dd['afastamentos']->count() > 0;
                    @endphp
                    <div class="cal-day @if($temCol) colisao @elseif($temAf) tem-afastamento @endif">
                        <div class="cal-day-num">{{ sprintf('%02d', $nd) }}{{ $temCol ? ' ⚠' : '' }}</div>
                        @foreach ($dd['afastamentos'] as $af)
                            <div class="cal-af">
                                {{ \Illuminate\Support\Str::limit($af->funcionario?->short_name ?: $af->funcionario?->name ?? '—', 16) }}
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</x-report.default>
