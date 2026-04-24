@extends('layouts.app')

@section('title', 'Importar BOs | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Importar planilha de BOs</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Mesmo formato aceito pelo sistema Python â€” XLSX gerado pelo mÃ³dulo de AnÃ¡lise de Dados.
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
                    Tamanho mÃ¡ximo: 20 MB.
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
                    <th>ObrigatÃ³ria</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>NÂº RDO</td><td>Sim</td></tr>
                <tr><td>Data da OcorrÃªncia</td><td>Sim</td></tr>
                <tr><td>Lavrado</td><td>NÃ£o</td></tr>
                <tr><td>Flagrante</td><td>NÃ£o</td></tr>
                <tr><td>Ato Infracional</td><td>NÃ£o</td></tr>
                <tr><td>Ãrea do Fato</td><td>NÃ£o</td></tr>
                <tr><td>MPU</td><td>NÃ£o</td></tr>
                <tr><td>NÂº CNJ MPU</td><td>NÃ£o</td></tr>
                <tr><td>BO designado (CartÃ³rio)</td><td>NÃ£o</td></tr>
                <tr><td>NÂº IP</td><td>NÃ£o</td></tr>
                <tr><td>CartÃ³rio do IP (final)</td><td>NÃ£o</td></tr>
                <tr><td>Natureza 1 â€¦ Natureza 6</td><td>NÃ£o</td></tr>
                <tr><td>Consumo/Tentativa 1 â€¦ 6</td><td>NÃ£o</td></tr>
                <tr><td>VÃ­tima 1 (Nome) â€¦ VÃ­tima 6 (Nome)</td><td>NÃ£o</td></tr>
                <tr><td>VÃ­tima 1 (Tipo) â€¦ VÃ­tima 6 (Tipo)</td><td>NÃ£o</td></tr>
                <tr><td>Autor 1 â€¦ Autor 3</td><td>NÃ£o</td></tr>
            </tbody>
        </table>
        <p class="muted" style="margin: 10px 0 0; font-size: 0.85em;">
            BOs duplicados (mesmo NÂº RDO) serÃ£o <strong>atualizados</strong>, nÃ£o duplicados.
        </p>
    </section>
@endsection

