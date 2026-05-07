@extends('layouts.app')

@section('title', 'Plantões | Grom.Seg')

@section('content')
    <style>
        .plantoes-clean {
            display: grid;
            gap: 12px;
        }

        .plantoes-clean .card {
            border-radius: 8px;
            padding: 14px;
        }

        .plantoes-clean h1 {
            font-size: 1.35rem;
            line-height: 1.2;
        }

        .plantoes-clean h2 {
            margin: 0 0 10px;
            font-size: 0.98rem;
            line-height: 1.25;
        }

        .plantoes-clean table {
            font-size: 0.86rem;
        }

        .plantoes-clean th,
        .plantoes-clean td {
            padding: 8px 10px;
            vertical-align: middle;
        }

        .plantoes-clean .plantao-funcionario {
            font-size: 0.88rem;
            font-weight: 600;
            line-height: 1.2;
        }
    </style>

    <div class="plantoes-clean">
    <div class="section-head">
        <div>
            <h1>Plantões</h1>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('escalas.index', $filters) }}">Voltar para escala</a>
        </div>
    </div>

    @if (session('status-success'))
        <div class="alert success">{{ session('status-success') }}</div>
    @elseif (session('status-error'))
        <div class="alert danger">{{ session('status-error') }}</div>
    @elseif (session('status-warning'))
        <div class="alert warn">{{ session('status-warning') }}</div>
    @endif

    <section class="card">
        <form method="GET" action="{{ route('escalas.plantoes') }}" class="actions">
            <div class="field" style="min-width: 140px;">
                <label for="ano">Ano</label>
                <select id="ano" name="ano">
                    @foreach ($anosPhp as $year)
                        <option value="{{ $year }}" @selected($filters['ano'] === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field" style="min-width: 180px;">
                <label for="mes">Mês</label>
                <select id="mes" name="mes">
                    @foreach (($snapshot['available_months'] ?? range(1, 12)) as $month)
                        <option value="{{ $month }}" @selected($filters['mes'] === $month)>
                            {{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }} - {{ \Carbon\Carbon::create()->month($month)->locale('pt_BR')->isoFormat('MMMM') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="actions" style="align-self: end;">
                <button type="submit">Pesquisar</button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Atribuições do mês</h2>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Funcionário</th>
                    <th>Plantão</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($snapshot['plantoes'] as $plantao)
                    <tr>
                        <td>{{ $plantao['date_label'] ?? '—' }}</td>
                        <td><span class="plantao-funcionario">{{ $plantao['funcionario_nome'] ?? '—' }}</span></td>
                        <td>{{ $plantao['plantao_sigla'] ?? ($plantao['plantao_nome'] ?? '—') }}</td>
                        <td style="white-space:nowrap;">
                            @if (!empty($plantao['id']))
                                <form method="POST" action="{{ route('escalas.plantoes-funcionarios.destroy', $plantao['id']) }}" style="display:inline;" onsubmit="return confirm('Remover atribuição?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm danger">Remover</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center; color:#b00; font-weight:600;">Nenhum plantão externo atribuído para o mês selecionado.<br>Utilize o botão <b>+ Atribuir plantão</b> para registrar antes de gerar a escala definitiva.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
    </div>
@endsection
