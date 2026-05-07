<?php
// Remover dados fictícios do banco PHP
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$matriculas = ['FUN-001', 'FUN-002'];

foreach ($matriculas as $mat) {
    $func = \App\Models\RhFuncionario::where('matricula', $mat)->first();
    if (!$func) {
        echo "Não encontrado: $mat\n";
        continue;
    }
    
    echo "Processando: {$func->name} ({$mat})\n";
    
    // Remover afastamentos associados
    $afas = \App\Models\RhAfastamento::where('funcionario_id', $func->id)->get();
    foreach ($afas as $af) {
        $af->delete();
        echo "  - Afastamento removido: {$af->reason}\n";
    }
    
    // Remover plantões de escala associados
    if (class_exists(\App\Models\EscalaPlantaoFuncionario::class)) {
        $plantoes = \App\Models\EscalaPlantaoFuncionario::where('funcionario_id', $func->id)->get();
        foreach ($plantoes as $p) {
            $p->delete();
            echo "  - Plantão removido\n";
        }
    }
    
    // Remover o funcionário
    $func->delete();
    echo "  - Funcionário removido.\n";
}

echo "\nConcluído.\n";
