@extends('layouts.app')

@section('title', 'Plantões | Grom.Seg')

@section('content')
    <style>
        .plantoes-overview {
            display: grid;
            gap: 18px;
        }

        .plantoes-summary {
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            gap: 16px;
            margin-bottom: 18px;
        }

        .plantoes-hero {
            position: relative;
            overflow: hidden;
            padding: 24px 26px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(15, 39, 68, 0.98) 0%, rgba(22, 56, 95, 0.96) 60%, rgba(7, 21, 33, 0.98) 100%);
            color: #f4f7fa;
            box-shadow: 0 22px 46px rgba(7, 21, 33, 0.18);
        }

        .plantoes-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(212, 175, 55, 0.14), transparent 32%);
            pointer-events: none;
        }

        .plantoes-hero > * {
            position: relative;
            z-index: 1;
        }

        .plantoes-hero h2 {
            margin: 0 0 8px;
            font-size: 1.45rem;
            line-height: 1.1;
        }

        .plantoes-hero p {
            margin: 0;
            color: rgba(244, 247, 250, 0.78);
            line-height: 1.6;
        }

        .plantoes-hero-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 18px;
        }

        .plantoes-hero-metrics div {
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .plantoes-hero-metrics strong {
            display: block;
            font-size: 1.4rem;
            line-height: 1;
            margin-bottom: 6px;
        }

        .plantoes-hero-metrics span {
            font-size: 0.78rem;
            color: rgba(244, 247, 250, 0.78);
        }

        .plantoes-sidecard {
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .plantoes-sidecard .tag {
            justify-content: center;
        }

        @media (max-width: 900px) {
            .plantoes-summary {
                grid-template-columns: 1fr;
            }

            .plantoes-hero-metrics {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>

    <div class="plantoes-overview">
    <div class="section-head">
        <div>
            <h1>Plantões</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Leitura somente consulta da base legada, com os plantões externos vinculados ao mês selecionado.
            </p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('escalas.index', $filters) }}">Voltar para escala</a>
            <a class="btn secondary" href="{{ route('escala.alias') }}">Atalho escala</a>
            <a class="btn secondary" href="{{ route('plantoes.alias') }}">Atalho plantões</a>
            <a class="btn secondary" href="{{ route('dashboard') }}">Dashboard</a>
        </div>
    </div>

    <div class="plantoes-summary">
        <section class="plantoes-hero">
            <h2>Painel de plantões externos</h2>
            <p>
                Referência consolidada do legado para acompanhar atribuições, catálogo de plantões e conferência do espelho funcional do Grom.Seg.
            </p>
            <div class="plantoes-hero-metrics">
                <div>
                    <strong>{{ $snapshot['summary']['plantoes_atribuicoes'] }}</strong>
                    <span>Atribuições no período</span>
                </div>
                <div>
                    <strong>{{ $snapshot['summary']['plantoes_catalogo_ativos'] }}</strong>
                    <span>Modelos ativos no catálogo</span>
                </div>
                <div>
                    <strong>{{ $snapshot['summary']['funcionarios_ativos'] }}</strong>
                    <span>Funcionários ativos no legado</span>
                </div>
                <div>
                    <strong>{{ $phpMirrorSummary['ativos'] }}</strong>
                    <span>Ativos no espelho PHP</span>
                </div>
            </div>
        </section>

        <section class="card plantoes-sidecard">
            <h2 style="margin-top: 0;">Base de consulta</h2>
            <p class="muted" style="margin: 0;">
                Mês {{ str_pad((string) $snapshot['month'], 2, '0', STR_PAD_LEFT) }}/{{ $snapshot['year'] }} com versão {{ $snapshot['version'] ?? 'N/D' }}.
            </p>
            <div class="grid">
                <div class="tag good">Fonte: {{ $snapshot['source_name'] ?? 'N/D' }}</div>
                <div class="tag good">Concorrendo à escala: {{ $snapshot['summary']['funcionarios_concorrem'] }}</div>
                <div class="tag {{ $snapshot['summary']['funcionarios_em_afastamento'] > 0 ? 'warn' : 'good' }}">Em afastamento: {{ $snapshot['summary']['funcionarios_em_afastamento'] }}</div>
                <div class="tag good">Dias da escala: {{ $snapshot['summary']['dias_total'] }}</div>
            </div>
        </section>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <form method="GET" action="{{ route('escalas.plantoes') }}" class="actions">
            <div class="field" style="min-width: 140px;">
                <label for="ano">Ano</label>
                <select id="ano" name="ano">
                    @foreach ($snapshot['available_years'] as $year)
                        <option value="{{ $year }}" @selected($filters['ano'] === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="min-width: 180px;">
                <label for="mes">Mês</label>
                <select id="mes" name="mes">
                    @foreach ($snapshot['available_months'] as $month)
                        <option value="{{ $month }}" @selected($filters['mes'] === $month)>{{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }} - {{ \Carbon\Carbon::create()->month($month)->locale('pt_BR')->isoFormat('MMMM') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions" style="align-self: end;">
                <button type="submit">Atualizar</button>
            </div>
        </form>
    </section>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Fonte legada</small>
            <strong>{{ $snapshot['source_name'] ?? 'N/D' }}</strong>
            <span>Arquivo SQLite consultado somente em leitura.</span>
        </article>
        <article class="card">
            <small>Plantoes atribuídos</small>
            <strong>{{ $snapshot['summary']['plantoes_atribuicoes'] }}</strong>
            <span>Leitura direta do vínculo legado.</span>
        </article>
        <article class="card">
            <small>Catalogo ativo</small>
            <strong>{{ $snapshot['summary']['plantoes_catalogo_ativos'] }}</strong>
            <span>Modelos de plantão disponíveis.</span>
        </article>
        <article class="card">
            <small>Funcionários ativos</small>
            <strong>{{ $snapshot['summary']['funcionarios_ativos'] }}</strong>
            <span>Base funcional para as atribuições.</span>
        </article>
        <article class="card">
            <small>Concorrendo à escala</small>
            <strong>{{ $snapshot['summary']['funcionarios_concorrem'] }}</strong>
            <span>Somente os servidores marcados para concorrer.</span>
        </article>
        <article class="card">
            <small>Em afastamento</small>
            <strong>{{ $snapshot['summary']['funcionarios_em_afastamento'] }}</strong>
            <span>Referência informativa do legado.</span>
        </article>
        <article class="card">
            <small>Espelho PHP</small>
            <strong>{{ $phpMirrorSummary['total'] }}</strong>
            <span>{{ $phpMirrorSummary['ativos'] }} ativos, {{ $phpMirrorSummary['concorrem_escala'] }} concorrendo.</span>
        </article>
    </div>

    <div class="grid" style="grid-template-columns: 1.05fr .95fr; margin-bottom: 18px;">
        <section class="card">
            <h2 style="margin-top: 0;">Atribuições do mês</h2>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Funcionário</th>
                        <th>Plantão</th>
                        <th>Unidade</th>
                        <th>Regra</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($snapshot['plantoes'] as $plantao)
                        <tr>
                            <td>{{ $plantao['date_label'] }}</td>
                            <td>
                                <strong>{{ $plantao['funcionario_nome'] }}</strong><br>
                                <span class="muted">{{ $plantao['funcionario_setor'] ?: 'Setor não informado' }}</span>
                            </td>
                            <td>{{ $plantao['plantao_sigla'] ?: $plantao['plantao_nome'] }}</td>
                            <td>{{ $plantao['plantao_unidade'] ?: 'N/A' }}</td>
                            <td>{{ $plantao['plantao_regra'] ?: 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Nenhuma atribuição registrada para o período selecionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Catalogo de plantões</h2>
            <table>
                <thead>
                    <tr>
                        <th>Sigla</th>
                        <th>Nome</th>
                        <th>Unidade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($snapshot['plantao_catalog'] as $plantao)
                        <tr>
                            <td><strong>{{ $plantao['sigla'] ?: 'S/ sigla' }}</strong></td>
                            <td>{{ $plantao['nome'] }}</td>
                            <td>{{ $plantao['unidade'] }}</td>
                            <td><span class="tag {{ $plantao['ativo'] ? 'good' : 'warn' }}">{{ $plantao['ativo'] ? 'Ativo' : 'Inativo' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Nenhum plantão encontrado no catálogo legado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <h2 style="margin-top: 18px;">Base da escala</h2>
            <div class="grid">
                <div class="tag good">Mes: {{ str_pad((string) $snapshot['month'], 2, '0', STR_PAD_LEFT) }}</div>
                <div class="tag good">Ano: {{ $snapshot['year'] }}</div>
                <div class="tag good">Versão: {{ $snapshot['version'] ?? 'N/D' }}</div>
                <div class="tag good">Dias da escala: {{ $snapshot['summary']['dias_total'] }}</div>
            </div>
        </section>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Espelho PHP de referência</h2>
        <p class="muted" style="margin: 0 0 14px;">
            Funcionários já espelhados no PHP para conferência dos plantões e da escala mensal.
        </p>
        <table>
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Cargo / Setor</th>
                    <th>Contato / Docs</th>
                    <th>Datas</th>
                    <th>Escala</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($phpFuncionarios as $funcionario)
                    @php($currentAfastamento = $funcionario->currentAfastamento())
                    <tr>
                        <td>
                            <strong>{{ $funcionario->matricula }}</strong><br>
                            <span class="muted">{{ $funcionario->name }}</span><br>
                            <span class="muted">{{ $funcionario->short_name ?: 'Sem nome simplificado' }}</span>
                        </td>
                        <td>
                            {{ $funcionario->cargo?->name ?: 'Sem cargo' }}<br>
                            <span class="muted">{{ $funcionario->sector ?: 'Sem setor' }}</span>
                        </td>
                        <td>
                            <strong>{{ $funcionario->phone ?: 'Sem telefone' }}</strong><br>
                            <span class="muted">RG: {{ $funcionario->rg ?: 'N/D' }}</span><br>
                            <span class="muted">CPF: {{ $funcionario->cpf ?: 'N/D' }}</span>
                        </td>
                        <td>
                            <strong>Nasc.: {{ $funcionario->birth_date?->format('d/m/Y') ?: 'N/D' }}</strong><br>
                            <span class="muted">Designação: {{ $funcionario->designation_date?->format('d/m/Y') ?: 'N/D' }}</span><br>
                            <span class="muted">Remoção: {{ $funcionario->removal_date?->format('d/m/Y') ?: 'N/D' }}</span>
                        </td>
                        <td>
                            <span class="tag {{ $funcionario->concorre_escala ? 'good' : 'warn' }}">
                                {{ $funcionario->concorre_escala ? 'Concorre' : 'Não concorre' }}
                            </span>
                        </td>
                        <td>
                            @if ($currentAfastamento)
                                <span class="tag warn">Em afastamento</span><br>
                                <span class="muted">{{ $currentAfastamento->reason }}</span>
                            @else
                                <span class="tag {{ $funcionario->is_active ? 'good' : 'warn' }}">{{ $funcionario->is_active ? 'Ativo' : 'Inativo' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Nenhum funcionário espelhado no PHP.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @if (! empty($snapshot['warnings']))
        <section class="card">
            <h2 style="margin-top: 0;">Avisos</h2>
            <div class="grid">
                @foreach ($snapshot['warnings'] as $warning)
                    <div class="tag warn">{{ $warning }}</div>
                @endforeach
            </div>
        </section>
    @endif
    </div>
@endsection

