<?php
// Script de importação dos mandados do legado Python para o banco PHP
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Mostra colunas da tabela PHP
$phpCols = \Illuminate\Support\Facades\Schema::getColumnListing('operacional_mandados');
echo "=== Colunas PHP (operacional_mandados) ===" . PHP_EOL;
echo implode(', ', $phpCols) . PHP_EOL . PHP_EOL;

// Conta mandados
$phpCount = \App\Models\OperacionalMandado::count();
echo "Mandados PHP: $phpCount" . PHP_EOL;

// Lê mandados do legado
$dbPath = 'C:/grom_gerenciamento_final/main/grom_database.sqlite3';
$db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
$res = $db->query("SELECT * FROM mandados WHERE deleted_at IS NULL ORDER BY id");
$mandados = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $mandados[] = $row;
}
$db->close();
echo "Mandados legado: " . count($mandados) . PHP_EOL . PHP_EOL;

if ($phpCount > 0) {
    echo "PHP já tem $phpCount mandados — importando apenas os faltantes (pelos nomes)." . PHP_EOL;
    $existingNames = \App\Models\OperacionalMandado::pluck('nome')->map(fn($n) => strtoupper(trim($n)))->toArray();
    $mandados = array_filter($mandados, fn($m) => !in_array(strtoupper(trim($m['nome'] ?? '')), $existingNames));
    echo "Faltantes: " . count($mandados) . PHP_EOL . PHP_EOL;
}

// Importa cada mandado
$imported = 0;
$skipped = 0;
foreach ($mandados as $m) {
    try {
        // Deriva tipo_sigla se nulo no legado
        $tipoSigla = $m['tipo_sigla'] ?? null;
        if (!$tipoSigla) {
            $subtipo = strtolower($m['subtipo_prisao'] ?? '');
            if (str_contains($subtipo, 'preventivo'))       $tipoSigla = 'MPP';
            elseif (str_contains($subtipo, 'definitivo'))   $tipoSigla = 'MPD';
            elseif (str_contains($subtipo, 'temporário') || str_contains($subtipo, 'temporario')) $tipoSigla = 'MPT';
            else                                            $tipoSigla = 'MPP'; // fallback
        }
        \App\Models\OperacionalMandado::create([
            'tipo_mandado'       => $m['tipo_mandado']       ?? null,
            'subtipo_prisao'     => $m['subtipo_prisao']     ?? null,
            'vara'               => $m['vara']               ?? null,
            'cnj_numero'         => $m['cnj_numero']         ?? null,
            'data_emissao'       => $m['data_emissao']       ?? null,
            'validade'           => $m['validade']           ?? null,
            'nome'               => $m['nome']               ?? null,
            'cpf'                => $m['cpf']                ?? null,
            'rg'                 => $m['rg']                 ?? null,
            'tipificacao_penal'  => $m['tipificacao_penal']  ?? null,
            'artigo'             => $m['artigo']             ?? null,
            'paragrafo'          => $m['paragrafo']          ?? null,
            'pena_anos'          => $m['pena_anos']          ?? 0,
            'pena_meses'         => $m['pena_meses']         ?? 0,
            'pena_dias'          => $m['pena_dias']          ?? 0,
            'regime'             => $m['regime']             ?? null,
            'procedimento'       => $m['procedimento']       ?? null,
            'cumprido_por'       => $m['cumprido_por']       ?? null,
            'data_cumprimento'   => $m['data_cumprimento']   ?? null,
            'numero_ocorrencia'  => $m['numero_ocorrencia']  ?? null,
            'observacoes'        => $m['observacoes']        ?? null,
            'tipo_sigla'         => $tipoSigla,
            'tipificacoes_extra' => $m['tipificacoes_extra'] ?? null,
        ]);
        $imported++;
        echo "  ✓ id={$m['id']} {$m['nome']}" . PHP_EOL;
    } catch (\Exception $e) {
        $skipped++;
        echo "  ✗ id={$m['id']} ERRO: " . $e->getMessage() . PHP_EOL;
    }
}
echo PHP_EOL . "Importados: $imported | Erros: $skipped" . PHP_EOL;
echo "Total PHP agora: " . \App\Models\OperacionalMandado::count() . PHP_EOL;

