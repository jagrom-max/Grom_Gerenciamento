@extends('layouts.app')

@section('title', 'Importar BOs | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Importar planilha de BOs</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Mesmo formato aceito pelo sistema Python â€” XLSX gerado pelo módulo de Análise de Dados.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error" style="margin-bottom:16px;">
            @foreach ($errors->all() as $err)
                <p style="margin:0;">{{ $err }}</p>
            @endforeach
        </div>
    @endif

    <section class="card" style="max-width: 600px;">
        <h2 style="margin-top:0;">Selecionar arquivo</h2>

        <form method="POST" action="{{ route('analise.bos.import.store') }}" enctype="multipart/form-data">
            @csrf

            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:6px; font-weight:600;">
                    Arquivo XLSX / CSV
                </label>
                <input type="file"
                       name="arquivo"
                       accept=".xlsx,.csv,.txt"
                       style="display:block; width:100%; padding:8px 0;">
                <p class="muted" style="margin:6px 0 0; font-size: 0.85em;">
                    Tamanho máximo: 20 MB.
                </p>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn">Importar</button>
                <a class="btn secondary" href="{{ route('analise.index') }}">Cancelar</a>
            </div>
        </form>
    </section>

    <section class="card" style="max-width: 600px; margin-top: 16px;">
        <h2 style="margin-top:0;">Colunas esperadas no arquivo</h2>
        <table>
            <thead>
                <tr>
                    <th>Coluna no XLSX</th>
                    <th>Obrigatória</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>NÂº RDO</td><td>Sim</td></tr>
                <tr><td>Data da Ocorrência</td><td>Sim</td></tr>
                <tr><td>Lavrado</td><td>Não</td></tr>
                <tr><td>Flagrante</td><td>Não</td></tr>
                <tr><td>Ato Infracional</td><td>Não</td></tr>
                <tr><td>Ãrea do Fato</td><td>Não</td></tr>
                <tr><td>MPU</td><td>Não</td></tr>
                <tr><td>NÂº CNJ MPU</td><td>Não</td></tr>
                <tr><td>BO designado (Cartório)</td><td>Não</td></tr>
                <tr><td>NÂº IP</td><td>Não</td></tr>
                <tr><td>Cartório do IP (final)</td><td>Não</td></tr>
                <tr><td>Natureza 1 â€¦ Natureza 6</td><td>Não</td></tr>
                <tr><td>Consumo/Tentativa 1 â€¦ 6</td><td>Não</td></tr>
                <tr><td>Vítima 1 (Nome) â€¦ Vítima 6 (Nome)</td><td>Não</td></tr>
                <tr><td>Vítima 1 (Tipo) â€¦ Vítima 6 (Tipo)</td><td>Não</td></tr>
                <tr><td>Autor 1 â€¦ Autor 3</td><td>Não</td></tr>
            </tbody>
        </table>
        <p class="muted" style="margin: 10px 0 0; font-size: 0.85em;">
            BOs duplicados (mesmo NÂº RDO) serão <strong>atualizados</strong>, não duplicados.
        </p>
    </section>
@endsection

