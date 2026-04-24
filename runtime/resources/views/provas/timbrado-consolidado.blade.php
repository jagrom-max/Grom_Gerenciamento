@php
$brasaoSrc = \App\Support\ReportAsset::dataUri('assets/brasao.png');
$logoSrc = \App\Support\ReportAsset::dataUri('assets/logo_grom.png');
$watermarkSrc = \App\Support\ReportAsset::dataUri('assets/marca_dagua.png');
@endphp

<x-report.default
    title="Prova do Timbrado Consolidado"
    period="Padrão institucional"
    :generatedAt="now()"
    origin="Validação visual"
    footer-note="Cartório Central - Gerenciamento"
    :brasao-src="$brasaoSrc"
    :logo-src="$logoSrc"
    :watermark-src="$watermarkSrc"
>
    <x-slot:toolbar>
        <span style="font-weight:700;">Prova visual do timbrado consolidado</span>
        <span style="font-size:.85em; color:var(--ink-soft);">Use esta página para validar brasão, logo, cabeçalho, rodapé e margens A4.</span>
    </x-slot:toolbar>

    <x-slot:summary>
        <article class="card">
            <small>Brasão</small>
            <strong>OK</strong>
            <span>Posicionado conforme o shell oficial.</span>
        </article>
        <article class="card">
            <small>Logo</small>
            <strong>OK</strong>
            <span>Rodapé consolidado com a marca institucional.</span>
        </article>
        <article class="card">
            <small>Cabeçalho</small>
            <strong>OK</strong>
            <span>Alinhamento central e estrutura única.</span>
        </article>
        <article class="card">
            <small>Rodapé</small>
            <strong>OK</strong>
            <span>Cartório Central - Gerenciamento.</span>
        </article>
    </x-slot:summary>

    <section>
        <div style="margin-bottom: 10px; color: var(--ink-soft); font-size: 9pt; line-height: 1.5;">
            Esta folha foi montada para validar o padrão consolidado antes de qualquer ajuste em massa.
            O objetivo é conferir se o timbrado aparece igual em todos os relatórios institucionais.
        </div>

        <table style="table-layout: fixed; font-size: 8.4pt;">
            <colgroup>
                <col style="width:28%">
                <col style="width:24%">
                <col style="width:24%">
                <col style="width:24%">
            </colgroup>
            <thead>
                <tr>
                    <th>Elemento</th>
                    <th style="text-align:center;">Status</th>
                    <th>Validação</th>
                    <th>Observação</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Brasão institucional</td>
                    <td style="text-align:center; font-weight:700;">OK</td>
                    <td>Presença e proporção</td>
                    <td>Sem corte e sem deslocamento lateral.</td>
                </tr>
                <tr>
                    <td>Identidade do cabeçalho</td>
                    <td style="text-align:center; font-weight:700;">OK</td>
                    <td>Regra de ouro</td>
                    <td>Uma única composição institucional para todos os relatórios.</td>
                </tr>
                <tr>
                    <td>Rodapé consolidado</td>
                    <td style="text-align:center; font-weight:700;">OK</td>
                    <td>Padronização</td>
                    <td>Cartório Central - Gerenciamento.</td>
                </tr>
            </tbody>
        </table>
    </section>
</x-report.default>
