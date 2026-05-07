<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EscalaDia;

$rows = EscalaDia::query()
    ->where('escrivao', 'like', '%Maria Souza%')
    ->orWhere('operacional', 'like', '%Maria Souza%')
    ->orWhere('fechar_nome', 'like', '%Maria Souza%')
    ->orWhere('delegada', 'like', '%Maria Souza%')
    ->orWhere('plantao_externo', 'like', '%Maria Souza%')
    ->orWhere('escrivao', 'like', '%Carlos Lima%')
    ->orWhere('operacional', 'like', '%Carlos Lima%')
    ->orWhere('fechar_nome', 'like', '%Carlos Lima%')
    ->orWhere('delegada', 'like', '%Carlos Lima%')
    ->orWhere('plantao_externo', 'like', '%Carlos Lima%')
    ->orWhere('escrivao', 'like', '%TSN%')
    ->orWhere('operacional', 'like', '%TSN%')
    ->orWhere('fechar_nome', 'like', '%TSN%')
    ->orWhere('delegada', 'like', '%TSN%')
    ->orWhere('plantao_externo', 'like', '%TSN%')
    ->orderBy('ano')->orderBy('mes')->orderBy('data')
    ->get(['id','ano','mes','data','versao','escrivao','operacional','fechar_nome','delegada','plantao_externo']);

echo 'Total hits: ' . $rows->count() . PHP_EOL;

$group = [];
foreach ($rows as $r) {
    $key = $r->ano . '/' . str_pad((string)$r->mes, 2, '0', STR_PAD_LEFT);
    $group[$key] = ($group[$key] ?? 0) + 1;
}
foreach ($group as $k => $n) {
    echo " - {$k}: {$n}" . PHP_EOL;
}

echo PHP_EOL . 'Amostra:' . PHP_EOL;
foreach ($rows->take(15) as $r) {
    echo $r->data->format('Y-m-d') . " v{$r->versao} | E={$r->escrivao} | O={$r->operacional} | F={$r->fechar_nome} | D={$r->delegada}" . PHP_EOL;
}
