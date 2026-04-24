@extends('layouts.app')

@section('title', 'Prova da Escala Mensal | Grom.Seg')

@section('content')
    <style>
        .proof-wrap {
            display: grid;
            gap: 18px;
        }

        .proof-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .proof-head h1 {
            margin: 0;
            font-size: 28px;
        }

        .proof-head p {
            margin: 6px 0 0;
            color: var(--ink-soft);
        }

        .proof-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .proof-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }

        .proof-check {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            justify-content: space-between;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 14px;
            background: #fff;
        }

        .proof-check.good {
            border-color: #b9e2c7;
            background: #f5fbf7;
        }

        .proof-check strong {
            display: block;
            margin-bottom: 4px;
        }

        .proof-check small {
            display: block;
            color: var(--ink-soft);
            line-height: 1.45;
        }

        .proof-reference code {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 10px;
            background: #f4f7fb;
            border: 1px solid var(--line);
            font-size: 13px;
        }

        .proof-frame {
            width: 100%;
            min-height: 1180px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fff;
        }
    </style>

    <div class="page-card proof-wrap">
        <div class="proof-head">
            <div>
                <h1>Prova da Escala Mensal</h1>
                <p>
                    {{ ucfirst(\Carbon\Carbon::create()->month($filters['mes'])->locale('pt_BR')->isoFormat('MMMM')) }}/{{ $filters['ano'] }}
                    @if ($escalaVersao)
                        · versão {{ $escalaVersao->versao }}
                        @if ($escalaVersao->eh_definitiva)
                            · definitiva
                        @else
                            · provisória
                        @endif
                    @endif
                </p>
            </div>

            <div class="proof-actions no-print">
                <a class="btn secondary" href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer">Abrir pré-visualização</a>
                <a class="btn secondary" href="{{ route('escalas.index', $filters) }}">Voltar para escala</a>
            </div>
        </div>

        <div class="alert good">
            Esta página foi criada para você conferir o resultado dentro do sistema, sem depender de PDF ou PNG solto.
            O painel abaixo carrega o relatório real, com o mesmo timbrado consolidado e sem autoimpressão.
        </div>

        <div class="proof-summary">
            <div class="card">
                <small>Contexto</small>
                <strong>Relatório pronto</strong>
                <div style="margin-top: 8px; color: var(--ink-soft); line-height: 1.6;">
                    <div>Fonte: {{ $phpDias->isNotEmpty() ? 'base PHP' : 'base legada' }}</div>
                    <div>Dias carregados: {{ $phpDias->isNotEmpty() ? $phpDias->count() : collect($snapshot['scale_rows'] ?? [])->count() }}</div>
                    <div>Rodapé: Cartório Central - Gerenciamento</div>
                </div>
            </div>

            <div class="card">
                <small>Checagens</small>
                <div style="display:grid; gap:10px; margin-top:8px;">
                    @foreach ($proofChecks as $check)
                        <div class="proof-check good">
                            <div>
                                <strong>{{ $check['label'] }}</strong>
                                <small>{{ $check['detail'] }}</small>
                            </div>
                            <div style="font-weight: 800; white-space: nowrap;">{{ $check['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if (! empty($referenceRow))
            <div class="card proof-reference">
                <small>Linha de referência</small>
                <div style="margin-top: 8px; display:grid; gap: 8px; color: var(--ink); line-height: 1.6;">
                    <div><strong>{{ $referenceRow['date'] }}</strong> · {{ $referenceRow['day'] }}</div>
                    <div><code>{{ $referenceRow['plantao_text'] }}</code></div>
                    <div style="color: var(--ink-soft);">Essa é a linha usada para validar a acomodação dos plantões externos sem esmagar a diagramação.</div>
                </div>
            </div>
        @endif

        <div class="card">
            <small>Pré-visualização real</small>
            <div style="margin-top: 10px;">
                <iframe class="proof-frame" src="{{ $previewUrl }}" title="Pré-visualização da escala mensal" loading="lazy"></iframe>
            </div>
        </div>
    </div>
@endsection

