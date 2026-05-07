<?php
// Verificar se os dados fictícios existem no banco PHP
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$nomes = ['Carlos Lima', 'Maria Souza', 'Ricardo Alves', 'Helena Martins'];

foreach ($nomes as $nome) {
    $func = \App\Models\RhFuncionario::where('name', $nome)->first();
    if ($func) {
        echo "ENCONTRADO: $nome (id={$func->id}, matricula={$func->matricula})\n";
    } else {
        echo "nao encontrado: $nome\n";
    }
}

// Verificar também usuários
foreach ($nomes as $nome) {
    $user = \App\Models\User::where('name', $nome)->first();
    if ($user) {
        echo "USER ENCONTRADO: $nome (id={$user->id})\n";
    }
}
