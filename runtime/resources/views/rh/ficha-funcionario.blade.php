<x-report.default
    title="Ficha Individual do Servidor"
    period="{{ $funcionario->name }}{{ $funcionario->matricula ? ' — Matrícula ' . $funcionario->matricula : '' }}"
    :generatedAt="now()"
    origin="RH / Funcionários"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="\App\Support\ReportAsset::dataUri('assets/brasao.png')"
    :logo-src="\App\Support\ReportAsset::dataUri('assets/logo_grom.png')"
    :watermark-src="\App\Support\ReportAsset::dataUri('assets/marca_dagua.png')"
>
    <x-slot:toolbar>
        <a href="{{ route('rh.index') }}" class="btn secondary" style="font-size:0.85rem;">← Voltar ao RH</a>
        <button onclick="window.print()" style="font-size:0.85rem;">Imprimir / Salvar PDF</button>
        <span style="color:#aaa; font-size:0.80rem;">{{ $funcionario->name }}</span>
    </x-slot:toolbar>

    <x-slot:summary>
        <article class="card">
            <small>Situação</small>
            <strong>{{ $funcionario->is_active ? 'Ativo' : 'Inativo' }}</strong>
            <span>{{ $funcionario->concorre_escala ? 'Concorre à escala' : 'Não concorre' }}</span>
        </article>
        <article class="card">
            <small>Cargo</small>
            <strong>{{ $funcionario->cargo?->name ?: '—' }}</strong>
            <span>{{ $funcionario->sector ?: 'Sem setor' }}</span>
        </article>
        <article class="card">
            <small>Afastamentos</small>
            <strong>{{ $funcionario->afastamentos->count() }}</strong>
            <span>no histórico</span>
        </article>
        <article class="card">
            <small>Plantões externos</small>
            <strong>{{ $totalPlantoes }}</strong>
            <span>registrados</span>
        </article>
    </x-slot:summary>

    {{-- ── Dados do Servidor ──────────────────────────────────────────── --}}
    <h3 style="font-size:9pt; font-weight:700; margin:0 0 6px; padding-bottom:3px; border-bottom:0.8px solid #999;">Dados do Servidor</h3>
    <dl style="display:grid; grid-template-columns:160px 1fr; gap:3px 10px; font-size:8.5pt; margin-bottom:14px;">
        <dt style="color:#555;">Nome completo</dt>
        <dd style="font-weight:500;">{{ $funcionario->name }}</dd>

        <dt style="color:#555;">Nome simplificado</dt>
        <dd style="font-weight:500;">{{ $funcionario->short_name ?: '—' }}</dd>

        <dt style="color:#555;">Matrícula</dt>
        <dd style="font-weight:500;">{{ $funcionario->matricula ?: '—' }}</dd>

        <dt style="color:#555;">Cargo</dt>
        <dd style="font-weight:500;">{{ $funcionario->cargo?->name ?: '—' }}</dd>

        <dt style="color:#555;">Setor</dt>
        <dd style="font-weight:500;">{{ $funcionario->sector ?: '—' }}</dd>

        <dt style="color:#555;">Telefone</dt>
        <dd style="font-weight:500;">{{ $funcionario->phone ?: '—' }}</dd>

        <dt style="color:#555;">CPF</dt>
        <dd style="font-weight:500;">{{ $funcionario->cpf ?: '—' }}</dd>

        <dt style="color:#555;">RG</dt>
        <dd style="font-weight:500;">{{ $funcionario->rg ?: '—' }}</dd>

        <dt style="color:#555;">Nascimento</dt>
        <dd style="font-weight:500;">{{ $funcionario->birth_date?->format('d/m/Y') ?: '—' }}</dd>

        <dt style="color:#555;">Admissão</dt>
        <dd style="font-weight:500;">{{ $funcionario->admission_date?->format('d/m/Y') ?: '—' }}</dd>

        <dt style="color:#555;">Designação</dt>
        <dd style="font-weight:500;">{{ $funcionario->designation_date?->format('d/m/Y') ?: '—' }}</dd>

        @if ($funcionario->departure_date)
            <dt style="color:#555;">Saída</dt>
            <dd style="font-weight:500;">{{ $funcionario->departure_date->format('d/m/Y') }}</dd>
        @endif

        @if ($funcionario->notes)
            <dt style="color:#555;">Observações</dt>
            <dd style="font-weight:500;">{{ $funcionario->notes }}</dd>
        @endif
    </dl>

    {{-- ── Afastamentos ───────────────────────────────────────────────── --}}
    <h3 style="font-size:9pt; font-weight:700; margin:0 0 6px; padding-bottom:3px; border-bottom:0.8px solid #999;">
        Histórico de Afastamentos ({{ $funcionario->afastamentos->count() }})
    </h3>

    @if ($funcionario->afastamentos->isNotEmpty())
        <table style="margin-bottom:14px;">
            <thead>
                <tr>
                    <th>Motivo</th>
                    <th style="text-align:center;">Início</th>
                    <th style="text-align:center;">Término</th>
                    <th style="text-align:center;">Situação</th>
                    <th>Observações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($funcionario->afastamentos as $af)
                    <tr>
                        <td>{{ $af->reason }}</td>
                        <td style="text-align:center;">{{ $af->start_date?->format('d/m/Y') ?: '—' }}</td>
                        <td style="text-align:center;">{{ $af->end_date?->format('d/m/Y') ?: 'Indeterminado' }}</td>
                        <td style="text-align:center;">
                            <span class="tag {{ $af->statusTone() === 'good' ? 'good' : 'warn' }}">
                                {{ $af->statusLabel() }}
                            </span>
                        </td>
                        <td>{{ $af->notes ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="font-style:italic; color:#666; font-size:8.5pt; margin-bottom:14px;">Nenhum afastamento registrado.</p>
    @endif

    {{-- ── Plantões Externos ──────────────────────────────────────────── --}}
    <h3 style="font-size:9pt; font-weight:700; margin:0 0 6px; padding-bottom:3px; border-bottom:0.8px solid #999;">
        Plantões Externos ({{ $totalPlantoes }} no total)
    </h3>

    @if ($plantoesPorTipo->isNotEmpty())
        <p style="font-size:8.5pt; margin-bottom:8px;">
            @foreach ($plantoesPorTipo as $tipo => $qtd)
                <strong>{{ $tipo }}:</strong> {{ $qtd }}&nbsp;&nbsp;
            @endforeach
        </p>
    @endif

    @if ($plantoes->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th style="text-align:center;">Data</th>
                    <th>Tipo de Plantão</th>
                    <th style="text-align:center;">Sigla</th>
                    <th>Unidade</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($plantoes as $p)
                    <tr>
                        <td style="text-align:center;">{{ $p->data?->format('d/m/Y') }}</td>
                        <td>{{ $p->plantaoExterno?->nome ?: '—' }}</td>
                        <td style="text-align:center;">{{ $p->plantaoExterno?->sigla ?: '—' }}</td>
                        <td>{{ $p->plantaoExterno?->unidade ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="font-style:italic; color:#666; font-size:8.5pt;">Nenhum plantão externo registrado.</p>
    @endif

</x-report.default>
