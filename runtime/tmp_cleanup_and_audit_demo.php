<?php
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\RhFuncionario;
use App\Models\RhAfastamento;
use App\Models\EscalaPlantaoFuncionario;
use App\Models\User;
use App\Models\EscalaDia;

$targetsByName = ['Maria Souza', 'Carlos Lima'];
$targetMatriculas = ['TSN260423165611'];

$funcionarios = RhFuncionario::query()
    ->whereIn('name', $targetsByName)
    ->orWhereIn('short_name', $targetsByName)
    ->orWhereIn('matricula', $targetMatriculas)
    ->orWhere('matricula', 'like', 'TSN%')
    ->get();

echo "Funcionarios alvo encontrados: " . $funcionarios->count() . PHP_EOL;
foreach ($funcionarios as $f) {
    echo " - {$f->id} | {$f->name} | {$f->matricula}" . PHP_EOL;
}

$ids = $funcionarios->pluck('id')->all();

if (!empty($ids)) {
    $afast = RhAfastamento::query()->whereIn('funcionario_id', $ids)->count();
    $plantoes = EscalaPlantaoFuncionario::query()->whereIn('funcionario_id', $ids)->count();
    $users = User::query()->whereIn('funcionario_id', $ids)->count();

    echo "Afastamentos a remover: {$afast}" . PHP_EOL;
    echo "Plantoes a remover: {$plantoes}" . PHP_EOL;
    echo "Usuarios vinculados a remover: {$users}" . PHP_EOL;

    RhAfastamento::query()->whereIn('funcionario_id', $ids)->delete();
    EscalaPlantaoFuncionario::query()->whereIn('funcionario_id', $ids)->delete();
    User::query()->whereIn('funcionario_id', $ids)->delete();
    RhFuncionario::query()->whereIn('id', $ids)->delete();
}

$hitsMaria = EscalaDia::query()->where('escrivao', 'like', '%Maria Souza%')
    ->orWhere('operacional', 'like', '%Maria Souza%')
    ->orWhere('fechar_nome', 'like', '%Maria Souza%')
    ->orWhere('delegada', 'like', '%Maria Souza%')
    ->orWhere('plantao_externo', 'like', '%Maria Souza%')
    ->count();

$hitsCarlosTsn = EscalaDia::query()->where('escrivao', 'like', '%Carlos Lima%')
    ->orWhere('operacional', 'like', '%Carlos Lima%')
    ->orWhere('fechar_nome', 'like', '%Carlos Lima%')
    ->orWhere('delegada', 'like', '%Carlos Lima%')
    ->orWhere('plantao_externo', 'like', '%Carlos Lima%')
    ->orWhere('escrivao', 'like', '%TSN260423165611%')
    ->orWhere('operacional', 'like', '%TSN260423165611%')
    ->orWhere('fechar_nome', 'like', '%TSN260423165611%')
    ->orWhere('delegada', 'like', '%TSN260423165611%')
    ->orWhere('plantao_externo', 'like', '%TSN260423165611%')
    ->count();

echo "Hits em escalas_dias para Maria Souza: {$hitsMaria}" . PHP_EOL;
echo "Hits em escalas_dias para Carlos/TSN: {$hitsCarlosTsn}" . PHP_EOL;
