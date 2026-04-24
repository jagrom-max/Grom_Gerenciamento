<?php

namespace App\Services\Analise;

use App\Services\Legacy\LegacyDatabaseService;

/**
 * Lê dados de Boletins de Ocorrências do banco legado (somente leitura).
 *
 * Tabelas utilizadas:
 *  - analise_ocorrencias       — BO principal (1082 registros em abr/2026)
 *  - analise_naturezas         — Naturezas por SPJ (6492 registros)
 *  - analise_vitimas           — Vítimas por SPJ (3756 registros)
 *  - analise_autores           — Autores por SPJ (1878 registros)
 *  - analise_ocorrencias_extra — Dados extras de cada BO (1082 registros)
 *
 * Princípios de desempenho:
 *  - Conexão aberta e fechada dentro de cada chamada (via LegacyDatabaseService).
 *  - Nenhum polling ou background — lê apenas quando requisitado.
 *  - LIMIT sempre aplicado para evitar varredura completa em operações de listagem.
 */
final class LegacyAnaliseReader
{
    public function __construct(
        private readonly LegacyDatabaseService $db,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Contagens e sumário
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retorna totalizadores gerais dos BOs.
     *
     * @return array{total:int, com_mpu:int, com_ip:int, flagrantes:int,
     *               cartorios:array<string,int>, areas:array<string,int>}
     */
    public function sumario(): array
    {
        if (! $this->db->isAvailable()) {
            return $this->emptySumario();
        }

        $rows = $this->db->fetchAll("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN mpu_numero IS NOT NULL AND trim(mpu_numero) <> '' THEN 1 ELSE 0 END) AS com_mpu,
                SUM(CASE WHEN num_ip     IS NOT NULL AND trim(num_ip)     <> '' THEN 1 ELSE 0 END) AS com_ip,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes
            FROM analise_ocorrencias
        ");

        $base = $rows[0] ?? [];

        return [
            'total'      => (int) ($base['total']      ?? 0),
            'com_mpu'    => (int) ($base['com_mpu']    ?? 0),
            'com_ip'     => (int) ($base['com_ip']     ?? 0),
            'flagrantes' => (int) ($base['flagrantes'] ?? 0),
            'cartorios'  => $this->countBy('analise_ocorrencias', 'cartorio_ip', 10),
            'areas'      => $this->countBy('analise_ocorrencias', 'area_fato', 10),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Naturezas
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Top-N naturezas mais frequentes (por SPJ único — uma natureza por BO).
     *
     * @return array<int, array{natureza_label:string,total:int,tentado:int,consumado:int}>
     */
    public function topNaturezas(int $limit = 20): array
    {
        if (! $this->db->isAvailable()) {
            return [];
        }

        return $this->db->fetchAll("
            SELECT
                natureza_label,
                COUNT(DISTINCT spj)  AS total,
                SUM(CASE WHEN tentado_consumado = 'Tentado'  THEN 1 ELSE 0 END) AS tentado,
                SUM(CASE WHEN tentado_consumado = 'Consumado' THEN 1 ELSE 0 END) AS consumado
            FROM analise_naturezas
            WHERE trim(coalesce(natureza_label,'')) <> ''
            GROUP BY natureza_label
            ORDER BY total DESC
            LIMIT :limit
        ", [':limit' => $limit]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Distribuição por cartório / área / MPU
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Estatísticas agrupadas por cartório de IP.
     *
     * @return array<int, array{cartorio:string,total:int,com_ip:int,com_mpu:int,flagrantes:int}>
     */
    public function porCartorio(): array
    {
        if (! $this->db->isAvailable()) {
            return [];
        }

        return $this->db->fetchAll("
            SELECT
                COALESCE(NULLIF(trim(cartorio_ip), ''), 'Sem cartório') AS cartorio,
                COUNT(*) AS total,
                SUM(CASE WHEN trim(coalesce(num_ip,''))     <> '' THEN 1 ELSE 0 END) AS com_ip,
                SUM(CASE WHEN trim(coalesce(mpu_numero,'')) <> '' THEN 1 ELSE 0 END) AS com_mpu,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes
            FROM analise_ocorrencias
            GROUP BY cartorio
            ORDER BY total DESC
        ");
    }

    /**
     * Estatísticas agrupadas por área do fato.
     *
     * @return array<int, array{area:string,total:int,flagrantes:int}>
     */
    public function porArea(): array
    {
        if (! $this->db->isAvailable()) {
            return [];
        }

        return $this->db->fetchAll("
            SELECT
                COALESCE(NULLIF(trim(area_fato), ''), 'Não informada') AS area,
                COUNT(*) AS total,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes
            FROM analise_ocorrencias
            GROUP BY area
            ORDER BY total DESC
        ");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Listagem de BOs com filtros
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Lista BOs paginados com filtros opcionais.
     *
     * @param  array{q?:string,area?:string,cartorio?:string,flagrante?:bool,com_mpu?:bool,com_ip?:bool,ano?:int}  $filters
     * @return array{total:int, rows:array<int,array<string,mixed>>}
     */
    public function listar(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        if (! $this->db->isAvailable()) {
            return ['total' => 0, 'rows' => []];
        }

        [$where, $params] = $this->buildWhere($filters);

        $total = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM analise_ocorrencias ao
             LEFT JOIN analise_ocorrencias_extra ae ON ae.spj = ao.spj
             $where",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT
                ao.spj, ao.spj_fmt, ao.data_ocorrencia, ao.lavrado,
                ao.area_fato, ao.flagrante, ao.ato_infracional,
                ao.mpu_numero, ao.cnj_mpu,
                ao.cartorio_designado, ao.num_ip, ao.cartorio_ip, ao.cnj_ip_importado,
                ae.autoria, ae.vitimas_total, ae.vitimas_tipos_json, ae.delegacia_insercao
             FROM analise_ocorrencias ao
             LEFT JOIN analise_ocorrencias_extra ae ON ae.spj = ao.spj
             $where
             ORDER BY ao.spj_year DESC, ao.spj_seq DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $limit, ':offset' => $offset])
        );

        return ['total' => $total, 'rows' => $rows];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Detalhe de um BO
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retorna todos os dados de um BO por SPJ, incluindo naturezas, vítimas e autores.
     *
     * @return array{ocorrencia:array<string,mixed>|null, extra:array<string,mixed>|null,
     *               naturezas:array<int,array<string,mixed>>,
     *               vitimas:array<int,array<string,mixed>>,
     *               autores:array<int,array<string,mixed>>}|null
     */
    public function detalhe(string $spj): ?array
    {
        if (! $this->db->isAvailable()) {
            return null;
        }

        $spj = strtoupper(trim($spj));

        $ocorrencias = $this->db->fetchAll(
            'SELECT * FROM analise_ocorrencias WHERE spj = :spj LIMIT 1',
            [':spj' => $spj]
        );

        if (empty($ocorrencias)) {
            return null;
        }

        $extras = $this->db->fetchAll(
            'SELECT * FROM analise_ocorrencias_extra WHERE spj = :spj LIMIT 1',
            [':spj' => $spj]
        );

        $naturezas = $this->db->fetchAll(
            "SELECT natureza_label, tentado_consumado, slot
             FROM analise_naturezas
             WHERE spj = :spj AND trim(coalesce(natureza_label,'')) <> ''
             ORDER BY slot",
            [':spj' => $spj]
        );

        $vitimas = $this->db->fetchAll(
            "SELECT nome_upper, tipo, slot
             FROM analise_vitimas
             WHERE spj = :spj
             ORDER BY slot",
            [':spj' => $spj]
        );

        $autores = $this->db->fetchAll(
            "SELECT nome_upper, slot
             FROM analise_autores
             WHERE spj = :spj
             ORDER BY slot",
            [':spj' => $spj]
        );

        return [
            'ocorrencia' => $ocorrencias[0],
            'extra'      => $extras[0] ?? null,
            'naturezas'  => $naturezas,
            'vitimas'    => $vitimas,
            'autores'    => $autores,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pesquisa de pessoa (vítima ou autor)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Busca nominal em vítimas e autores.
     * Retorna BOs distintos que envolvem a pessoa pesquisada.
     *
     * @return array<int, array{spj:string,spj_fmt:string,data_ocorrencia:string,
     *                          area_fato:string,papel:string,nome:string}>
     */
    public function pesquisaPessoa(string $nome, int $limit = 40): array
    {
        if (! $this->db->isAvailable() || strlen(trim($nome)) < 3) {
            return [];
        }

        $key = '%' . strtolower(preg_replace('/\s+/', '%', trim($nome))) . '%';

        $vitimas = $this->db->fetchAll(
            "SELECT v.spj, o.spj_fmt, o.data_ocorrencia, o.area_fato,
                    'Vítima' AS papel, v.nome_upper AS nome
             FROM analise_vitimas v
             JOIN analise_ocorrencias o ON o.spj = v.spj
             WHERE v.nome_key LIKE :key
             ORDER BY o.spj_year DESC, o.spj_seq DESC
             LIMIT :limit",
            [':key' => $key, ':limit' => $limit]
        );

        $autores = $this->db->fetchAll(
            "SELECT a.spj, o.spj_fmt, o.data_ocorrencia, o.area_fato,
                    'Autor' AS papel, a.nome_upper AS nome
             FROM analise_autores a
             JOIN analise_ocorrencias o ON o.spj = a.spj
             WHERE a.nome_key LIKE :key
             ORDER BY o.spj_year DESC, o.spj_seq DESC
             LIMIT :limit",
            [':key' => $key, ':limit' => $limit]
        );

        // Mescla, ordena por ano/seq e limita
        $merged = array_merge($vitimas, $autores);
        usort($merged, fn ($a, $b) => $b['spj'] <=> $a['spj']);

        return array_slice($merged, 0, $limit);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internos
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  array{q?:string,area?:string,cartorio?:string,flagrante?:bool,com_mpu?:bool,com_ip?:bool,ano?:int}  $filters
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params  = [];

        if (!empty($filters['q'])) {
            $clauses[] = "(ao.spj LIKE :q OR ao.mpu_numero LIKE :q OR ao.num_ip LIKE :q)";
            $params[':q'] = '%' . trim($filters['q']) . '%';
        }
        if (!empty($filters['area'])) {
            $clauses[] = "ao.area_fato = :area";
            $params[':area'] = $filters['area'];
        }
        if (!empty($filters['cartorio'])) {
            $clauses[] = "ao.cartorio_ip = :cartorio";
            $params[':cartorio'] = $filters['cartorio'];
        }
        if (isset($filters['flagrante']) && $filters['flagrante']) {
            $clauses[] = "ao.flagrante = 1";
        }
        if (isset($filters['com_mpu']) && $filters['com_mpu']) {
            $clauses[] = "trim(coalesce(ao.mpu_numero,'')) <> ''";
        }
        if (isset($filters['com_ip']) && $filters['com_ip']) {
            $clauses[] = "trim(coalesce(ao.num_ip,'')) <> ''";
        }
        if (!empty($filters['ano'])) {
            $clauses[] = "ao.spj_year = :ano";
            $params[':ano'] = (int) $filters['ano'];
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return [$where, $params];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Estatísticas avançadas (painéis analíticos)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Evolução mensal de BOs (últimos N meses, ordem crescente para display).
     *
     * @return array<int, array{periodo:string,total:int,flagrantes:int}>
     */
    public function evolucaoPorMes(int $meses = 24): array
    {
        if (! $this->db->isAvailable()) {
            return [];
        }

        $rows = $this->db->fetchAll("
            SELECT
                strftime('%Y-%m', data_ocorrencia) AS periodo,
                COUNT(*)                           AS total,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes
            FROM analise_ocorrencias
            WHERE data_ocorrencia IS NOT NULL
              AND trim(data_ocorrencia) <> ''
            GROUP BY periodo
            ORDER BY periodo DESC
            LIMIT :limit
        ", [':limit' => $meses]);

        return array_reverse($rows);
    }

    /**
     * Evolução anual de BOs (pela coluna spj_year).
     *
     * @return array<int, array{ano:int,total:int,flagrantes:int,com_ip:int,com_mpu:int}>
     */
    public function evolucaoPorAno(): array
    {
        if (! $this->db->isAvailable()) {
            return [];
        }

        return $this->db->fetchAll("
            SELECT
                spj_year AS ano,
                COUNT(*) AS total,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes,
                SUM(CASE WHEN trim(coalesce(num_ip,''))     <> '' THEN 1 ELSE 0 END) AS com_ip,
                SUM(CASE WHEN trim(coalesce(mpu_numero,'')) <> '' THEN 1 ELSE 0 END) AS com_mpu
            FROM analise_ocorrencias
            WHERE spj_year > 0
            GROUP BY ano
            ORDER BY ano DESC
            LIMIT 10
        ");
    }

    /**
     * Distribuição de BOs por dia da semana (0=Domingo … 6=Sábado).
     *
     * @return array<int, array{dia:string,dia_num:int,total:int,flagrantes:int}>
     */
    public function porDiaSemana(): array
    {
        if (! $this->db->isAvailable()) {
            return [];
        }

        $diasNomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

        $rows = $this->db->fetchAll("
            SELECT
                CAST(strftime('%w', data_ocorrencia) AS INTEGER) AS dia_num,
                COUNT(*) AS total,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END) AS flagrantes
            FROM analise_ocorrencias
            WHERE data_ocorrencia IS NOT NULL AND trim(data_ocorrencia) <> ''
            GROUP BY dia_num
            ORDER BY dia_num
        ");

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(int) $row['dia_num']] = $row;
        }

        $result = [];
        for ($d = 0; $d <= 6; $d++) {
            $result[] = [
                'dia'        => $diasNomes[$d],
                'dia_num'    => $d,
                'total'      => (int) ($byDay[$d]['total']      ?? 0),
                'flagrantes' => (int) ($byDay[$d]['flagrantes'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Top naturezas com taxa de flagrante + breakdown tentado/consumado.
     *
     * @return array<int, array{natureza_label:string,total:int,flagrantes:int,tentado:int,consumado:int}>
     */
    public function topNaturezasCompleto(int $limit = 20): array
    {
        if (! $this->db->isAvailable()) {
            return [];
        }

        return $this->db->fetchAll("
            SELECT
                n.natureza_label,
                COUNT(DISTINCT n.spj)                                                       AS total,
                SUM(CASE WHEN o.flagrante = 1 THEN 1 ELSE 0 END)                           AS flagrantes,
                SUM(CASE WHEN n.tentado_consumado = 'Tentado'  THEN 1 ELSE 0 END)          AS tentado,
                SUM(CASE WHEN n.tentado_consumado = 'Consumado' THEN 1 ELSE 0 END)         AS consumado
            FROM analise_naturezas n
            JOIN analise_ocorrencias o ON o.spj = n.spj
            WHERE trim(coalesce(n.natureza_label,'')) <> ''
            GROUP BY n.natureza_label
            ORDER BY total DESC
            LIMIT :limit
        ", [':limit' => $limit]);
    }

    /**
     * Top naturezas dos BOs que são EXCLUSIVAMENTE flagrantes (flagrante = 1).
     *
     * @return array<int, array{natureza_label:string,total:int}>
     */
    public function topNaturezasFlagrante(int $limit = 20): array
    {
        if (! $this->db->isAvailable()) {
            return [];
        }

        return $this->db->fetchAll("
            SELECT
                n.natureza_label,
                COUNT(DISTINCT n.spj) AS total
            FROM analise_naturezas n
            JOIN analise_ocorrencias o ON o.spj = n.spj AND o.flagrante = 1
            WHERE trim(coalesce(n.natureza_label,'')) <> ''
            GROUP BY n.natureza_label
            ORDER BY total DESC
            LIMIT :limit
        ", [':limit' => $limit]);
    }

    /**
     * BOs por área do fato com taxa de flagrante e atos infracionais.
     *
     * @return array<int, array{area:string,total:int,flagrantes:int,atos_infracionais:int,com_ip:int}>
     */
    public function porAreaCompleta(): array
    {
        if (! $this->db->isAvailable()) {
            return [];
        }

        return $this->db->fetchAll("
            SELECT
                COALESCE(NULLIF(trim(area_fato), ''), 'Não informada') AS area,
                COUNT(*) AS total,
                SUM(CASE WHEN flagrante = 1 THEN 1 ELSE 0 END)             AS flagrantes,
                SUM(CASE WHEN ato_infracional = 1 THEN 1 ELSE 0 END)       AS atos_infracionais,
                SUM(CASE WHEN trim(coalesce(num_ip,'')) <> '' THEN 1 ELSE 0 END) AS com_ip
            FROM analise_ocorrencias
            GROUP BY area
            ORDER BY total DESC
        ");
    }

    /**
     * Distribuição por área do fato somente dos BOs que são flagrante (flagrante = 1).
     *
     * @return array<int, array{area:string,total:int,atos_infracionais:int,com_ip:int}>
     */
    public function porAreaFlagrante(): array
    {
        if (! $this->db->isAvailable()) {
            return [];
        }

        return $this->db->fetchAll("
            SELECT
                COALESCE(NULLIF(trim(area_fato), ''), 'Não informada') AS area,
                COUNT(*) AS total,
                SUM(CASE WHEN ato_infracional = 1 THEN 1 ELSE 0 END)       AS atos_infracionais,
                SUM(CASE WHEN trim(coalesce(num_ip,'')) <> '' THEN 1 ELSE 0 END) AS com_ip
            FROM analise_ocorrencias
            WHERE flagrante = 1
            GROUP BY area
            ORDER BY total DESC
        ");
    }

    /**
     * Tipos de vítimas mais frequentes, com contagem de BOs associados.
     *
     * @return array<int, array{tipo:string,total_vitimas:int,total_bos:int}>
     */
    public function tiposVitimas(int $limit = 10): array
    {
        if (! $this->db->isAvailable()) {
            return [];
        }

        return $this->db->fetchAll("
            SELECT
                COALESCE(NULLIF(trim(tipo), ''), 'Não informado') AS tipo,
                COUNT(*)            AS total_vitimas,
                COUNT(DISTINCT spj) AS total_bos
            FROM analise_vitimas
            GROUP BY tipo
            ORDER BY total_vitimas DESC
            LIMIT :limit
        ", [':limit' => $limit]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internos
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array<string,int> */
    private function countBy(string $table, string $column, int $limit = 10): array
    {
        $rows = $this->db->fetchAll(
            "SELECT COALESCE(NULLIF(trim($column),''), 'Não informado') AS label, COUNT(*) AS total
             FROM $table
             GROUP BY label
             ORDER BY total DESC
             LIMIT :limit",
            [':limit' => $limit]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['label']] = (int) $row['total'];
        }

        return $result;
    }

    /** @return array{total:int,com_mpu:int,com_ip:int,flagrantes:int,cartorios:array,areas:array} */
    private function emptySumario(): array
    {
        return [
            'total' => 0, 'com_mpu' => 0, 'com_ip' => 0,
            'flagrantes' => 0, 'cartorios' => [], 'areas' => [],
        ];
    }
}
