<x-report.default
    title="Relatório de Afastamentos"
    :period="$periodoLabel"
    :generatedAt="now()"
    origin="RH / Afastamentos"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="\App\Support\ReportAsset::dataUri('assets/brasao.png')"
    :logo-src="\App\Support\ReportAsset::dataUri('assets/logo_grom.png')"
    :watermark-src="\App\Support\ReportAsset::dataUri('assets/marca_dagua.png')"
>
    <x-slot:toolbar>
        <a href="{{ url()->previous() }}" class="btn secondary" style="font-size:0.85rem;">← Voltar</a>
        <button onclick="window.print()" style="font-size:0.85rem;">Imprimir / Salvar PDF</button>
        {{-- Filtros de impressão --}}
        <form method="GET" action="{{ route('rh.afastamentos.relatorio') }}" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
            <select name="year" style="font-size:0.80rem; padding:3px 6px;">
                @for ($y = now()->year + 1; $y >= 2020; $y--)
                    <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                @endfor
            </select>
            <select name="month" style="font-size:0.80rem; padding:3px 6px;">
                <option value="0" @selected($month == 0)>Ano inteiro</option>
                @foreach ([1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'] as $n => $m)
                    <option value="{{ $n }}" @selected($n == $month)>{{ $m }}</option>
                @endforeach
            </select>
            <select name="funcionario_id" style="font-size:0.80rem; padding:3px 6px;">
                <option value="">Todos os servidores</option>
                @foreach (\App\Models\RhFuncionario::orderBy('name')->get() as $f)
                    <option value="{{ $f->id }}" @selected(($filters['funcionario_id'] ?? '') == $f->id)>{{ $f->name }}</option>
                @endforeach
            </select>
            <select name="reason" style="font-size:0.80rem; padding:3px 6px;">
                <option value="" @selected(empty($filters['reason']))>Todos os motivos</option>
                @foreach (['Férias', 'Licença Prêmio', 'Saúde', 'Outros'] as $r)
                    <option value="{{ $r }}" @selected(($filters['reason'] ?? '') === $r)>{{ $r }}</option>
                @endforeach
            </select>
            <select name="modo" style="font-size:0.80rem; padding:3px 6px;">
                <option value="todos" @selected($modo === 'todos')>Lista unificada</option>
                <option value="por-funcionario" @selected($modo === 'por-funcionario')>Por servidor</option>
            </select>
            <button type="submit" style="font-size:0.80rem; padding:3px 12px;">Atualizar</button>
        </form>
    </x-slot:toolbar>

    <x-slot:summary>
        <article class="card">
            <small>Total afastamentos</small>
            <strong>{{ $afastamentos->count() }}</strong>
            <span>no período</span>
        </article>
        <article class="card">
            <small>Servidores envolvidos</small>
            <strong>{{ $porFuncionario->count() }}</strong>
            <span>{{ $selectedFuncionario ? $selectedFuncionario->name : 'todos' }}</span>
        </article>
        <article class="card">
            <small>Período</small>
            <strong style="font-size:9pt;">{{ $periodoLabel }}</strong>
            <span>{{ $periodoInicio->format('d/m/Y') }} a {{ $periodoFim->format('d/m/Y') }}</span>
        </article>
        @if ($conflictIds->isNotEmpty())
        <article class="card" style="border-left: 3px solid #e67e22;">
            <small>⚠ Conflitos detectados</small>
            <strong style="color:#c0392b;">{{ $conflictIds->count() }}</strong>
            <span>afastamentos simultâneos</span>
        </article>
        @else
        <article class="card">
            <small>Modo</small>
            <strong>{{ $modo === 'por-funcionario' ? 'Por servidor' : 'Lista unificada' }}</strong>
            <span>{{ $selectedFuncionario ? $selectedFuncionario->cargo?->name : '' }}</span>
        </article>
        @endif
    </x-slot:summary>

    {{-- Legenda de conflito --}}
    @if ($conflictIds->isNotEmpty())
    <p style="font-size:7.5pt; color:#c0392b; background:#fdf3ec; border:0.5px solid #e59866; border-radius:4px; padding:4px 8px; margin-bottom:4mm;">
        ⚠ Linhas marcadas com fundo <span style="background:#fdebd0; padding:0 4px; border-radius:2px;">laranja</span>
        indicam períodos em que mais de um servidor está afastado simultaneamente.
    </p>
    @endif

    @if ($modo === 'por-funcionario')
        {{-- ── Modo: uma seção por funcionário ─────────────────────── --}}
        @forelse ($porFuncionario as $item)
            @php $func = $item['funcionario']; $afas = $item['afastamentos']; @endphp
            <div style="margin-bottom: 10mm; page-break-inside: avoid;">
                <h3 style="font-size:9.5pt; font-weight:700; margin:0 0 4px; padding-bottom:3px; border-bottom:0.8px solid #bbb;">
                    {{ $func?->name ?? '—' }}
                    <span style="font-weight:400; font-size:8.5pt; color:#666; margin-left:8px;">
                        {{ $func?->cargo?->name ?: '—' }}{{ $func?->sector ? ' · ' . $func->sector : '' }}
                        · Matr. {{ $func?->matricula }}
                    </span>
                </h3>
                <table style="width:100%; border-collapse:collapse; font-size:8pt; margin-top:4px;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="text-align:left; padding:3px 6px; border:0.5px solid #ccc;">Motivo</th>
                            <th style="text-align:center; padding:3px 6px; border:0.5px solid #ccc; width:90px;">Início</th>
                            <th style="text-align:center; padding:3px 6px; border:0.5px solid #ccc; width:90px;">Término</th>
                            <th style="text-align:center; padding:3px 6px; border:0.5px solid #ccc; width:60px;">Dias</th>
                            <th style="text-align:center; padding:3px 6px; border:0.5px solid #ccc; width:80px;">Situação</th>
                            <th style="text-align:center; padding:3px 6px; border:0.5px solid #ccc; width:60px;">Conflito</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($afas as $af)
                            @php $isConflict = $conflictIds->contains($af->id); @endphp
                            <tr style="{{ $isConflict ? 'background:#fdebd0;' : '' }}">
                                <td style="padding:3px 6px; border:0.5px solid #ddd;">
                                    {{ $af->reason }}
                                    @if ($af->notes)
                                        <br><span style="font-size:7.5pt; color:#777;">{{ $af->notes }}</span>
                                    @endif
                                </td>
                                <td style="padding:3px 6px; border:0.5px solid #ddd; text-align:center;">{{ $af->start_date?->format('d/m/Y') }}</td>
                                <td style="padding:3px 6px; border:0.5px solid #ddd; text-align:center;">
                                    {{ $af->end_date ? $af->end_date->format('d/m/Y') : 'Em aberto' }}
                                </td>
                                <td style="padding:3px 6px; border:0.5px solid #ddd; text-align:center;">
                                    {{ $af->end_date ? ($af->start_date->diffInDays($af->end_date) + 1) : '—' }}
                                </td>
                                <td style="padding:3px 6px; border:0.5px solid #ddd; text-align:center;">{{ $af->statusLabel() }}</td>
                                <td style="padding:3px 6px; border:0.5px solid #ddd; text-align:center;">
                                    @if ($isConflict)
                                        <span style="color:#c0392b; font-weight:700; font-size:7.5pt;">⚠ Sim</span>
                                    @else
                                        <span style="color:#888;">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p style="color:#888; font-style:italic; font-size:9pt; margin-top:8mm;">Nenhum afastamento encontrado no período selecionado.</p>
        @endforelse

    @else
        {{-- ── Modo: lista unificada ───────────────────────────────── --}}
        <table style="width:100%; border-collapse:collapse; font-size:8pt; margin-top:4mm;">
            <thead>
                <tr style="background:#f0f0f0;">
                    <th style="text-align:left; padding:3px 6px; border:0.5px solid #ccc; width:22%;">Servidor</th>
                    <th style="text-align:left; padding:3px 6px; border:0.5px solid #ccc; width:14%;">Cargo</th>
                    <th style="text-align:left; padding:3px 6px; border:0.5px solid #ccc;">Motivo</th>
                    <th style="text-align:center; padding:3px 6px; border:0.5px solid #ccc; width:80px;">Início</th>
                    <th style="text-align:center; padding:3px 6px; border:0.5px solid #ccc; width:80px;">Término</th>
                    <th style="text-align:center; padding:3px 6px; border:0.5px solid #ccc; width:50px;">Dias</th>
                    <th style="text-align:center; padding:3px 6px; border:0.5px solid #ccc; width:72px;">Situação</th>
                    <th style="text-align:center; padding:3px 6px; border:0.5px solid #ccc; width:56px;">Conflito</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($afastamentos as $af)
                    @php $isConflict = $conflictIds->contains($af->id); @endphp
                    <tr style="{{ $isConflict ? 'background:#fdebd0;' : '' }}">
                        <td style="padding:3px 6px; border:0.5px solid #ddd; font-weight:500;">{{ $af->funcionario?->name ?? '—' }}</td>
                        <td style="padding:3px 6px; border:0.5px solid #ddd; color:#555;">{{ $af->funcionario?->cargo?->name ?? '—' }}</td>
                        <td style="padding:3px 6px; border:0.5px solid #ddd;">
                            {{ $af->reason }}
                            @if ($af->notes)
                                <br><span style="font-size:7.5pt; color:#777;">{{ $af->notes }}</span>
                            @endif
                        </td>
                        <td style="padding:3px 6px; border:0.5px solid #ddd; text-align:center;">{{ $af->start_date?->format('d/m/Y') }}</td>
                        <td style="padding:3px 6px; border:0.5px solid #ddd; text-align:center;">
                            {{ $af->end_date ? $af->end_date->format('d/m/Y') : 'Em aberto' }}
                        </td>
                        <td style="padding:3px 6px; border:0.5px solid #ddd; text-align:center;">
                            {{ $af->end_date ? ($af->start_date->diffInDays($af->end_date) + 1) : '—' }}
                        </td>
                        <td style="padding:3px 6px; border:0.5px solid #ddd; text-align:center;">{{ $af->statusLabel() }}</td>
                        <td style="padding:3px 6px; border:0.5px solid #ddd; text-align:center;">
                            @if ($isConflict)
                                <span style="color:#c0392b; font-weight:700; font-size:7.5pt;">⚠ Sim</span>
                            @else
                                <span style="color:#aaa; font-size:7.5pt;">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="padding:6px; text-align:center; color:#888; font-style:italic;">
                            Nenhum afastamento encontrado no período selecionado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($afastamentos->isNotEmpty())
            <p style="font-size:7.5pt; color:#888; margin-top:4mm; text-align:right;">
                Total: {{ $afastamentos->count() }} afastamento(s) · {{ $porFuncionario->count() }} servidor(es)
                @if ($conflictIds->isNotEmpty())
                    · <span style="color:#c0392b;">{{ $conflictIds->count() }} com conflito de datas</span>
                @endif
            </p>
        @endif
    @endif

</x-report.default>
