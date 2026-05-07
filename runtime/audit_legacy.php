<?php
// Script temporário de auditoria: lê todas as tabelas do legado Python e exibe contagens e amostras
$dbPath = 'C:/grom_gerenciamento_final/main/grom_database.sqlite3';
$db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);

$res = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
$tables = [];
while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
    $tables[] = $r['name'];
}

foreach ($tables as $t) {
    $n = $db->query("SELECT COUNT(*) as n FROM [$t]")->fetchArray(SQLITE3_ASSOC)['n'];
    echo "=== $t: $n registros ===\n";

    // Mostra schema
    $schema = $db->query("PRAGMA table_info([$t])");
    $cols = [];
    while ($col = $schema->fetchArray(SQLITE3_ASSOC)) {
        $cols[] = $col['name'];
    }
    echo "  Colunas: " . implode(', ', $cols) . "\n";

    // Amostra de até 3 linhas
    $sample = $db->query("SELECT * FROM [$t] LIMIT 3");
    $i = 0;
    while ($row = $sample->fetchArray(SQLITE3_ASSOC)) {
        echo "  Linha " . (++$i) . ": " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
}
$db->close();
