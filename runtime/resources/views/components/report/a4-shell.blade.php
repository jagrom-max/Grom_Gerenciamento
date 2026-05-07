@props([
    'title',
    'period',
    'generatedAt',
    'origin' => 'Grom.Seg',
    'footerNote' => 'Cartório Central - Gerenciamento | grom.seg.br',
    'brasaoSrc' => asset('assets/brasao.png'),
    'logoSrc' => asset('assets/logo_grom.png'),
    'watermarkSrc' => asset('assets/marca_dagua.png'),
    'totalPages' => 1,
])
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | Grom.Seg</title>
    <style>
        :root {
            --ink: #213547;
            --ink-soft: #5a6a7a;
            --line: #d5dde7;
            --panel: #ffffff;
            --soft: #eef3f8;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: linear-gradient(180deg, #edf2f7 0%, #e4ebf3 100%);
            font-family: "Segoe UI", Tahoma, sans-serif;
            color: var(--ink);
            padding: 24px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        @page {
            size: A4;
            margin: 10mm 8mm 5mm 8mm;
            @bottom-right {
                content: "";
                font-family: "Segoe UI", Tahoma, sans-serif;
                font-size: 8pt;
                color: #5a6a7a;
            }
        }
        .toolbar {
            max-width: 210mm;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }
        .toolbar a, .toolbar button {
            border: 1px solid var(--line);
            background: var(--panel);
            color: var(--ink);
            border-radius: 12px;
            padding: 10px 14px;
            text-decoration: none;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .sheet {
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: var(--panel);
            box-shadow: 0 18px 40px rgba(33, 53, 71, .12);
            padding: 0;
            position: relative;
            overflow: hidden;
        }
        .page-frame {
            position: relative;
            min-height: 297mm;
            padding: 12mm 14mm 16mm 32mm;
            box-sizing: border-box;
        }
        .watermark {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            opacity: .05;
            pointer-events: none;
        }
        .watermark img {
            width: 118mm;
            height: 118mm;
            object-fit: contain;
        }
        .content {
            position: relative;
        }
        header {
            position: relative;
            min-height: 24mm;
            margin-bottom: 5mm;
            padding-top: 1mm;
        }
        .header-brand {
            position: absolute;
            top: 0;
            left: -24mm;
            width: 22mm;
            height: 22mm;
            display: flex;
            align-items: flex-start;
            justify-content: flex-start;
        }
        .header-brand img {
            width: 22mm;
            height: 22mm;
            object-fit: contain;
        }
        .head-text {
            text-align: center;
            padding-left: 6mm;
        }
        .head-text strong {
            display: block;
            font-size: 13pt;
            margin-bottom: 2mm;
        }
        .head-text span {
            display: block;
            font-size: 10pt;
            color: var(--ink-soft);
            line-height: 1.45;
        }
        .title-band {
            margin: 20mm 0 0.8mm;
            background: var(--soft);
            border: 1px solid var(--line);
            padding: 2.6mm 4mm;
            font-weight: 700;
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: .05em;
            text-align: center;
        }
        .meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4mm;
            margin-bottom: 10mm;
            font-size: 9.5pt;
            color: var(--ink-soft);
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(48mm, 1fr));
            gap: 4mm;
            margin-bottom: 6mm;
        }
        .card {
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 14px;
            padding: 4mm;
            min-height: 28mm;
        }
        .card small {
            display: block;
            color: var(--ink-soft);
            text-transform: uppercase;
            letter-spacing: .05em;
            font-size: 7.8pt;
            margin-bottom: 3mm;
        }
        .card strong {
            font-size: 18pt;
            display: block;
        }
        .card span {
            display: block;
            margin-top: 2mm;
            font-size: 8.5pt;
            color: var(--ink-soft);
        }
        .report-body {
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.8pt;
        }
        th, td {
            border: 1px solid var(--line);
            padding: 3mm;
            vertical-align: middle;
        }
        th {
            background: var(--soft);
            text-align: left;
            font-size: 8.4pt;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        tbody tr:nth-child(even) td {
            background: #fbfdff;
        }
        .footer {
            position: absolute;
            left: 14mm;
            right: 14mm;
            bottom: 6mm;
            display: grid;
            grid-template-columns: 30mm 1fr auto;
            gap: 8mm;
            align-items: center;
            border-top: 1px solid var(--line);
            padding-top: 3mm;
            font-size: 8.8pt;
            color: var(--ink-soft);
        }
        .footer img {
            width: 26mm;
            height: 26mm;
            object-fit: contain;
        }
        .footer-brand {
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }
        .footer-center {
            text-align: center;
            font-weight: 600;
            color: var(--ink);
            display: flex;
            flex-direction: column;
            gap: 1px;
            line-height: 1.2;
        }
        .footer-center-main {
            display: block;
        }
        .footer-center-site {
            display: block;
            font-size: 8pt;
            font-weight: 500;
            color: var(--ink-soft);
        }
        .footer-right {
            text-align: right;
            white-space: nowrap;
            line-height: 1.2;
            min-width: 30mm;
        }
        .footer-right-date {
            display: block;
            margin-bottom: 1px;
        }
        .footer-right-page {
            display: block;
        }
        /* page-number e total-pages preenchidos pelo servidor */
        @media (max-width: 900px) {
            body { padding: 12px; }
            .toolbar, .meta, .cards { grid-template-columns: 1fr; display: grid; }
            .sheet { padding: 0; }
            .page-frame { padding: 14mm 10mm 20mm; }
            header { grid-template-columns: 1fr; gap: 6mm; }
        }
        @media print {
            *, *::before, *::after {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body { padding: 0 !important; background: #fff !important; }
            .toolbar { display: none !important; }
            .watermark { display: none !important; }
            /* Folha sem altura mínima forçada */
            .sheet { box-shadow: none !important; max-width: none !important; min-height: auto !important; overflow: visible !important; }
            .page-frame {
                min-height: auto !important;
                padding: 21mm 8mm 15mm 8mm !important;
            }
            header {
                position: fixed !important;
                top: 0 !important;
                left: 8mm !important;
                right: 8mm !important;
                z-index: 4;
                display: flex !important;
                align-items: center !important;
                gap: 12px !important;
                min-height: auto !important;
                margin-bottom: 0 !important;
                padding-top: 0 !important;
                padding-bottom: 2mm !important;
                border-bottom: 1.5px solid #000 !important;
                background: #fff !important;
            }
            .header-brand {
                position: static !important;
                top: auto !important;
                left: auto !important;
                width: 22mm !important;
                height: 22mm !important;
                display: flex !important;
                align-items: center !important;
                justify-content: flex-start !important;
                flex: 0 0 auto !important;
            }
            .header-brand img { width: 22mm !important; height: 22mm !important; }
            .head-text {
                flex: 1 !important;
                padding-left: 0 !important;
                text-align: center !important;
            }
            .head-text strong { font-size: 11pt !important; margin-bottom: 1mm !important; }
            .head-text span { font-size: 8pt !important; line-height: 1.35 !important; }
            /* Title band centralizada e compacta */
            .title-band {
                margin: 2.5mm 0 0.4mm !important;
                padding: 2mm 4mm !important;
                text-align: center !important;
                font-size: 9.5pt !important;
                background: var(--soft) !important;
                border: 1px solid var(--line) !important;
            }
            .meta,
            .cards {
                display: none !important;
            }
            .report-body { margin-top: 0 !important; }
            /* Tabela compacta â€” sem word-break nas colunas */
            table { font-size: 7pt !important; }
            th {
                background: var(--soft) !important;
                white-space: normal !important;
                word-break: normal !important;
                overflow-wrap: normal !important;
                padding: 1.5mm 2mm !important;
                font-size: 7pt !important;
                /* text-align herdado do atributo style inline de cada th */
            }
            td { padding: 1.5mm 2mm !important; vertical-align: middle !important; }
            tbody tr:nth-child(even) td { background: #fbfdff !important; }
            tfoot tr td { background: var(--soft) !important; }
            tfoot tr td strong { font-weight: 700; }
            /* Rodapé fixo no fim da página física â€” não quebra em 2Âª folha */
            .footer {
                position: fixed !important;
                bottom: -1mm !important;
                left: 0 !important;
                right: 0 !important;
                padding: 1.5mm 10mm !important;
                background: #fff !important;
                grid-template-columns: 26mm 1fr auto !important;
                gap: 5mm !important;
            }
            .footer img { width: 22mm !important; height: 22mm !important; }
            .footer-right-page { display: block !important; }
        }
    </style>
</head>
<body>
    @isset($toolbar)
        <div class="toolbar">
            {{ $toolbar }}
        </div>
    @endisset

    <article class="sheet">
        <div class="watermark">
            <img src="{{ $watermarkSrc }}" alt="">
        </div>

        <div class="page-frame content">
            <header>
                <div class="header-brand">
                    <img src="{{ $brasaoSrc }}" alt="Brasao institucional">
                </div>
                <div class="head-text">
                    <strong>Delegacia de Defesa da Mulher de Rio Claro</strong>
                    <span>Delegacia Seccional de Policia de Rio Claro</span>
                    <span>DEINTER 9 - Piracicaba</span>
                </div>
            </header>

            <div class="title-band">{{ $title }}</div>

            <section class="report-body">
                {{ $slot }}
            </section>

            <div class="footer">
                <div class="footer-brand">
                    <img src="{{ $logoSrc }}" alt="Logo GROM">
                </div>
                <div class="footer-center">
                    <span class="footer-center-main">Cartório Central - Gerenciamento</span>
                    <span class="footer-center-site">grom.seg.br</span>
                </div>
                <div class="footer-right">
                    <span class="footer-right-date">{{ $generatedAt->format('d/m/Y') }}</span>
                    <span class="footer-right-page" aria-label="Pagina">1/1</span>
                </div>
            </div>
        </div>
    </article>
    <script>
        window.addEventListener('load', function () {
            if (new URLSearchParams(window.location.search).get('noprint') !== '1') {
                window.print();
            }
        });
    </script>
</body>
</html>

