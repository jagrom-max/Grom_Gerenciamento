@php
use Carbon\Carbon;

$ano = $filters['ano'];
$mes = $filters['mes'];
$previewMode = request()->boolean('preview');
$mesLabel = Carbon::create()->month($mes)->locale('pt_BR')->isoFormat('MMMM');
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
} elseif (! empty($snapshot['scale_rows'])) {
    foreach ($snapshot['scale_rows'] as $row) {
        if (! empty($row['date'])) {
            $diasMapa[$row['date']] = ['src' => 'legacy', 'obj' => $row];
        }
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

// Fallback legado
$legacyAfastamentos = [];
if ($afastamentosMes->isEmpty() && ! empty($snapshot['afastamentos_mes'])) {
    $legacyAfastamentos = $snapshot['afastamentos_mes'];
}

$observacoesItems = [];
foreach ($afastamentosMes as $item) {
    $observacoesItems[] = [
        'dates' => $item['afastamento']->start_date?->format('d/m/Y') . ' a ' . ($item['afastamento']->end_date ? $item['afastamento']->end_date->format('d/m/Y') : 'em aberto'),
        'name' => $item['funcionario']->short_name ?? $item['funcionario']->name,
        'type' => $item['afastamento']->reason ?? '',
    ];
}

foreach ($legacyAfastamentos as $la) {
    $observacoesItems[] = [
        'dates' => (! empty($la['data_inicio']) ? Carbon::parse($la['data_inicio'])->format('d/m/Y') : '??') . ' a ' . (! empty($la['data_fim']) ? Carbon::parse($la['data_fim'])->format('d/m/Y') : 'em aberto'),
        'name' => $la['funcionario_nome'] ?? '',
        'type' => $la['tipo'] ?? '',
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
        'type' => $feriado['tipo'] ?? '',
    ];
}

$diasCarregados = $phpDias->isNotEmpty() ? $phpDias->count() : count($snapshot['scale_rows'] ?? []);
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
    } else {
        $plantaoTexto = trim((string) ($scaleEntry['obj']['plantao_externo'] ?? ''));
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
    title="Escala Mensal"
    :period="$mesLabelFull"
    :generatedAt="$geradoEm"
    origin="Escalas / Escala Mensal"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="asset('assets/brasao.png')"
    :logo-src="asset('assets/logo_grom.png')"
    :watermark-src="asset('assets/marca_dagua.png')"
>
    @unless($previewMode)
        <x-slot:toolbar>
            <a href="{{ route('escalas.index', $filters) }}">← Voltar</a>
            <button onclick="window.print()">Imprimir / Salvar PDF</button>
            <span style="font-size:.85em; color:var(--ink-soft);">Pré-visualização da escala {{ $mesLabelFull }}</span>
        </x-slot:toolbar>
    @endunless

    <x-slot:summary>
        <article class="card">
            <small>Dias carregados</small>
            <strong>{{ $diasCarregados }}</strong>
            <span>Base usada na composição da escala mensal.</span>
        </article>
        <article class="card">
            <small>Plantões externos</small>
            <strong>{{ $plantoesMesTotal }}</strong>
            <span>Quantidade total registrada no mês.</span>
        </article>
        <article class="card">
            <small>Observações</small>
            <strong>{{ count($observacoesItems) }}</strong>
            <span>Afastamentos consolidados no período.</span>
        </article>
        <article class="card">
            <small>Feriados / P. Fac.</small>
            <strong>{{ count($feriadosItems) }}</strong>
            <span>Itens destacados no bloco final.</span>
        </article>
    </x-slot:summary>

    <style>
        .scale-note {
            margin-bottom: 3mm;
            font-size: 8.5pt;
            color: var(--ink-soft);
            line-height: 1.5;
        }
        .scale-table {
            table-layout: fixed;
            font-size: 8.2pt;
        }
        .scale-table th {
            text-transform: none;
            letter-spacing: 0;
            font-size: 7.9pt;
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
            font-size: 8pt;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
        }
        .obs-section {
            margin-top: 12px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .obs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .obs-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 8px 10px;
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
            margin-bottom: 6px;
            font-size: 9pt;
        }
        .obs-list {
            list-style: none;
            padding: 0 4px;
        }
        .obs-list li {
            font-size: 8.5pt;
            line-height: 1.6;
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
        <div class="scale-note">
            <strong>Escala Mensal</strong> de {{ $mesLabelFull }}.
            @if ($escalaVersao)
                @if ($escalaVersao->eh_definitiva)
                    Versão <strong>definitiva</strong> {{ $escalaVersao->versao }}.
                @else
                    Versão <strong>provisória</strong> {{ $escalaVersao->versao }}.
                @endif
            @elseif ($phpVersao)
                Versão {{ $phpVersao }}.
            @endif
        </div>

        <table class="scale-table">
            <colgroup>
                <col style="width:6%">
                <col style="width:5%">
                <col style="width:16%">
                <col style="width:14%">
                <col style="width:12%">
                <col style="width:15%">
                <col style="width:32%">
            </colgroup>
            <thead>
                <tr>
                    <th style="width:6%">Data</th>
                    <th style="width:5%">Dia</th>
                    <th style="width:16%">Escrivão</th>
                    <th style="width:14%">Operacional</th>
                    <th style="width:12%">Fechar</th>
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

                        if ($scaleEntry && $scaleEntry['src'] === 'php') {
                            $obj = $scaleEntry['obj'];
                            $escrivao = $obj->escrivao ?? '';
                            $operacional = $obj->operacional ?? '';
                            $fechar = $obj->fechar_nome ?? '';
                            $delegada = $obj->delegada ?? '';
                            $dataRef = $carbon->toDateString();
                            $plantaoExt = trim((string) ($plantoesTextoPorData[$dataRef] ?? ''));
                            if ($plantaoExt === '') {
                                $plantaoExt = $obj->plantao_externo ?? '';
                            }
                            $plantaoItens = $splitPlantaoTexto((string) $plantaoExt);
                            $isDeclHoliday = strtoupper(trim($escrivao)) === 'FERIADO';
                        } elseif ($scaleEntry && $scaleEntry['src'] === 'legacy') {
                            $obj = $scaleEntry['obj'];
                            $escrivao = trim((string) ($obj['escrivao'] ?? ''));
                            $operacional = trim((string) ($obj['operacional'] ?? ''));
                            $fechar = trim((string) ($obj['fechar'] ?? ''));
                            $delegada = trim((string) ($obj['delegada'] ?? ''));
                            $plantaoExt = trim((string) ($obj['plantao_externo'] ?? ''));
                            $plantaoItens = $splitPlantaoTexto($plantaoExt);
                            $isDeclHoliday = strtoupper($escrivao) === 'FERIADO' || ($obj['display_mode'] ?? '') === 'holiday';
                        } else {
                            $escrivao = $operacional = $fechar = $delegada = $plantaoExt = '';
                            $plantaoItens = [];
                            $isDeclHoliday = false;
                        }
                        $isEffHoliday = $isHoliday || $isDeclHoliday;
                    @endphp
                    <tr>
                        <td class="td-date">{{ $carbon->format('d/m') }}</td>
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
                                        <span class="obs-type">
                                            {{ $item['name'] }}
                                            @if (! empty($item['type']))
                                                ({{ $item['type'] }})
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </section>

    @unless($previewMode)
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 500);
            });
        </script>
    @endunless
</x-report.default>
