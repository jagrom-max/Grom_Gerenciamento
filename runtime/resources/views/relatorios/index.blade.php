@extends('layouts.app')

@section('title', 'Relatorios | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Central de relatorios</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Ponto unico para a camada transversal de saida institucional, com base visual preparada para a evolucao do PDF timbrado.
            </p>
        </div>
    </div>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Cartorios ativos</small>
            <strong>{{ $metrics['cartorios_ativos'] }}</strong>
            <span>Universo disponivel para consolidacao e fechamento.</span>
        </article>
        <article class="card">
            <small>Flagrantes no mes</small>
            <strong>{{ $metrics['flagrantes_mes'] }}</strong>
            <span>Base corrente do piloto de produtividade.</span>
        </article>
        <article class="card">
            <small>Funcionarios RH</small>
            <strong>{{ $metrics['funcionarios_rh'] }}</strong>
            <span>Espelho administrativo disponivel para confronto.</span>
        </article>
        <article class="card">
            <small>Lotes importados</small>
            <strong>{{ $metrics['lotes_importados'] }}</strong>
            <span>Entradas auditadas pela fila web.</span>
        </article>
        <article class="card">
            <small>Eventos de auditoria 30d</small>
            <strong>{{ $metrics['eventos_auditoria_30d'] }}</strong>
            <span>Trilha de acesso e operacao recente.</span>
        </article>
    </div>

    <section class="card" style="margin-bottom: 18px;">
        <h2 style="margin-top: 0;">Templates planejados</h2>
        <table>
            <thead>
                <tr>
                    <th>Relatorio</th>
                    <th>Status</th>
                    <th>Escopo</th>
                    <th>Saida esperada</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($templates as $template)
                    <tr>
                        <td><strong>{{ $template['name'] }}</strong></td>
                        <td><span class="tag good">{{ $template['status'] }}</span></td>
                        <td>{{ $template['scope'] }}</td>
                            <td>
                                {{ $template['output'] }}
                                @if (! empty($template['route']) || ! empty($template['pdf_route']))
                                    <div class="actions" style="margin-top: 8px;">
                                        @if (! empty($template['route']))
                                            <a class="btn secondary" href="{{ $template['route'] }}">Abrir</a>
                                        @endif
                                        @if (! empty($template['pdf_route']))
                                            <a class="btn secondary" href="{{ $template['pdf_route'] }}">PDF</a>
                                        @endif
                                    </div>
                                @endif
                            </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <div class="grid" style="grid-template-columns: 1.15fr .85fr;">
        <section class="card">
            <h2 style="margin-top: 0;">Linha de evolucao</h2>
            <div class="grid">
                <div class="tag good">Base visual padronizada</div>
                <div class="tag good">Timbrado institucional</div>
                <div class="tag good">Historico de emissao</div>
                <div class="tag good">Filtros por periodo e unidade</div>
                <div class="tag good">Exportacao PDF e XLSX</div>
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Proxima etapa</h2>
            <p class="muted" style="margin: 0;">
                Esta central foi aberta para dar suporte ao restante da migracao. A evolucao natural agora e ligar os templates
                ao mecanismo de emissao, mantendo auditoria e padronizacao visual desde o inicio.
            </p>
        </section>
    </div>
@endsection
