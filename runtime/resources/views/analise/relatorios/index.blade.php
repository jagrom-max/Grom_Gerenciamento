@extends('layouts.app')

@section('title', 'Relatórios de Análise | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Relatórios de Análise de Dados</h1>
            <p class="muted" style="margin:6px 0 0;">Relatórios analíticos impressos gerados a partir da base consolidada de BOs.</p>
        </div>
    </div>

    {{-- KPIs gerais --}}
    <div class="cards" style="margin-bottom:20px;">
        <article class="card" style="text-align:center;">
            <small>Total de BOs</small>
            <strong style="font-size:1.8rem;">{{ $kpis['total'] }}</strong>
        </article>
        <article class="card" style="text-align:center;">
            <small>Flagrantes</small>
            <strong style="font-size:1.8rem; color:#c0392b;">{{ $kpis['flagrantes'] }}</strong>
        </article>
        <article class="card" style="text-align:center;">
            <small>Com IP instaurado</small>
            <strong style="font-size:1.8rem;">{{ $kpis['com_ip'] }}</strong>
        </article>
        <article class="card" style="text-align:center;">
            <small>Com MPU</small>
            <strong style="font-size:1.8rem; color:#e67e22;">{{ $kpis['com_mpu'] }}</strong>
        </article>
    </div>

    {{-- Cards de relatórios --}}
    <section class="card">
        <h2 style="margin-top:0;">Relatórios disponíveis</h2>
        <div class="cards">

            <article class="card" style="cursor:pointer;" onclick="window.open('{{ route('analise.relatorios.dados', 'flagrantes') }}', '_blank')">
                <small>Relatório</small>
                <strong style="font-size:1.1rem; color:#991b1b;">Flagrantes</strong>
                <span>Distribuição por período, área, natureza e tipo de lavrado.</span>
                <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                    <a class="btn" style="font-size:.8rem; padding:5px 10px;"
                       href="{{ route('analise.relatorios.dados', 'flagrantes') }}" target="_blank">
                        Visualizar
                    </a>
                </div>
            </article>

            <article class="card" style="cursor:pointer;" onclick="window.open('{{ route('analise.relatorios.dados', 'naturezas') }}', '_blank')">
                <small>Relatório</small>
                <strong style="font-size:1.1rem;">Total por Natureza</strong>
                <span>Ranking de naturezas criminais com tentado/consumado e taxa de flagrante.</span>
                <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                    <a class="btn" style="font-size:.8rem; padding:5px 10px;"
                       href="{{ route('analise.relatorios.dados', 'naturezas') }}" target="_blank">
                        Visualizar
                    </a>
                </div>
            </article>

            <article class="card" style="cursor:pointer;" onclick="window.open('{{ route('analise.relatorios.dados', 'areas') }}', '_blank')">
                <small>Relatório</small>
                <strong style="font-size:1.1rem;">Ocorrências por Área</strong>
                <span>Distribuição de BOs por área do fato com IPs, MPUs e flagrantes.</span>
                <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                    <a class="btn" style="font-size:.8rem; padding:5px 10px;"
                       href="{{ route('analise.relatorios.dados', 'areas') }}" target="_blank">
                        Visualizar
                    </a>
                </div>
            </article>

            <article class="card" style="cursor:pointer;" onclick="window.open('{{ route('analise.relatorios.dados', 'ips-cartorio') }}', '_blank')">
                <small>Relatório</small>
                <strong style="font-size:1.1rem;">IPs por Cartório</strong>
                <span>Volume de Inquéritos Policiais agrupados por cartório de condução.</span>
                <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                    <a class="btn" style="font-size:.8rem; padding:5px 10px;"
                       href="{{ route('analise.relatorios.dados', 'ips-cartorio') }}" target="_blank">
                        Visualizar
                    </a>
                </div>
            </article>

            <article class="card" style="cursor:pointer;" onclick="window.open('{{ route('analise.relatorios.dados', 'ips-totais') }}', '_blank')">
                <small>Relatório</small>
                <strong style="font-size:1.1rem;">IPs Totais</strong>
                <span>Lista completa de Inquéritos Policiais com nº IP, CNJ, MPU e cartório.</span>
                <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                    <a class="btn" style="font-size:.8rem; padding:5px 10px;"
                       href="{{ route('analise.relatorios.dados', 'ips-totais') }}" target="_blank">
                        Visualizar
                    </a>
                </div>
            </article>

        </div>
    </section>
@endsection
