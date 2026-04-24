<?php

namespace App\Services\Operacional;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use SQLite3;

/**
 * Lê a tabela `mandados` do banco SQLite legado (main/grom_database.sqlite3)
 * e devolve um snapshot simples para comparações e exibição no painel.
 */
class LegacyMandadosReader
{
    /** Retorna contagens e os primeiros N registros do legado. */
    public function snapshot(int $limit = 500): array
    {
        $db = $this->openDb();

        try {
            if (! $this->tableExists($db, 'mandados')) {
                return ['total' => 0, 'rows' => [], 'warning' => 'Tabela mandados nao encontrada no banco legado.'];
            }

            $total = (int) $db->querySingle('SELECT COUNT(*) FROM mandados');

            $stmt = $db->prepare(
                'SELECT id, tipo_mandado, subtipo_prisao, tipo_sigla,
                        cnj_numero, vara, nome, cpf, rg,
                        data_emissao, validade,
                        tipificacao_penal, artigo, paragrafo,
                        pena_anos, pena_meses, pena_dias, regime,
                        procedimento, cumprido_por, data_cumprimento, bo_numero,
                        observacoes
                 FROM mandados
                 ORDER BY id DESC
                 LIMIT :lim'
            );
            $stmt->bindValue(':lim', $limit, SQLITE3_INTEGER);
            $result = $stmt->execute();

            $rows = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = $row;
            }
        } finally {
            $db->close();
        }

        return ['total' => $total, 'rows' => $rows];
    }

    // -------------------------------------------------------
    // Internals
    // -------------------------------------------------------
    private function openDb(): SQLite3
    {
        if (! config('grom_legacy.enabled')) {
            throw new RuntimeException('Leitura da base legada desabilitada neste ambiente.');
        }

        $path = (string) config('grom_legacy.analise_db_path');

        if ($path === '' || ! File::exists($path)) {
            throw new InvalidArgumentException('Banco legado nao encontrado: ' . $path);
        }

        $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
        $db->enableExceptions(true);
        $db->busyTimeout(5000);

        return $db;
    }

    private function tableExists(SQLite3 $db, string $table): bool
    {
        $r = $db->querySingle(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=" . $db->escapeString($table)
        );
        return (int) $r > 0;
    }
}
