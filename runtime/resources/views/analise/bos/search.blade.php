@extends('layouts.app')

@section('title', 'Pesquisa de Vítima / Autor | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Pesquisa por vítima ou autor</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Busca nominal nos BOs importados (web) e no banco legado (Python), quando disponível.
                Cada ocorrência é exibida uma única vez, com todas as naturezas e pessoas encontradas.
            </p>
        </div>
    </div>

    @if ($legadoWarning)
        <div class="alert alert-warn" style="margin-bottom: 14px;">
            Banco legado indisponível — a pesquisa retorna apenas registros importados pelo sistema web.
        </div>
    @endif

    {{-- ── Formulário de busca ──────────────────────────────────────────── --}}
    <section class="card" style="margin-bottom: 20px;">
        <form method="GET" action="{{ route('analise.bos.search') }}"
              style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">

            <div style="flex:1 1 240px;">
                <label style="display:block; margin-bottom:4px; font-weight:600;">Nome</label>
                <input type="text"
                       name="q"
                       value="{{ e($q) }}"
                       placeholder="Ex.: Maria Silva"
                       style="width:100%; padding:7px 10px; border:1px solid var(--bd); border-radius:4px;"
                       autofocus>
            </div>

            <div style="flex:0 1 160px;">
                <label style="display:block; margin-bottom:4px; font-weight:600;">Papel</label>
                <select name="papel"
                        style="width:100%; padding:7px 10px; border:1px solid var(--bd); border-radius:4px;">
                    <option value=""      @selected($papel === '')>Todos</option>
                    <option value="vitima" @selected($papel === 'vitima')>Somente vítima</option>
                    <option value="autor"  @selected($papel === 'autor')>Somente autor</option>
                </select>
            </div>

            <div>
                <button type="submit" class="btn">Pesquisar</button>
                @if ($hasSearch)
                    <a class="btn secondary" href="{{ route('analise.bos.search') }}">Limpar</a>
                    @if ($totalBos > 0)
                        <a class="btn"
                           style="background:#27ae60; color:#fff;"
                           href="{{ route('analise.bos.search', array_filter(['q' => $q, 'papel' => $papel, 'imprimir' => '1'])) }}"
                           target="_blank"
                           title="Abre o relatório imprimível em nova aba">
                            &#128438; Relatório
                        </a>
                    @endif
                @endif
            </div>
        </form>
    </section>

    {{-- ── Resultados ───────────────────────────────────────────────────── --}}
    @if ($hasSearch)

        {{-- KPIs --}}
        @if ($totalBos > 0)
        <div class="cards" style="margin-bottom:1.5rem">
            <article class="card" style="text-align:center">
                <small>Ocorrências (BOs únicos)</small>
                <strong style="font-size:1.8rem">{{ $totalBos }}</strong>
            </article>
            <article class="card" style="text-align:center">
                <small>Com MPU</small>
                <strong style="font-size:1.8rem; color:{{ $totalComMpu > 0 ? '#e67e22' : 'inherit' }}">{{ $totalComMpu }}</strong>
            </article>
            <article class="card" style="text-align:center">
                <small>Com IP instaurado</small>
                <strong style="font-size:1.8rem">{{ $totalComIp }}</strong>
            </article>
            <article class="card" style="text-align:center">
                <small>Flagrante</small>
                <strong style="font-size:1.8rem; color:{{ $totalFlagrante > 0 ? '#c0392b' : 'inherit' }}">{{ $totalFlagrante }}</strong>
            </article>
        </div>
        @endif

        <section class="card">
            <h2 style="margin-top:0;">
                {{ $totalBos }} ocorrência{{ $totalBos !== 1 ? 's' : '' }} com
                <strong>{{ e($q) }}</strong>
                @if ($papel)
                    — {{ $papel === 'vitima' ? 'vítima' : 'autor' }}
                @endif
            </h2>

            @if (empty($results))
                <p class="muted">Nenhum resultado encontrado para "{{ e($q) }}".</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="font-size:.82rem; white-space:nowrap;">
                        <thead>
                            <tr>
                                <th>Nº RDO</th>
                                <th>Data</th>
                                <th>Lavrado</th>
                                <th style="white-space:normal; min-width:200px;">Nome(s) encontrado(s)</th>
                                <th style="white-space:normal; min-width:180px;">Naturezas</th>
                                <th>Flag.</th>
                                <th>AI</th>
                                <th>Área</th>
                                <th>Cartório (BO)</th>
                                <th>MPU</th>
                                <th>CNJ MPU</th>
                                <th>Nº IP</th>
                                <th>Cartório (IP)</th>
                                <th>CNJ IP</th>
                                <th>Fonte</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $row)
                                <tr style="{{ !empty($row['flagrante']) ? 'background:#fff5f5;' : '' }}">
                                    <td><strong>{{ $row['spj_fmt'] ?? $row['spj'] ?? '' }}</strong></td>
                                    <td>{{ $row['data_ocorrencia'] ?? '' }}</td>
                                    <td class="td-center">
                                        @php $lav = strtoupper(trim($row['lavrado'] ?? '')); @endphp
                                        @if (str_contains($lav, 'DDM'))
                                            <span class="tag" style="background:#dbeafe; color:#1e40af;">DDM</span>
                                        @elseif (!empty($row['lavrado']))
                                            <span style="font-size:.8em;">{{ $row['lavrado'] }}</span>
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                    <td style="white-space:normal;">
                                        @foreach (explode("\n", $row['pessoas'] ?? '') as $pessoaLinha)
                                            @if (trim($pessoaLinha) !== '')
                                                @if (str_contains($pessoaLinha, '[Vítima]'))
                                                    @php $nomeVit = mb_strtoupper(str_replace(' [Vítima]', '', $pessoaLinha)); @endphp
                                                    <span style="color:#1e40af; display:block; line-height:1.6;">
                                                        <strong>{{ $nomeVit }}</strong>
                                                        <small style="background:#dbeafe; color:#1e40af; border-radius:3px; padding:0 4px; margin-left:3px;">Vítima</small>
                                                    </span>
                                                @elseif (str_contains($pessoaLinha, '[Autor]'))
                                                    @php $nomeAut = mb_strtoupper(str_replace(' [Autor]', '', $pessoaLinha)); @endphp
                                                    <span style="color:#991b1b; display:block; line-height:1.6;">
                                                        <strong>{{ $nomeAut }}</strong>
                                                        <small style="background:#fee2e2; color:#991b1b; border-radius:3px; padding:0 4px; margin-left:3px;">Autor</small>
                                                    </span>
                                                @else
                                                    <span style="display:block; line-height:1.6;"><strong>{{ mb_strtoupper($pessoaLinha) }}</strong></span>
                                                @endif
                                            @endif
                                        @endforeach
                                    </td>
                                    <td style="white-space:normal; font-size:.78rem; color:#555;">
                                        {{ $row['naturezas'] ?? '' ?: '—' }}
                                    </td>
                                    <td class="td-center">
                                        @if (!empty($row['flagrante']))
                                            <span class="tag" style="background:#fee2e2; color:#991b1b;" title="Flagrante">F</span>
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                    <td class="td-center">
                                        @if (!empty($row['ato_infracional']))
                                            <span class="tag warn" title="Ato Infracional (adolescente)">AI</span>
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $row['area_fato'] ?? '' ?: '—' }}</td>
                                    <td>{{ $row['cartorio_designado'] ?? '' ?: '—' }}</td>
                                    <td>
                                        @if (!empty($row['mpu_numero']))
                                            <abbr title="{{ $row['mpu_numero'] }}">{{ Str::limit($row['mpu_numero'], 18) }}</abbr>
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $row['cnj_mpu'] ?? '' ?: '—' }}</td>
                                    <td>{{ $row['num_ip'] ?? '' ?: '—' }}</td>
                                    <td>{{ $row['cartorio_ip'] ?? '' ?: '—' }}</td>
                                    <td>{{ $row['cnj_ip'] ?? '' ?: '—' }}</td>
                                    <td>
                                        @if (($row['fonte'] ?? '') === 'legado')
                                            <span class="muted" title="Banco Python (legado)">legado</span>
                                        @else
                                            <span style="color:var(--green, #080);" title="Importado via web">web</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($totalBos === 100)
                    <p class="muted" style="margin-top:10px; font-size:.85em;">
                        Exibindo os primeiros 100 BOs. Refine o nome para resultados mais precisos.
                    </p>
                @endif

                <p class="muted" style="margin-top:10px; font-size:.8em;">
                    <strong>Flag.</strong> = Flagrante &nbsp;|&nbsp;
                    <strong>AI</strong> = Ato Infracional (adolescente como autor) &nbsp;|&nbsp;
                    <strong>Lavrado</strong> = DDM (Delegacia de Defesa da Mulher) ou Outras Unidades &nbsp;|&nbsp;
                    <strong>Cartório (BO)</strong> = cartório designado na fase do boletim &nbsp;|&nbsp;
                    <strong>Cartório (IP)</strong> = cartório que conduz o inquérito
                </p>
                <p class="muted" style="margin-top:4px; font-size:.8em; color:#c0392b;">
                    ⚠ Sexo (homem/mulher) e faixa etária (criança/adolescente) como vítima não são capturados
                    na planilha de importação atual — esses dados não estão disponíveis no sistema.
                </p>
            @endif
        </section>
    @elseif (!empty($q))
        <p class="muted">Digite pelo menos 3 caracteres para pesquisar.</p>
    @endif
@endsection

