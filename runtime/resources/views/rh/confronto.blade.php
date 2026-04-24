@extends('layouts.app')

@section('title', 'Confronto de Afastamentos | Grom.Seg')

@section('content')
    @php
        use Illuminate\Support\Carbon;

        $meses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'MarÃ§o', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
        $diasSemana = ['Dom','Seg','Ter','Qua','Qui','Sex','SÃ¡b'];
        $anoAnterior = $ano;
        $mesAnterior = $mes - 1;
        if ($mesAnterior < 1) { $mesAnterior = 12; $anoAnterior--; }
        $anoPosterior = $ano;
        $mesPosterior = $mes + 1;
        if ($mesPosterior > 12) { $mesPosterior = 1; $anoPosterior++; }
    @endphp

    <div class="section-head">
        <div>
            <h1>Confronto de Afastamentos</h1>
            <p class="muted" style="margin: 6px 0 0;">
                VisualizaÃ§Ã£o mensal de quem estÃ¡ afastado, perÃ­odos simultÃ¢neos e colisÃµes crÃ­ticas.
            </p>
        </div>
        <div class="actions">
            <a href="{{ route('rh.confronto.print', ['ano' => $ano, 'mes' => $mes, 'funcionario_id' => $filters['funcionario_id'] ?? '']) }}"
               class="btn secondary" target="_blank">Imprimir A4</a>
            <a href="{{ route('rh.index') }}" class="btn secondary">â† RH/Admin</a>
        </div>
    </div>

    {{-- NavegaÃ§Ã£o de mÃªs --}}
    <section class="card" style="margin-bottom: 14px;">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <a href="{{ route('rh.confronto', ['ano' => $anoAnterior, 'mes' => $mesAnterior]) }}"
               class="btn secondary" style="padding: 4px 12px;">â† {{ $meses[$mesAnterior] }}/{{ $anoAnterior }}</a>
            <strong style="font-size: 1.1rem; flex: 1; text-align: center;">
                {{ $meses[$mes] }} de {{ $ano }}
            </strong>
            <a href="{{ route('rh.confronto', ['ano' => $anoPosterior, 'mes' => $mesPosterior]) }}"
               class="btn secondary" style="padding: 4px 12px;">{{ $meses[$mesPosterior] }}/{{ $anoPosterior }} â†’</a>
        </div>

        <form method="GET" action="{{ route('rh.confronto') }}" style="margin-top: 14px;">
            <div class="form-grid">
                <div class="field">
                    <label>Filtrar funcionÃ¡rio</label>
                    <select name="funcionario_id">
                        <option value="">Todos os funcionÃ¡rios</option>
                        @foreach ($funcionarios as $f)
                            <option value="{{ $f->id }}" @selected(($filters['funcionario_id'] ?? '') === $f->id)>
                                {{ $f->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>MÃªs</label>
                    <select name="mes">
                        @foreach ($meses as $num => $nome)
                            <option value="{{ $num }}" @selected($mes === $num)>{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Ano</label>
                    <input type="number" name="ano" value="{{ $ano }}" min="2000" max="2099">
                </div>
            </div>
            <input type="hidden" name="ano" value="{{ $ano }}">
            <div class="actions" style="margin-top: 8px;">
                <button type="submit">Aplicar</button>
                <a href="{{ route('rh.confronto') }}" class="btn secondary">Limpar</a>
            </div>
        </form>
    </section>

    {{-- Resumo do perÃ­odo --}}
    <div class="cards" style="margin-bottom: 14px;">
        <article class="card">
            <small>Afastamentos no perÃ­odo</small>
            <strong>{{ $afastamentos->count() }}</strong>
            <span>Registros ativos que abrangem {{ $meses[$mes] }}/{{ $ano }}.</span>
        </article>
        <article class="card">
            <small>FuncionÃ¡rios afastados</small>
            <strong>{{ $afastamentos->pluck('funcionario_id')->unique()->count() }}</strong>
            <span>Servidores com ao menos um afastamento no perÃ­odo.</span>
        </article>
        <article class="card">
            <small>ColisÃµes crÃ­ticas</small>
            <strong style="color: {{ $colisoesCriticas->count() > 0 ? '#c0392b' : 'inherit' }}">
                {{ $colisoesCriticas->count() }}
            </strong>
            <span>Dias com 2+ afastamentos em cargos de Delegado/EscrivÃ£o.</span>
        </article>
    </div>

    @if ($colisoesCriticas->count() > 0)
        <div class="alert alert-warn" style="margin-bottom: 14px; padding: 10px 14px; background: #fff3cd; border-left: 4px solid #f39c12; border-radius: 4px;">
            <strong>âš  AtenÃ§Ã£o:</strong>
            ColisÃµes de cargo crÃ­tico detectadas nos dias:
            <strong>{{ $colisoesCriticas->map(fn($d) => sprintf('%02d', $d))->implode(', ') }}</strong>
            de {{ $meses[$mes] }}/{{ $ano }}.
            Verifique cobertura de escala nesses dias.
        </div>
    @endif

    {{-- Tabela de afastamentos no perÃ­odo --}}
    @if ($afastamentos->count() > 0)
        <section class="card" style="margin-bottom: 18px;">
            <h2 style="margin-top: 0;">Afastamentos vigentes em {{ $meses[$mes] }}/{{ $ano }}</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>FuncionÃ¡rio</th>
                            <th>Cargo</th>
                            <th>Setor</th>
                            <th>Motivo</th>
                            <th>InÃ­cio</th>
                            <th>Fim</th>
                            <th>DuraÃ§Ã£o no mÃªs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($afastamentos->sortBy('start_date') as $af)
                            @php
                                $inicio = $af->start_date instanceof Carbon ? $af->start_date : Carbon::parse($af->start_date);
                                $fim    = $af->end_date
                                    ? ($af->end_date instanceof Carbon ? $af->end_date : Carbon::parse($af->end_date))
                                    : null;
                                $periodoInicio = Carbon::create($ano, $mes, 1);
                                $periodoFim    = $periodoInicio->copy()->endOfMonth();
                                $inicioNoMes   = $inicio->lt($periodoInicio) ? $periodoInicio : $inicio;
                                $fimNoMes      = ($fim === null || $fim->gt($periodoFim)) ? $periodoFim : $fim;
                                $diasNoMes     = $inicioNoMes->diffInDays($fimNoMes) + 1;
                                $isCritico     = in_array($af->dia ?? null, $colisoesCriticas->toArray());
                                $cargoNome     = mb_strtolower($af->funcionario?->cargo?->name ?? '');
                                $ehCargoCritico = str_contains($cargoNome, 'delegad') || str_contains($cargoNome, 'escrivao') || str_contains($cargoNome, 'escrivÃ£');
                            @endphp
                            <tr @if ($ehCargoCritico) style="background: #fdf5e6;" @endif>
                                <td><strong>{{ $af->funcionario?->name ?? 'â€”' }}</strong></td>
                                <td>{{ $af->funcionario?->cargo?->name ?? 'â€”' }}</td>
                                <td>{{ $af->funcionario?->sector ?: 'â€”' }}</td>
                                <td>{{ $af->reason }}</td>
                                <td style="white-space: nowrap;">{{ $inicio->format('d/m/Y') }}</td>
                                <td style="white-space: nowrap;">{{ $fim ? $fim->format('d/m/Y') : 'Em aberto' }}</td>
                                <td style="text-align: center; font-weight: bold;">{{ $diasNoMes }} dia(s)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <section class="card" style="margin-bottom: 18px;">
            <p class="muted" style="text-align: center; padding: 24px 0;">
                Nenhum afastamento ativo encontrado para {{ $meses[$mes] }}/{{ $ano }}.
            </p>
        </section>
    @endif

    {{-- Grade calendÃ¡rio --}}
    <section class="card">
        <h2 style="margin-top: 0;">Grade diÃ¡ria â€” {{ $meses[$mes] }}/{{ $ano }}</h2>
        <p class="muted" style="margin: 0 0 14px; font-size: 0.88rem;">
            Cada dia mostra quem estÃ¡ afastado. Dias com
            <span style="background: #fff3cd; padding: 1px 5px; border-radius: 3px; font-weight: bold;">fundo amarelo</span>
            indicam colisÃ£o de cargo crÃ­tico.
        </p>

        <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; font-size: 0.82rem;">
            @foreach (['Dom','Seg','Ter','Qua','Qui','Sex','SÃ¡b'] as $ds)
                <div style="text-align: center; font-weight: bold; padding: 4px; background: #f0f0f0; border-radius: 3px;">
                    {{ $ds }}
                </div>
            @endforeach

            @php
                // Offset do primeiro dia da semana (0=dom)
                $diaSemanaInicio = (int) $periodoInicio->dayOfWeek;
            @endphp
            @for ($off = 0; $off < $diaSemanaInicio; $off++)
                <div></div>
            @endfor

            @foreach ($calendarioDias as $numDia => $dadosDia)
                @php
                    $temAfastamento   = $dadosDia['afastamentos']->count() > 0;
                    $temColisao       = $colisoesCriticas->contains($numDia);
                    $diaCarbon        = $dadosDia['data'];
                    $isHoje           = $diaCarbon->isToday();
                    $bg = $temColisao ? '#fff3cd' : ($temAfastamento ? '#e8f4f8' : '#fff');
                    $border = $isHoje ? '2px solid #2980b9' : '1px solid #ddd';
                @endphp
                <div style="background: {{ $bg }}; border: {{ $border }}; border-radius: 4px; padding: 4px 5px; min-height: 70px;">
                    <div style="font-weight: bold; font-size: 0.9rem; margin-bottom: 3px;">
                        {{ sprintf('%02d', $numDia) }}
                        @if ($isHoje)
                            <span style="color: #2980b9; font-size: 0.7rem;">hoje</span>
                        @endif
                        @if ($temColisao)
                            <span style="color: #c0392b; font-size: 0.7rem; font-weight: bold;">âš </span>
                        @endif
                    </div>
                    @foreach ($dadosDia['afastamentos'] as $af)
                        <div style="font-size: 0.72rem; line-height: 1.4; padding: 1px 0; border-top: 1px dotted #bbb; color: #333;">
                            {{ $af->funcionario?->short_name ?: \Illuminate\Support\Str::limit($af->funcionario?->name ?? 'â€”', 18) }}
                            <span style="color: #777; font-style: italic;">{{ \Illuminate\Support\Str::limit($af->reason, 12) }}</span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    <div style="margin-top: 10px; text-align: right; font-size: 0.8rem;" class="muted">
        Gerado em {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->name }}.
    </div>
@endsection

