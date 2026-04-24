<?php

namespace App\Services\Legacy;

use Illuminate\Support\Facades\File;
use RuntimeException;
use SQLite3;

/**
 * Ponto central e único para abertura do banco legado SQLite (somente leitura).
 *
 * Regras de uso:
 *  - Toda conexão é aberta com SQLITE3_OPEN_READONLY — nunca grava no legado.
 *  - busyTimeout de 3 s evita trava indefinida se outro processo estiver escrevendo.
 *  - Cada chamada a withConnection() abre, usa e fecha a conexão na mesma operação.
 *    Isso evita conexões abertas em background que disputam I/O com o Windows.
 *  - Nenhum processo PHP fica em polling ou loop ativo — as leituras são pontuais,
 *    sob demanda, e nunca ficam em background persistente.
 */
final class LegacyDatabaseService
{
    private string $dbPath;

    public function __construct()
    {
        $this->dbPath = (string) config('grom_legacy.analise_db_path', '');
    }

    /**
     * Executa $callback com uma conexão SQLite3 aberta e fecha-a ao terminar.
     * Nunca deixa conexão aberta entre requisições.
     *
     * @template T
     * @param  callable(SQLite3): T  $callback
     * @return T
     */
    public function withConnection(callable $callback): mixed
    {
        $this->assertAvailable();

        $db = new SQLite3($this->dbPath, SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
        $db->busyTimeout(3000);

        // Leitura leve: impede que o SQLite bloqueie o I/O do sistema por
        // mais de 3 segundos, garantindo que outros processos (Outlook, Chrome)
        // não sejam impactados.
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA cache_size = -2000');   // 2 MB máx.
        $db->exec('PRAGMA temp_store = MEMORY');

        try {
            return $callback($db);
        } finally {
            $db->close();
        }
    }

    /**
     * Retorna array de linhas de uma query preparada, com bind de parâmetros.
     *
     * @param  array<string, mixed>  $params  chave => valor p/ bindValue
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->withConnection(function (SQLite3 $db) use ($sql, $params): array {
            $stmt = $db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $this->sqliteType($value));
            }

            $result = $stmt->execute();
            $rows = [];

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = $row;
            }

            return $rows;
        });
    }

    /**
     * Retorna um único valor escalar.
     */
    public function fetchScalar(string $sql, array $params = []): mixed
    {
        return $this->withConnection(function (SQLite3 $db) use ($sql, $params): mixed {
            $stmt = $db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $this->sqliteType($value));
            }

            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_NUM);

            return $row[0] ?? null;
        });
    }

    /**
     * Verifica se uma tabela existe no legado.
     */
    public function tableExists(string $table): bool
    {
        $result = $this->fetchScalar(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=:name",
            [':name' => $table]
        );

        return (int) $result > 0;
    }

    public function isAvailable(): bool
    {
        return config('grom_legacy.enabled', false)
            && $this->dbPath !== ''
            && File::exists($this->dbPath);
    }

    public function getDbPath(): string
    {
        return $this->dbPath;
    }

    // ─── Internos ────────────────────────────────────────────────────────────

    private function assertAvailable(): void
    {
        if (! config('grom_legacy.enabled', false)) {
            throw new RuntimeException('Sincronização com a base legada está desabilitada neste ambiente.');
        }

        if ($this->dbPath === '') {
            throw new RuntimeException('Caminho da base legada não configurado (grom_legacy.analise_db_path).');
        }

        if (! File::exists($this->dbPath)) {
            throw new RuntimeException("Base legada não encontrada: {$this->dbPath}");
        }
    }

    private function sqliteType(mixed $value): int
    {
        return match (true) {
            is_int($value)   => SQLITE3_INTEGER,
            is_float($value) => SQLITE3_FLOAT,
            is_null($value)  => SQLITE3_NULL,
            default          => SQLITE3_TEXT,
        };
    }
}
