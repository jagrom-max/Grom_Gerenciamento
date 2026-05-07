@extends('layouts.app')

@section('title', 'Prova da Escala Mensal | Grom.Seg')

@section('content')
    <div class="section-head">
        <div>
            <h1>Prova da Escala</h1>
        </div>
        <div class="actions no-print">
            <a class="btn secondary" href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer">Abrir pré-visualização</a>
            <a class="btn secondary" href="{{ route('escalas.index', $filters) }}">Voltar para escala</a>
        </div>
    </div>

    <section class="card">
        <iframe
            style="width:100%; min-height:1180px; border:1px solid var(--line); border-radius:12px; background:#fff;"
            src="{{ $previewUrl }}"
            title="Pré-visualização da escala mensal"
            loading="lazy"></iframe>
    </section>
@endsection
