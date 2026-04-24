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

<x-report.a4-shell
    :title="$title"
    :period="$period"
    :generatedAt="$generatedAt"
    :origin="$origin"
    :footer-note="$footerNote"
    :brasao-src="$brasaoSrc"
    :logo-src="$logoSrc"
    :watermark-src="$watermarkSrc"
    :total-pages="$totalPages"
>
    @isset($toolbar)
        <x-slot:toolbar>{{ $toolbar }}</x-slot:toolbar>
    @endisset

    @isset($summary)
        <x-slot:summary>{{ $summary }}</x-slot:summary>
    @endisset

    {{ $slot }}
</x-report.a4-shell>

