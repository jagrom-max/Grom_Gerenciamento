@extends('layouts.app')

@section('title', 'Backup | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Backup</h1>
            <p class="muted" style="margin: 6px 0 0;">
                Area de conferência somente leitura para arquivos SQLite locais, relatórios gerados e base legada disponível no ambiente.
            </p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="btn secondary" href="{{ route('evolucao') }}">Evolucao</a>
        </div>
    </div>

    <div class="cards" style="margin-bottom: 18px;">
        <article class="card">
            <small>Arquivos SQLite</small>
            <strong>{{ $metrics['sqlite_files'] }}</strong>
            <span>Instantanea local para conferencia de ambiente.</span>
        </article>
        <article class="card">
            <small>PDFs recentes</small>
            <strong>{{ $metrics['report_pdfs'] }}</strong>
            <span>Relatorios timbrados ja gerados no runtime.</span>
        </article>
        <article class="card">
            <small>Base legada</small>
            <strong>{{ $metrics['legacy_exists'] ? 'Sim' : 'Nao' }}</strong>
            <span>Arquivo principal de analise e escala disponível.</span>
        </article>
    </div>

    <div class="grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 18px;">
        <section class="card">
            <h2 style="margin-top: 0;">Bases locais</h2>
            <table>
                <thead>
                    <tr>
                        <th>Arquivo</th>
                        <th>Tamanho</th>
                        <th>Atualizado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($localDbFiles as $file)
                        <tr>
                            <td>
                                <strong>{{ $file['name'] }}</strong><br>
                                <span class="muted">{{ $file['path'] }}</span>
                            </td>
                            <td>{{ number_format($file['size'] / 1024, 1, ',', '.') }} KB</td>
                            <td>{{ $file['modified_at']->format('d/m/Y H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">Nenhum arquivo SQLite local encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2 style="margin-top: 0;">Relatorios recentes</h2>
            <table>
                <thead>
                    <tr>
                        <th>PDF</th>
                        <th>Tamanho</th>
                        <th>Atualizado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentReports as $file)
                        <tr>
                            <td>
                                <strong>{{ $file['name'] }}</strong><br>
                                <span class="muted">{{ $file['path'] }}</span>
                            </td>
                            <td>{{ number_format($file['size'] / 1024, 1, ',', '.') }} KB</td>
                            <td>{{ $file['modified_at']->format('d/m/Y H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">Nenhum PDF recente encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <section class="card">
        <h2 style="margin-top: 0;">Caminhos observados</h2>
        <div class="grid">
            <div class="tag good">Storage local: {{ $paths['storage'] }}</div>
            <div class="tag good">Pasta de relatorios: {{ $paths['reports'] }}</div>
            <div class="tag good">Base legada: {{ $paths['legacy'] }}</div>
            <div class="tag good">Gerado em: {{ $generatedAt->format('d/m/Y H:i:s') }}</div>
        </div>
    </section>
@endsection
