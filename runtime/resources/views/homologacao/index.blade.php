@extends('layouts.app')

@section('title', 'Evolucao e Aprovacao | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Painel de evolucao e aprovacao</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Esta e a porta de entrada para revisar a evolucao do projeto, abrir cada modulo e aprovar o que ja foi entregue sem procurar nada em `runtime/tests/`.
            </p>
        </div>
        <div class="actions">
            <!-- Link removido: acesso de teste legacy -->
            <a class="btn secondary" href="{{ route('evolucao') }}">Abrir evolucao</a>
            @auth
                <a class="btn secondary" href="{{ route('dashboard') }}">Abrir dashboard</a>
                <a class="btn secondary" href="{{ route('evolucao') }}">Recarregar painel</a>
            @else
                <a class="btn secondary" href="{{ route('login') }}">Ir para login</a>
            @endauth
        </div>
    </div>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Usuarios</small>
            <strong>{{ $metrics['usuarios'] }}</strong>
            <span>Base de acesso e perfis carregada.</span>
        </article>
        <article class="card">
            <small>Cartorios</small>
            <strong>{{ $metrics['cartorios'] }}</strong>
            <span>Escopo atual do piloto de produtividade.</span>
        </article>
        <article class="card">
            <small>Flagrantes ativos</small>
            <strong>{{ $metrics['flagrantes'] }}</strong>
            <span>Fila consolidada para conferencia e fechamento.</span>
        </article>
        <article class="card">
            <small>Lotes importados</small>
            <strong>{{ $metrics['lotes'] }}</strong>
            <span>Historico de consolidacoes ja processadas.</span>
        </article>
        <article class="card">
            <small>RH ativos</small>
            <strong>{{ $metrics['rh_funcionarios'] }}</strong>
            <span>Funcionarios ja preparados para evolucao do modulo.</span>
        </article>
        <article class="card">
            <small>Afastamentos</small>
            <strong>{{ $metrics['rh_afastamentos'] }}</strong>
            <span>Ocorrencias ativas para conferencia gerencial.</span>
        </article>
        <article class="card">
            <small>Delegados externos</small>
            <strong>{{ $metrics['rh_delegados_externos'] }}</strong>
            <span>Delegacoes externas ativas no ambiente web.</span>
        </article>
        <article class="card">
            <small>Escalas do mes</small>
            <strong>{{ $metrics['escalas_dias'] }}</strong>
            <span>Leitura legada da escala mensal corrente.</span>
        </article>
        <article class="card">
            <small>Plantões do mes</small>
            <strong>{{ $metrics['escalas_plantoes'] }}</strong>
            <span>Vinculos carregados da base antiga.</span>
        </article>
        <article class="card">
            <small>Feriados do calendario</small>
            <strong>{{ $metrics['calendarios_feriados'] }}</strong>
            <span>Cadastro ativo no RH para consulta mensal.</span>
        </article>
        <article class="card">
            <small>Backup local</small>
            <strong>{{ $metrics['backup_sqlite_files'] }}</strong>
            <span>Arquivos de base local visiveis para conferencia.</span>
        </article>
    </div>

    <div class="grid" style="grid-template-columns: 1.05fr .95fr; margin-bottom: 18px;">
        <section class="card">
            <h2 style="margin-top: 0;">Panorama de aprovacao</h2>
            <div class="grid">
                @foreach ($modules as $module)
                    <article style="border: 1px solid var(--line); border-radius: 16px; padding: 16px; background: #fff;">
                        <div class="actions" style="justify-content: space-between; margin-bottom: 8px;">
                            <strong>{{ $module['title'] }}</strong>
                            <span class="tag good">{{ $module['status'] }}</span>
                        </div>
                        <p class="muted" style="margin: 0; line-height: 1.6;">{{ $module['description'] }}</p>
                        <div class="actions" style="margin-top: 12px;">
                            <a class="btn secondary" href="{{ $module['link'] }}">Abrir modulo</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">O que avaliar agora</h2>
            <div class="grid">
                <div class="tag good">Identidade visual unica e consistente</div>
                <div class="tag good">A4 padronizado para relatorios</div>
                <div class="tag good">Escopo por cartorio e unidade</div>
                <div class="tag good">Agenda de afastamentos consultada</div>
                <div class="tag good">Backup local observado</div>
                <div class="tag good">Fila auditavel de importacao</div>
                <div class="tag good">RBAC com perfis e permissoes</div>
                <div class="tag good">Base pronta para proximas migracoes</div>
                <div class="tag good">RH com feriados e delegados externos</div>
                <div class="tag good">Escalas e plantões legados</div>
            </div>

            <div style="margin-top: 18px;">
                <h3 style="margin: 0 0 8px;">Como aprovar sem perder tempo</h3>
                <p class="muted" style="margin: 0; line-height: 1.6;">
                    Abra cada modulo pelos atalhos acima, valide os fluxos principais e me diga apenas o que deve mudar.
                    Eu mantenho o ritmo do pacote seguinte sem transformar a revisão em uma sequência de perguntas.
                </p>
            </div>
        </section>
    </div>

    <section class="card">
        <h2 style="margin-top: 0;">Acesso rapido para revisao</h2>
        <p class="muted" style="margin-top: 0;">
            Se o objetivo for aprovar visualmente, estes atalhos mostram o estado atual da construcao sem ir para os testes automatizados.
        </p>
        <div class="actions">
            <!-- Link removido: credenciais de teste legacy -->
            <a class="btn secondary" href="{{ route('homologacao') }}">Versao homologacao</a>
            @foreach ($reviewLinks as $link)
                <a class="btn secondary" href="{{ $link['route'] }}">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </section>
@endsection
