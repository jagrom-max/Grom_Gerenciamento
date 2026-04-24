@extends('layouts.app')

@section('title', 'Dashboard | Grom.Seg')

@section('content')
    @php
        $user = auth()->user();
        $currentDateLabel = now()->translatedFormat('l, d \d\e F \d\e Y');

        $executiveCards = [
            [
                'eyebrow' => 'Governanca',
                'value' => $stats['usuarios'],
                'label' => 'Usuarios ativos na base de acesso central.',
                'tone' => 'neutral',
            ],
            [
                'eyebrow' => 'Permissoes',
                'value' => $stats['permissoes'],
                'label' => 'Permissoes efetivamente carregadas para o seu perfil.',
                'tone' => 'neutral',
            ],
            [
                'eyebrow' => 'Cartorios',
                'value' => $stats['cartorios'],
                'label' => 'Unidades operacionais presentes no ecossistema web.',
                'tone' => 'neutral',
            ],
        ];

        if ($canViewRh && $rhHoje) {
            $executiveCards[] = [
                'eyebrow' => 'Efetivo',
                'value' => $rhHoje['total_ativos'],
                'label' => $rhHoje['afastados_hoje'] > 0
                    ? $rhHoje['afastados_hoje'].' afastados no momento.'
                    : 'Efetivo completo em atividade hoje.',
                'tone' => $rhHoje['afastados_hoje'] > 0 ? 'alert' : 'good',
            ];
        } elseif ($canViewEscalas) {
            $executiveCards[] = [
                'eyebrow' => 'Escalas',
                'value' => $stats['escalas_plantoes'],
                'label' => 'Atribuicoes de plantoes consolidadas no mes corrente.',
                'tone' => 'neutral',
            ];
        }

        if ($produtividadeStats) {
            $executiveCards[] = [
                'eyebrow' => 'Fila operacional',
                'value' => $produtividadeStats['fila_pendente'],
                'label' => 'Pendencias aguardando conferencia manual.',
                'tone' => $produtividadeStats['fila_pendente'] > 0 ? 'alert' : 'good',
            ];
        }

        $moduleCards = [];

        if ($user->hasPermission('operacional.view') || $user->hasPermission('produtividade.cartorios.view')) {
            $operationalLinks = [];

            if ($user->hasPermission('operacional.view')) {
                $operationalLinks[] = ['label' => 'Painel operacional', 'url' => route('operacional.index')];
            }
            if ($user->hasPermission('operacional.mandados.view')) {
                $operationalLinks[] = ['label' => 'Mandados de Prisao', 'url' => route('operacional.mandados.index')];
            }
            if ($user->hasPermission('operacional.objetos.view')) {
                $operationalLinks[] = ['label' => 'Objetos apreendidos', 'url' => route('operacional.objetos.index')];
            }
            if ($user->hasPermission('produtividade.cartorios.view')) {
                $operationalLinks[] = ['label' => 'Cartorios', 'url' => route('produtividade.cartorios.index')];
            }
            if ($user->hasPermission('produtividade.boletins.view')) {
                $operationalLinks[] = ['label' => 'Boletins / Upload Consolidado', 'url' => route('produtividade.boletins.index')];
            }
            if ($user->hasPermission('produtividade.flagrantes.view')) {
                $operationalLinks[] = ['label' => 'Fila de Flagrantes', 'url' => route('produtividade.flagrantes.index')];
            }
            if ($user->hasPermission('produtividade.stats.view')) {
                $operationalLinks[] = ['label' => 'Estatisticas', 'url' => route('produtividade.stats.index')];
            }

            $moduleCards[] = [
                'eyebrow' => 'Nucleo operacional',
                'title' => 'Operacional e produtividade',
                'description' => 'Coordena cartorios, flagrantes, mandados e indicadores taticos da atividade policial.',
                'accent' => 'blue',
                'kpis' => [
                    ['label' => 'Cartorios', 'value' => $stats['cartorios']],
                    ['label' => 'Fila', 'value' => $produtividadeStats['fila_pendente'] ?? 0],
                ],
                'primary' => $operationalLinks[0] ?? null,
                'links' => $operationalLinks,
            ];
        }

        if ($canViewRh) {
            $moduleCards[] = [
                'eyebrow' => 'Gestao institucional',
                'title' => 'Recursos humanos',
                'description' => 'Concentra efetivo, afastamentos, confrontos, composicao de cartorios e leitura gerencial de pessoal.',
                'accent' => 'gold',
                'kpis' => [
                    ['label' => 'Ativos', 'value' => $rhHoje['total_ativos'] ?? 0],
                    ['label' => 'Afastados hoje', 'value' => $rhHoje['afastados_hoje'] ?? 0],
                ],
                'primary' => ['label' => 'Abrir RH / Administracao', 'url' => route('rh.index')],
                'links' => [
                    ['label' => 'Funcionarios', 'url' => route('funcionarios.index')],
                    ['label' => 'Confronto de afastamentos', 'url' => route('rh.confronto')],
                    ['label' => 'Composicao dos cartorios', 'url' => route('rh.composicao')],
                    ['label' => 'Estatisticas RH', 'url' => route('rh.stats')],
                ],
            ];
        }

        if ($canViewEscalas) {
            $scaleLinks = [
                ['label' => 'Escala Mensal', 'url' => route('escalas.index')],
                ['label' => 'Plantoes', 'url' => route('escalas.plantoes')],
            ];

            if ($user->hasPermission('calendarios.view')) {
                $scaleLinks[] = ['label' => 'Agenda de afastamentos', 'url' => route('calendarios.index')];
            }

            $moduleCards[] = [
                'eyebrow' => 'Coordenacao de servico',
                'title' => 'Escalas e agendas',
                'description' => 'Orquestra escalas mensais, plantoes, agenda de afastamentos e leitura do legado operacional.',
                'accent' => 'emerald',
                'kpis' => [
                    ['label' => 'Dias no mes', 'value' => $stats['escalas_dias']],
                    ['label' => 'Plantoes', 'value' => $stats['escalas_plantoes']],
                ],
                'primary' => $scaleLinks[0],
                'links' => $scaleLinks,
            ];
        }

        if ($user->hasPermission('relatorios.emit') || $user->hasPermission('analise.view')) {
            $reportLinks = [];

            if ($user->hasPermission('relatorios.emit')) {
                $reportLinks[] = ['label' => 'Central de relatorios', 'url' => route('relatorios.index')];
                $reportLinks[] = ['label' => 'Produtividade A4', 'url' => route('relatorios.produtividade.a4')];
                $reportLinks[] = ['label' => 'Acompanhamento operacional', 'url' => route('relatorios.operacional.integrado')];
            }
            if ($user->hasPermission('analise.view')) {
                $reportLinks[] = ['label' => 'Analise de dados', 'url' => route('analise.index')];
            }

            $moduleCards[] = [
                'eyebrow' => 'Inteligencia e saida',
                'title' => 'Relatorios e analise',
                'description' => 'Expande a visao institucional com relatorios formais, acompanhamento integrado e leitura analitica.',
                'accent' => 'slate',
                'kpis' => [
                    ['label' => 'Lotes recentes', 'value' => $latestBatchesPreview->count()],
                    ['label' => 'Pendencias', 'value' => $pendingItemsPreview->count()],
                ],
                'primary' => $reportLinks[0] ?? null,
                'links' => $reportLinks,
            ];
        }

        $governanceLinks = [];
        if ($user->hasPermission('backup.view')) {
            $governanceLinks[] = ['label' => 'Backup', 'url' => route('backup.index')];
        }
        if ($user->hasPermission('auditoria.view')) {
            $governanceLinks[] = ['label' => 'Auditoria', 'url' => route('auditoria.index')];
        }
        if ($user->hasPermission('access.users.view')) {
            $governanceLinks[] = ['label' => 'Usuarios', 'url' => route('access.users.index')];
        }
        if ($user->hasPermission('access.roles.view')) {
            $governanceLinks[] = ['label' => 'Perfis de acesso', 'url' => route('access.roles.index')];
        }
        $governanceLinks[] = ['label' => 'Minha senha', 'url' => route('password.edit')];
        if (app()->environment(['local', 'testing'])) {
            $governanceLinks[] = ['label' => 'Evolucao do sistema', 'url' => route('evolucao')];
        }

        $moduleCards[] = [
            'eyebrow' => 'Controle institucional',
            'title' => 'Governanca e seguranca',
            'description' => 'Reune auditoria, gestao de usuarios, perfis de acesso, continuidade e administracao do ambiente.',
            'accent' => 'ruby',
            'kpis' => [
                ['label' => 'Usuarios', 'value' => $stats['usuarios']],
                ['label' => 'Permissoes', 'value' => $stats['permissoes']],
            ],
            'primary' => $governanceLinks[0] ?? null,
            'links' => $governanceLinks,
        ];

        $capabilityTags = [
            'RBAC com roles e permissions',
            'Auditoria de login e logout',
            'Painel operacional',
            'Mandados de Prisao',
            'Objetos apreendidos',
            'Cartorios no modulo web',
            'Flagrantes com fila controlada',
            'Sincronizacao com legado',
            'Funcionarios e RH',
            'Confronto mensal',
            'Composicao por cartorio',
            'Escalas e plantoes',
            'Agenda de afastamentos',
            'Analise de dados',
            'Central de relatorios',
            'Backup observado',
            'Auditoria consultavel',
        ];
    @endphp

    <style>
        .dashboard-shell {
            display: grid;
            gap: 24px;
        }

        .dashboard-hero {
            position: relative;
            overflow: hidden;
            border-radius: 30px;
            padding: 34px;
            background:
                linear-gradient(135deg, rgba(11, 29, 52, 0.98) 0%, rgba(14, 44, 78, 0.96) 54%, rgba(10, 23, 40, 0.96) 100%),
                url('{{ asset('assets/marca_dagua.png') }}') no-repeat right 24px center / 260px auto;
            color: #f3f7fb;
            box-shadow: 0 28px 64px rgba(24, 41, 61, 0.18);
        }

        .dashboard-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(212, 175, 55, 0.12), transparent 32%);
            pointer-events: none;
        }

        .hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(280px, 0.95fr);
            gap: 26px;
            align-items: stretch;
        }

        .hero-copy {
            display: grid;
            gap: 18px;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: fit-content;
            min-height: 34px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid rgba(212, 175, 55, 0.28);
            background: rgba(212, 175, 55, 0.1);
            color: #f2d888;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .hero-copy h1 {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 0.98;
            letter-spacing: -0.04em;
            max-width: 12ch;
        }

        .hero-copy p {
            margin: 0;
            max-width: 62ch;
            color: rgba(243, 247, 251, 0.76);
            font-size: 1rem;
            line-height: 1.65;
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .hero-chip {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            padding: 0 14px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: rgba(243, 247, 251, 0.88);
            font-size: 0.9rem;
        }

        .hero-aside {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 14px;
            align-content: start;
            padding: 22px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.09);
            backdrop-filter: blur(10px);
        }

        .hero-aside small {
            color: rgba(212, 175, 55, 0.86);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 700;
        }

        .hero-aside strong {
            font-size: 1.3rem;
            line-height: 1.2;
        }

        .hero-aside p {
            margin: 0;
            color: rgba(243, 247, 251, 0.72);
            line-height: 1.6;
        }

        .hero-aside-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .hero-stat {
            padding: 14px;
            border-radius: 18px;
            background: rgba(7, 18, 30, 0.32);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .hero-stat span {
            display: block;
            color: rgba(243, 247, 251, 0.66);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }

        .hero-stat strong {
            font-size: 1.5rem;
        }

        .dashboard-section-head {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 16px;
        }

        .dashboard-section-head h2 {
            margin: 0;
            font-size: 1.3rem;
        }

        .dashboard-section-head p {
            margin: 6px 0 0;
            color: var(--ink-soft);
            max-width: 56ch;
            line-height: 1.55;
        }

        .executive-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }

        .executive-card {
            position: relative;
            overflow: hidden;
            border-radius: 22px;
            padding: 20px 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f7fafc 100%);
            border: 1px solid rgba(216, 222, 231, 0.9);
            box-shadow: 0 16px 34px rgba(44, 62, 80, 0.06);
        }

        .executive-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #2c3e50;
        }

        .executive-card.good::before { background: #1f8f5f; }
        .executive-card.alert::before { background: #b04a26; }

        .executive-card small {
            display: block;
            color: var(--ink-soft);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .executive-card strong {
            display: block;
            font-size: 2.2rem;
            line-height: 1;
            margin-bottom: 10px;
        }

        .executive-card p {
            margin: 0;
            color: var(--ink-soft);
            line-height: 1.55;
            font-size: 0.94rem;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
        }

        .module-card {
            display: grid;
            gap: 18px;
            min-height: 320px;
            padding: 24px;
            border-radius: 26px;
            border: 1px solid rgba(216, 222, 231, 0.92);
            background: linear-gradient(180deg, #ffffff 0%, #f8fbfd 100%);
            box-shadow: 0 20px 42px rgba(44, 62, 80, 0.07);
        }

        .module-card.blue { box-shadow: 0 20px 42px rgba(23, 74, 132, 0.12); }
        .module-card.gold { box-shadow: 0 20px 42px rgba(184, 141, 29, 0.13); }
        .module-card.emerald { box-shadow: 0 20px 42px rgba(33, 117, 96, 0.12); }
        .module-card.slate { box-shadow: 0 20px 42px rgba(44, 62, 80, 0.1); }
        .module-card.ruby { box-shadow: 0 20px 42px rgba(128, 52, 52, 0.12); }

        .module-head {
            display: grid;
            gap: 10px;
        }

        .module-head small {
            color: var(--ink-soft);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 700;
        }

        .module-head h3 {
            margin: 0;
            font-size: 1.4rem;
            line-height: 1.15;
        }

        .module-head p {
            margin: 0;
            color: var(--ink-soft);
            line-height: 1.65;
        }

        .module-kpis {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .module-kpi {
            padding: 14px;
            border-radius: 18px;
            background: #f4f7fb;
            border: 1px solid rgba(216, 222, 231, 0.84);
        }

        .module-kpi span {
            display: block;
            color: var(--ink-soft);
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }

        .module-kpi strong {
            font-size: 1.45rem;
            line-height: 1;
        }

        .module-actions {
            display: grid;
            gap: 10px;
            align-content: end;
        }

        .module-primary {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            border-radius: 14px;
            background: #132a43;
            color: #ffffff;
            font-weight: 700;
            padding: 0 18px;
        }

        .module-primary:hover {
            background: #1b395b;
        }

        .module-links {
            display: grid;
            gap: 8px;
        }

        .module-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            min-height: 44px;
            padding: 0 14px;
            border-radius: 14px;
            border: 1px solid rgba(216, 222, 231, 0.92);
            background: #ffffff;
            font-weight: 600;
        }

        .module-link::after {
            content: '›';
            color: var(--ink-soft);
            font-size: 1.15rem;
        }

        .module-link:hover {
            background: #eef3f8;
        }

        .insight-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(300px, 0.85fr);
            gap: 18px;
        }

        .dashboard-panel {
            padding: 24px;
            border-radius: 26px;
            border: 1px solid rgba(216, 222, 231, 0.92);
            background: linear-gradient(180deg, rgba(255,255,255,0.96) 0%, rgba(248,251,253,0.96) 100%);
            box-shadow: 0 18px 40px rgba(44, 62, 80, 0.06);
        }

        .dashboard-panel h2 {
            margin: 0 0 8px;
            font-size: 1.15rem;
        }

        .dashboard-panel > p {
            margin: 0 0 16px;
            color: var(--ink-soft);
            line-height: 1.6;
        }

        .status-stack,
        .signal-stack {
            display: grid;
            gap: 10px;
        }

        .status-row {
            padding: 14px 16px;
            border-radius: 16px;
            background: #f6f9fc;
            border: 1px solid rgba(216, 222, 231, 0.84);
        }

        .status-row strong {
            display: block;
            margin-bottom: 4px;
        }

        .status-row span {
            color: var(--ink-soft);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .signal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .signal-card {
            padding: 14px;
            border-radius: 18px;
            background: #f4f7fb;
            border: 1px solid rgba(216, 222, 231, 0.84);
        }

        .signal-card small {
            display: block;
            color: var(--ink-soft);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .signal-card strong {
            font-size: 1.65rem;
            line-height: 1;
        }

        .capability-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .capability-pill {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            padding: 0 12px;
            border-radius: 999px;
            background: #edf3f9;
            border: 1px solid rgba(216, 222, 231, 0.88);
            color: #28415c;
            font-size: 0.84rem;
            font-weight: 700;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .details-stack {
            display: grid;
            gap: 14px;
        }

        .dashboard-shell details {
            border-radius: 20px;
            padding: 16px 18px;
            background: #ffffff;
            border: 1px solid rgba(216, 222, 231, 0.92);
            box-shadow: 0 10px 28px rgba(44, 62, 80, 0.04);
        }

        .dashboard-shell summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 0.98rem;
        }

        .dashboard-shell table {
            margin-top: 12px;
        }

        .health-table-wrap {
            overflow-x: auto;
        }

        @media (max-width: 1100px) {
            .hero-grid,
            .insight-grid,
            .details-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            .dashboard-hero,
            .dashboard-panel,
            .module-card {
                padding: 20px;
            }

            .hero-aside-grid,
            .module-kpis,
            .signal-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="dashboard-shell">
        <section class="dashboard-hero">
            <div class="hero-grid">
                <div class="hero-copy">
                    <div class="hero-eyebrow">Painel institucional</div>
                    <h1>Painel do Grom.Seg</h1>
                    <p>
                        Centro executivo do ecossistema GROM. A entrada principal agora destaca a operacao, a governanca e os modulos institucionais com a densidade visual que o sistema exige.
                    </p>
                    <div class="hero-meta">
                        <div class="hero-chip">{{ $user->name }} ({{ $user->username }})</div>
                        <div class="hero-chip">{{ $currentDateLabel }}</div>
                    </div>
                </div>

                <aside class="hero-aside">
                    <small>Leitura executiva</small>
                    <strong>Acesso central por perfil, com expansao de modulos conforme permissao institucional.</strong>
                    <p>
                        O Grom.Seg concentra operacao, RH, escalas, relatorios, auditoria e governanca em uma unica plataforma, com acesso liberado conforme o nivel definido pela administracao.
                    </p>
                    <div class="hero-aside-grid">
                        <div class="hero-stat">
                            <span>Modulos visiveis</span>
                            <strong>{{ count($moduleCards) }}</strong>
                        </div>
                        <div class="hero-stat">
                            <span>Permissoes</span>
                            <strong>{{ $stats['permissoes'] }}</strong>
                        </div>
                        <div class="hero-stat">
                            <span>Usuarios</span>
                            <strong>{{ $stats['usuarios'] }}</strong>
                        </div>
                        <div class="hero-stat">
                            <span>Cartorios</span>
                            <strong>{{ $stats['cartorios'] }}</strong>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section>
            <div class="dashboard-section-head">
                <div>
                    <h2>Panorama executivo</h2>
                    <p>Indicadores de primeira leitura para mostrar porte operacional, governanca de acesso e situacao corrente da plataforma.</p>
                </div>
            </div>
            <div class="executive-grid">
                @foreach ($executiveCards as $card)
                    <article class="executive-card {{ $card['tone'] }}">
                        <small>{{ $card['eyebrow'] }}</small>
                        <strong>{{ $card['value'] }}</strong>
                        <p>{{ $card['label'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section>
            <div class="dashboard-section-head">
                <div>
                    <h2>Modulos institucionais</h2>
                    <p>Os acessos deixaram de ser apenas uma lista de links. Cada frente agora aparece como unidade funcional do sistema, com entrada principal e leitura rapida de volume.</p>
                </div>
            </div>
            <div class="module-grid">
                @foreach ($moduleCards as $module)
                    <article class="module-card {{ $module['accent'] }}">
                        <div class="module-head">
                            <small>{{ $module['eyebrow'] }}</small>
                            <h3>{{ $module['title'] }}</h3>
                            <p>{{ $module['description'] }}</p>
                        </div>

                        <div class="module-kpis">
                            @foreach ($module['kpis'] as $kpi)
                                <div class="module-kpi">
                                    <span>{{ $kpi['label'] }}</span>
                                    <strong>{{ $kpi['value'] }}</strong>
                                </div>
                            @endforeach
                        </div>

                        <div class="module-actions">
                            @if ($module['primary'])
                                <a href="{{ $module['primary']['url'] }}" class="module-primary">{{ $module['primary']['label'] }}</a>
                            @endif

                            <div class="module-links">
                                @foreach ($module['links'] as $link)
                                    <a href="{{ $link['url'] }}" class="module-link">{{ $link['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="insight-grid">
            <section class="dashboard-panel">
                <h2>Situacao hoje</h2>
                <p>Leitura operacional imediata para o dia corrente, com foco em disponibilidade do efetivo e saude da fila.</p>

                <div class="signal-grid">
                    <div class="signal-card">
                        <small>Efetivo ativo</small>
                        <strong>{{ $rhHoje['total_ativos'] ?? $stats['usuarios'] }}</strong>
                    </div>
                    <div class="signal-card">
                        <small>Pendencias</small>
                        <strong>{{ $produtividadeStats['fila_pendente'] ?? 0 }}</strong>
                    </div>
                    <div class="signal-card">
                        <small>BOs no mes</small>
                        <strong>{{ $produtividadeStats['boletins_mes'] ?? 0 }}</strong>
                    </div>
                    <div class="signal-card">
                        <small>Flagrantes no mes</small>
                        <strong>{{ $produtividadeStats['flagrantes_mes'] ?? 0 }}</strong>
                    </div>
                    <div class="signal-card">
                        <small>BOs nao-flagrante</small>
                        <strong>{{ $produtividadeStats['boletins_nao_flagrantes_mes'] ?? 0 }}</strong>
                    </div>
                    <div class="signal-card">
                        <small>MPU sem IP</small>
                        <strong>{{ $produtividadeStats['boletins_mpu_sem_ip_mes'] ?? 0 }}</strong>
                    </div>
                    <div class="signal-card">
                        <small>Plantoes atribuidos</small>
                        <strong>{{ $stats['escalas_plantoes'] }}</strong>
                    </div>
                </div>

                <div class="status-stack">
                    @if ($canViewRh && $rhHoje)
                        @if ($afastadosHojePreview->isEmpty())
                            <div class="status-row">
                                <strong>Efetivo completo em atividade</strong>
                                <span>Nao ha afastamentos ativos no recorte do dia atual.</span>
                            </div>
                        @else
                            @foreach ($afastadosHojePreview->take(3) as $af)
                                <div class="status-row">
                                    <strong>{{ $af->funcionario?->short_name ?? $af->funcionario?->name ?? '-' }}</strong>
                                    <span>
                                        {{ $af->reason }}
                                        @if ($af->end_date)
                                            | ate {{ $af->end_date->format('d/m') }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        @endif

                        @foreach ($agendadosPreview->take(2) as $af)
                            <div class="status-row">
                                <strong>{{ $af->funcionario?->short_name ?? $af->funcionario?->name ?? '-' }}</strong>
                                <span>
                                    Afastamento agendado para {{ $af->start_date->format('d/m') }}
                                    @if ($af->end_date)
                                        a {{ $af->end_date->format('d/m') }}
                                    @endif
                                    | {{ $af->reason }}
                                </span>
                            </div>
                        @endforeach
                    @else
                        <div class="status-row">
                            <strong>Leitura operacional restrita</strong>
                            <span>Seu perfil nao possui acesso ao detalhamento de RH no dashboard principal.</span>
                        </div>
                    @endif
                </div>
            </section>

            <section class="dashboard-panel">
                <h2>Capacidades institucionais</h2>
                <p>Recursos que reforcam maturidade operacional, seguranca e continuidade do sistema.</p>
                <div class="capability-cloud">
                    @foreach ($capabilityTags as $tag)
                        <span class="capability-pill">{{ $tag }}</span>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="dashboard-panel">
            <div class="dashboard-section-head">
                <div>
                    <h2>Dados carregados do sistema</h2>
                    <p>Amostras vivas das bases implementadas, organizadas em blocos mais legiveis para acompanhamento institucional.</p>
                </div>
            </div>

            <div class="details-grid">
                <div class="details-stack">
                    <details open>
                        <summary>Cartorios em uso</summary>
                        <table>
                            <thead>
                                <tr>
                                    <th>Cartorio</th>
                                    <th>Responsavel</th>
                                    <th>Mes corrente</th>
                                    <th>Pendentes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($cartorioPreview as $cartorio)
                                    @php($currentMonthStat = $cartorio->monthlyStats->first())
                                    <tr>
                                        <td>
                                            <strong>{{ $cartorio->name }}</strong><br>
                                            <span class="muted">{{ $cartorio->code }}</span><br>
                                            <span class="tag {{ $cartorio->is_active ? 'good' : 'warn' }}">{{ $cartorio->is_active ? 'Ativo' : 'Inativo' }}</span>
                                        </td>
                                        <td>
                                            {{ $cartorio->manager_name ?: 'Nao informado' }}<br>
                                            <span class="muted">{{ $cartorio->designacao ?: 'Sem designacao' }}</span>
                                        </td>
                                        <td>
                                            IP: {{ $currentMonthStat->ip_instaurados ?? 0 }}<br>
                                            Relatados: {{ $currentMonthStat->ip_relatados ?? 0 }}<br>
                                            Concluidos: {{ $currentMonthStat->concluidos ?? 0 }}
                                        </td>
                                        <td>
                                            {{ $cartorio->pending_import_items_count }} pendencias<br>
                                            {{ $cartorio->import_items_total_count }} itens importados
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">Nenhum cartorio carregado na visao atual.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </details>

                    <details>
                        <summary>Analise e importacao</summary>
                        <table>
                            <thead>
                                <tr>
                                    <th>Ultimo lote</th>
                                    <th>Origem</th>
                                    <th>Pendentes</th>
                                    <th>Confirmados</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($latestBatchesPreview as $batch)
                                    <tr>
                                        <td>
                                            <strong>{{ $batch->source_name }}</strong><br>
                                            <span class="muted">{{ $batch->imported_at?->format('d/m/Y H:i') }}</span>
                                        </td>
                                        <td>{{ $batch->source_type ?: 'Nao informada' }}</td>
                                        <td>{{ (int) $batch->pending_items_count }}</td>
                                        <td>{{ (int) $batch->confirmed_items_count }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">Nenhum lote recente.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <table>
                            <thead>
                                <tr>
                                    <th>Pendencia</th>
                                    <th>Cartorio</th>
                                    <th>Origem</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pendingItemsPreview as $item)
                                    <tr>
                                        <td>
                                            <strong>{{ $item->spj ?: $item->source_process_key }}</strong><br>
                                            <span class="muted">{{ $item->batch?->source_name }}</span>
                                        </td>
                                        <td>{{ $item->cartorio?->name ?: 'Sem cartorio' }}</td>
                                        <td>{{ $item->status_origem ?: 'Nao informado' }}</td>
                                        <td>{{ $item->data_fato?->format('d/m/Y') ?? 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">Nenhuma pendencia recente.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </details>
                </div>

                <div class="details-stack">
                    @if ($canViewRh)
                        <details>
                            <summary>Funcionarios em destaque</summary>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Funcionario</th>
                                        <th>Cargo</th>
                                        <th>Admissao</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($funcionariosPreview as $funcionario)
                                        <tr>
                                            <td>
                                                <strong>{{ $funcionario->matricula }}</strong><br>
                                                {{ $funcionario->name }}<br>
                                                <span class="muted">{{ $funcionario->short_name ?: '-' }}</span>
                                            </td>
                                            <td>
                                                {{ $funcionario->cargo?->code ?: 'N/A' }} - {{ $funcionario->cargo?->name ?: 'Sem cargo' }}<br>
                                                <span class="muted">{{ $funcionario->sector ?: 'Sem setor' }}</span>
                                            </td>
                                            <td>{{ $funcionario->admission_date?->format('d/m/Y') ?? 'N/A' }}</td>
                                            <td>
                                                @php($currentAfastamento = $funcionario->currentAfastamento())
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
                                            <td colspan="4">Nenhum funcionario carregado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </details>

                        <details>
                            <summary>Afastamentos e licencas</summary>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Funcionario</th>
                                        <th>Motivo</th>
                                        <th>Vigencia</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($afastamentosPreview as $afastamento)
                                        <tr>
                                            <td>{{ $afastamento->funcionario?->matricula }} - {{ $afastamento->funcionario?->name }}</td>
                                            <td>{{ $afastamento->reason }}</td>
                                            <td>
                                                {{ $afastamento->start_date?->format('d/m/Y') }}<br>
                                                <span class="muted">ate {{ $afastamento->end_date?->format('d/m/Y') ?: 'aberto' }}</span>
                                            </td>
                                            <td><span class="tag {{ $afastamento->statusTone() }}">{{ $afastamento->statusLabel() }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">Nenhum afastamento carregado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </details>

                        <details>
                            <summary>Delegados externos</summary>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Origem</th>
                                        <th>Funcao</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($delegadosPreview as $delegado)
                                        <tr>
                                            <td>
                                                <strong>{{ $delegado->name }}</strong><br>
                                                <span class="muted">{{ $delegado->registration_code ?: 'Sem codigo' }}</span>
                                            </td>
                                            <td>{{ $delegado->origin_unit }}</td>
                                            <td>{{ $delegado->role_title }}</td>
                                            <td><span class="tag {{ $delegado->statusTone() }}">{{ $delegado->statusLabel() }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">Nenhum delegado externo carregado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </details>

                        <details>
                            <summary>Calendario de RH</summary>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Feriado</th>
                                        <th>Escopo</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($holidaysPreview as $holiday)
                                        <tr>
                                            <td>{{ $holiday->holiday_date?->format('d/m/Y') ?? 'N/A' }}</td>
                                            <td>{{ $holiday->name }}</td>
                                            <td>{{ $holiday->scope }}</td>
                                            <td><span class="tag {{ $holiday->is_active ? 'good' : 'warn' }}">{{ $holiday->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">Nenhum feriado carregado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </details>
                    @endif

                    @if ($canViewEscalas && $escalaSnapshot)
                        <details>
                            <summary>Escalas do legado</summary>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Versao</th>
                                        <th>Dias</th>
                                        <th>Plantoes</th>
                                        <th>Feriados</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ $escalaSnapshot['version'] ?? 'N/D' }}</td>
                                        <td>{{ $escalaSnapshot['summary']['dias_total'] }}</td>
                                        <td>{{ $escalaSnapshot['summary']['plantoes_atribuicoes'] }}</td>
                                        <td>{{ $escalaSnapshot['summary']['feriados_mes'] }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <table>
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Escrivao</th>
                                        <th>Operacional</th>
                                        <th>Delegada</th>
                                        <th>Plantao externo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse (array_slice($escalaSnapshot['scale_rows'], 0, 5) as $row)
                                        <tr>
                                            <td>
                                                <strong>{{ $row['date_label'] }}</strong><br>
                                                <span class="muted">{{ $row['day_label'] }}</span>
                                            </td>
                                            <td>{{ $row['escrivao'] ?: '-' }}</td>
                                            <td>{{ $row['operacional'] ?: '-' }}</td>
                                            <td>{{ $row['delegada'] ?: '-' }}</td>
                                            <td>{{ $row['plantao_externo'] ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5">Nenhuma linha de escala carregada.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </details>
                    @endif
                </div>
            </div>
        </section>

        @if ($produtividadeStats)
            <section class="dashboard-panel">
                <h2>Saude operacional da fila</h2>
                <p>Ultimo retrato de processamento e absorcao da fila de importacao operacional.</p>
                <div class="health-table-wrap">
                    @if ($lastImportBatch)
                        <table>
                            <thead>
                                <tr>
                                    <th>Ultimo lote</th>
                                    <th>Origem</th>
                                    <th>Staged</th>
                                    <th>Atualizados</th>
                                    <th>Ignorados</th>
                                    <th>Erros</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ $lastImportBatch->imported_at?->format('d/m/Y H:i') }}</td>
                                    <td>{{ $lastImportBatch->source_name }}</td>
                                    <td>{{ (int) $lastImportBatch->rows_staged }}</td>
                                    <td>{{ (int) $lastImportBatch->rows_updated }}</td>
                                    <td>{{ (int) $lastImportBatch->rows_skipped }}</td>
                                    <td>{{ (int) $lastImportBatch->error_count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    @else
                        <p class="muted" style="margin: 0;">Nenhum lote de importacao processado ainda.</p>
                    @endif
                </div>
            </section>
        @endif
    </div>
@endsection

