<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EscalaDia;
use App\Models\EscalaVersao;

$hits = EscalaDia::query()
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
    ->get(['ano','mes','versao']);

$combos = [];
foreach ($hits as $h) {
    $key = $h->ano . '-' . $h->mes . '-' . $h->versao;
    $combos[$key] = ['ano' => (int)$h->ano, 'mes' => (int)$h->mes, 'versao' => (int)$h->versao];
}

if (empty($combos)) {
    echo "Nenhuma versão contaminada encontrada." . PHP_EOL;
    exit(0);
}

foreach ($combos as $c) {
    $nDias = EscalaDia::query()->where('ano',$c['ano'])->where('mes',$c['mes'])->where('versao',$c['versao'])->count();
    $nVers = EscalaVersao::query()->where('ano',$c['ano'])->where('mes',$c['mes'])->where('versao',$c['versao'])->count();

    EscalaDia::query()->where('ano',$c['ano'])->where('mes',$c['mes'])->where('versao',$c['versao'])->delete();
    EscalaVersao::query()->where('ano',$c['ano'])->where('mes',$c['mes'])->where('versao',$c['versao'])->delete();

    echo "Removido: {$c['ano']}/" . str_pad((string)$c['mes'],2,'0',STR_PAD_LEFT) . " v{$c['versao']} | dias={$nDias} | versao={$nVers}" . PHP_EOL;
}

$remaining = EscalaDia::query()
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
    ->count();

echo "Resíduos em escalas_dias após limpeza: {$remaining}" . PHP_EOL;
