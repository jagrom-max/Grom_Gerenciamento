@php
use Carbon\Carbon;

$ano = $filters['ano'];
$mes = $filters['mes'];
$previewMode = request()->boolean('preview');
$mesLabel = Carbon::create()->month($mes)->locale('pt_BR')->isoFormat('MMMM');
$mesLabelUpper = mb_strtoupper($mesLabel, 'UTF-8');
$mesLabelFull = ucfirst($mesLabel) . ' / ' . $ano;
$geradoEm = now();
$versao = $phpDias->isNotEmpty() ? $phpVersao : ($snapshot['version'] ?? '?');
$splitPlantaoTexto = static function (string $texto): array {
    $texto = trim($texto);

    if ($texto === '') {
        return [];
    }

    $partes = preg_split('/\s*(?:\r\n|\r|\n|,\s*)\s*/u', $texto, -1, PREG_SPLIT_NO_EMPTY);

    if ($partes === false) {
        return [$texto];
    }

    return array_values(array_filter(array_map('trim', $partes), static fn (string $item): bool => $item !== ''));
};

$plantoesTextoPorData = [];
foreach ($plantoesMes as $dataKey => $atribs) {
    $linhas = [];
    foreach ($atribs as $atrib) {
        $nome = trim((string) ($atrib->funcionario?->short_name ?: $atrib->funcionario?->name ?: ''));
        $sigla = trim((string) ($atrib->plantaoExterno?->sigla ?: $atrib->plantaoExterno?->nome ?: ''));

        if ($nome === '' && $sigla === '') {
            continue;
        }

        $linhas[] = $sigla === '' ? $nome : ($nome . ' (' . $sigla . ')');
    }

    sort($linhas, SORT_NATURAL | SORT_FLAG_CASE);
    $linhas = array_values(array_unique($linhas));
    $plantoesTextoPorData[$dataKey] = implode(PHP_EOL, $linhas);
}

// ---- Mapa de dias da escala ----
$diasMapa = [];
if ($phpDias->isNotEmpty()) {
    foreach ($phpDias as $dia) {
        $diasMapa[$dia->data->toDateString()] = ['src' => 'php', 'obj' => $dia];
    }
}

// ---- Mapa de feriados ----
$feriadoMapa = [];
foreach ($feriados as $h) {
    if (! empty($h['date'])) {
        $feriadoMapa[$h['date']] = $h;
    }
}

// ---- Todos os dias do mês ----
$primeiroDia = Carbon::create($ano, $mes, 1);
$ultimoDia = $primeiroDia->copy()->endOfMonth();
$allDays = [];
$d = $primeiroDia->copy();
while ($d->lessThanOrEqualTo($ultimoDia)) {
    $key = $d->toDateString();
    $allDays[] = [
        'key' => $key,
        'carbon' => $d->copy(),
        'scale' => $diasMapa[$key] ?? null,
        'is_weekend' => $d->isWeekend(),
        'is_holiday' => isset($feriadoMapa[$key]),
        'holiday' => $feriadoMapa[$key] ?? null,
    ];
    $d->addDay();
}

// ---- Afastamentos que se sobrepõem ao mês ----
$mesInicio = $primeiroDia->copy()->startOfDay();
$mesFim = $ultimoDia->copy()->endOfDay();

$afastamentosMes = collect();
foreach ($phpFuncionarios as $f) {
    foreach ($f->afastamentos as $a) {
        if (! $a->is_active || $a->start_date === null) {
            continue;
        }
        if ($a->start_date->greaterThan($mesFim)) {
            continue;
        }
        if ($a->end_date !== null && $a->end_date->lessThan($mesInicio)) {
            continue;
        }
        $afastamentosMes->push(['funcionario' => $f, 'afastamento' => $a]);
    }
}
$afastamentosMes = $afastamentosMes->sortBy(fn ($i) => $i['funcionario']->name)->values();

$observacoesItems = [];
foreach ($afastamentosMes as $item) {
    $reason = trim((string) ($item['afastamento']->reason ?? ''));
    $reason = str_ireplace('ferias', 'Férias', $reason);

    $observacoesItems[] = [
        'dates' => $item['afastamento']->start_date?->format('d/m/Y') . ' a ' . ($item['afastamento']->end_date ? $item['afastamento']->end_date->format('d/m/Y') : 'em aberto'),
        'name' => $item['funcionario']->short_name ?? $item['funcionario']->name,
        'type' => $reason,
    ];
}

if ($escalaVersao && ! empty($escalaVersao->obs)) {
    array_unshift($observacoesItems, [
        'dates' => Carbon::create($ano, $mes, 1)->format('m/Y'),
        'name' => 'Observação da versão',
        'type' => (string) $escalaVersao->obs,
    ]);
}

$feriadosItems = [];
foreach ($feriados as $feriado) {
    $fDate = ! empty($feriado['date']) ? Carbon::parse($feriado['date']) : null;
    $feriadosItems[] = [
        'dates' => ($fDate?->format('d/m') ?? ($feriado['date_label'] ?? '')) . ' (' . ($fDate?->locale('pt_BR')->isoFormat('ddd') ?? '') . ')',
        'name' => $feriado['descricao'] ?? '',
    ];
}

$diasCarregados = $phpDias->count();
$plantoesMesTotal = 0;
foreach ($plantoesMes as $lista) {
    $plantoesMesTotal += count($lista);
}

$referenceRow = null;
foreach ($allDays as $dayRow) {
    $scaleEntry = $dayRow['scale'];
    if (! $scaleEntry) {
        continue;
    }

    if ($scaleEntry['src'] === 'php') {
        $dataRef = $dayRow['carbon']->toDateString();
        $plantaoTextoConsolidado = trim((string) ($plantoesTextoPorData[$dataRef] ?? ''));
        $plantaoTexto = $plantaoTextoConsolidado !== ''
            ? $plantaoTextoConsolidado
            : trim((string) ($scaleEntry['obj']->plantao_externo ?? ''));
    }

    if ($plantaoTexto === '') {
        continue;
    }

    $items = $splitPlantaoTexto($plantaoTexto);
    if (count($items) >= 2) {
        $referenceRow = [
            'date' => $dayRow['carbon']->format('d/m/Y'),
            'day' => $dayRow['carbon']->locale('pt_BR')->isoFormat('dddd'),
            'plantao_text' => implode(', ', $items),
        ];
        break;
    }
}
@endphp

<x-report.default
    :title="'ESCALA MENSAL - ' . $mesLabelUpper . ' / ' . $ano"
    :period="$mesLabelFull"
    :generatedAt="$geradoEm"
    origin="Escalas / Escala Mensal"
    :brasao-src="$brasaoSrc ?? asset('assets/brasao.png')"
    :logo-src="$logoSrc ?? asset('assets/logo_grom.png')"
    :watermark-src="$watermarkSrc ?? asset('assets/marca_dagua.png')"
>
    @unless($previewMode)
        <x-slot:toolbar>
            <a href="{{ route('escalas.index', $filters) }}">← Voltar</a>
            <a href="{{ route('escalas.print.pdf', $filters) }}">Imprimir / Salvar PDF paginado</a>
            <span style="font-size:.85em; color:var(--ink-soft);">Pré-visualização da escala {{ $mesLabelFull }}</span>
        </x-slot:toolbar>
    @endunless

    <style>
        .scale-table {
            table-layout: fixed;
            font-size: 7.3pt;
        }
        .scale-table th {
            text-transform: none;
            letter-spacing: 0;
            font-size: 7pt;
            text-align: center;
            padding: 1.1mm 1.2mm;
            line-height: 1;
        }
        .td-date {
            text-align: center;
            white-space: nowrap;
            font-weight: 700;
        }
        .td-day {
            text-align: center;
            white-space: nowrap;
        }
        .td-deleg {
            font-weight: 700;
        }
        .td-span {
            text-align: center;
            color: #555;
            font-style: italic;
            font-size: 8pt;
        }
        .td-holiday-span {
            text-align: center;
            font-weight: 700;
            font-size: 8pt;
        }
        .td-plantao {
            font-size: 7pt;
            white-space: normal;
            overflow-wrap: normal;
            word-break: normal;
        }
        .scale-table td {
            padding: 0.4mm 1.1mm;
            line-height: 1;
        }
        .scale-table tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .obs-section {
            margin-top: 4px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .obs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }
        .obs-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 5px 8px;
            background: #fff;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .obs-card.full {
            grid-column: 1 / -1;
        }
        .obs-card-title {
            font-weight: 700;
            text-align: center;
            margin: 0 0 2px;
            font-size: 9pt;
        }
        .obs-list {
            list-style: none;
            margin: 0;
            padding: 0 2px;
        }
        .obs-list li {
            font-size: 7.7pt;
            line-height: 1.1;
            display: flex;
            gap: 0;
        }
        .obs-dates {
            min-width: 140px;
            flex-shrink: 0;
        }
        .obs-name {
            min-width: 120px;
            flex-shrink: 0;
        }
        .obs-type {
            flex: 1;
        }
        .obs-empty {
            text-align: center;
            color: #666;
            font-size: 8.5pt;
            padding: 6px 0;
        }
    </style>

    <section class="report-body">
        <table class="scale-table">
            <colgroup>
                <col style="width:4.2%">
                <col style="width:5.2%">
                <col style="width:12.5%">
                <col style="width:10.5%">
                <col style="width:8.8%">
                <col style="width:15%">
                <col style="width:43.8%">
            </colgroup>
            <thead>
                <tr>
                    <th colspan="2" style="width:9.4%">Data</th>
                    <th style="width:12.5%">Escrivão</th>
                    <th style="width:10.5%">Operacional</th>
                    <th style="width:8.8%">Fechar</th>
                    <th style="width:15%">Delegada(o)</th>
                    <th>Plantões Externos</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($allDays as $day)
                    @php
                        $carbon = $day['carbon'];
                        $isWeekend = $day['is_weekend'];
                        $isHoliday = $day['is_holiday'];
                        $holiday = $day['holiday'];
                        $scaleEntry = $day['scale'];
                        $dataRef = $carbon->toDateString();
                        $plantaoExtConsolidado = trim((string) ($plantoesTextoPorData[$dataRef] ?? ''));

                        if ($scaleEntry && $scaleEntry['src'] === 'php') {
                            $obj = $scaleEntry['obj'];
                            $escrivao = $obj->escrivao ?? '';
                            $operacional = $obj->operacional ?? '';
                            $fechar = $obj->fechar_nome ?? '';
                            $delegada = $obj->delegada ?? '';
                            $plantaoExt = $plantaoExtConsolidado;
                            if ($plantaoExt === '') {
                                $plantaoExt = $obj->plantao_externo ?? '';
                            }
                            $plantaoItens = $splitPlantaoTexto((string) $plantaoExt);
                            $isDeclHoliday = strtoupper(trim($escrivao)) === 'FERIADO';
                        } else {
                            $escrivao = $operacional = $fechar = $delegada = '';
                            $plantaoExt = $plantaoExtConsolidado;
                            $plantaoItens = $splitPlantaoTexto((string) $plantaoExt);
                            $isDeclHoliday = false;
                        }
                        $isEffHoliday = $isHoliday || $isDeclHoliday;
                    @endphp
                    <tr>
                        <td class="td-date">{{ $carbon->format('d') }}</td>
                        <td class="td-day">{{ strtoupper($carbon->locale('pt_BR')->isoFormat('ddd')) }}</td>
                        @if ($isEffHoliday)
                            <td colspan="5" class="td-holiday-span">
                                @if ($holiday && ! empty($holiday['descricao']))
                                    {{ $holiday['descricao'] }}
                                @elseif (! empty($escrivao) && strtoupper(trim($escrivao)) !== 'FERIADO')
                                    {{ $escrivao }}
                                @else
                                    FERIADO
                                @endif
                            </td>
                            <td class="td-plantao">
                                {{ ! empty($plantaoItens) ? implode(', ', $plantaoItens) : ($plantaoExt ?: '—') }}
                            </td>
                        @elseif ($isWeekend && empty($escrivao) && empty($operacional) && empty($fechar) && empty($delegada))
                            <td class="td-span"></td>
                            <td class="td-span"></td>
                            <td class="td-span"></td>
                            <td class="td-span"></td>
                            <td class="td-plantao">
                                {{ ! empty($plantaoItens) ? implode(', ', $plantaoItens) : ($plantaoExt ?: '—') }}
                            </td>
                        @else
                            <td>{{ $escrivao }}</td>
                            <td>{{ $operacional }}</td>
                            <td>{{ $fechar }}</td>
                            <td class="td-deleg">{{ $delegada }}</td>
                            <td class="td-plantao">
                                {{ ! empty($plantaoItens) ? implode(', ', $plantaoItens) : ($plantaoExt ?: '—') }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (! empty($observacoesItems) || ! empty($feriadosItems))
            <div class="obs-section">
                <div class="obs-grid">
                    <div class="obs-card @if (empty($feriadosItems)) full @endif">
                        <p class="obs-card-title">Observações do mês</p>
                        @if (! empty($observacoesItems))
                            <ul class="obs-list">
                                @foreach ($observacoesItems as $item)
                                    <li>
                                        <span class="obs-dates">{{ $item['dates'] }} - </span>
                                        <span class="obs-name">{{ $item['name'] }}</span>
                                        <span class="obs-type">{{ $item['type'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="obs-empty">Sem observações registradas.</p>
                        @endif
                    </div>

                    @if (! empty($feriadosItems))
                        <div class="obs-card">
                            <p class="obs-card-title">Feriados e Pontos Facultativos</p>
                            <ul class="obs-list">
                                @foreach ($feriadosItems as $item)
                                    <li>
                                        <span class="obs-dates">{{ $item['dates'] }} - </span>
                                        <span class="obs-type">{{ $item['name'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if (!empty($substituicoesDdm))
            @foreach ($substituicoesDdm as $subst)
                <?php
                $delegadoNome = $delegadosExternos->firstWhere('id', $subst->delegado_externo_id)?->short_name ?? 'Delegado Externo';
                ?>
                @php
                    $periodo = \Carbon\Carbon::parse($subst->data_inicio)->format('d/m/Y') . ' a ' . \Carbon\Carbon::parse($subst->data_fim)->format('d/m/Y');
                @endphp
                <?php
                $observacoesItems[] = [
                    'dates' => $periodo,
                    'name' => $delegadoNome,
                    'type' => 'Delegado Substituto (' . $subst->motivo . ')',
                ];
                ?>
            @endforeach
        @endif
    </section>

</x-report.default>
