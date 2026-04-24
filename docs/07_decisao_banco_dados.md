# Decisao de Banco de Dados

## Escolha oficial

Banco canonico do novo GROM Web:
- `PostgreSQL`

Bancos avaliados como alternativas viaveis:
- `MySQL`
- `MariaDB`

## Resultado da avaliacao

Para o contexto do GROM, `PostgreSQL` oferece o melhor equilibrio entre:
- consistencia transacional;
- concorrencia multiusuario;
- trilha de auditoria;
- consultas analiticas e relatorios;
- capacidade de crescer com seguranca por muitos anos.

## Por que nao manter SQLite

O SQLite foi util no desktop, mas nao deve ser o banco permanente da plataforma web porque:
- sofre mais com lock em ambiente multiusuario;
- nao e o melhor ajuste para fila, sessao e cache concorrentes;
- dificulta uma estrategia profissional de backup, restore e alta confiabilidade;
- tende a reproduzir gargalos que queremos eliminar.

## Comparativo objetivo

### PostgreSQL

Pontos fortes para o GROM:
- excelente suporte a integridade, constraints e modelagem rica;
- bom caminho para auditoria, historico, visoes e consolidacoes;
- JSONB, indices parciais e consultas flexiveis ajudam muito na Analise de Dados;
- estrategia madura de backup, replicacao e PITR;
- forte aderencia a relatorios, filtros por periodo e investigacoes historicas.

Pontos de atencao:
- exige operacao um pouco mais disciplinada que SQLite;
- precisa de administracao minima de ambiente e backup.

### MySQL

Pontos fortes:
- ecossistema amplo;
- hospedagem facil;
- bom desempenho geral para CRUD.

Pontos de atencao para este projeto:
- menor aderencia nativa a alguns padroes analiticos e de governanca que queremos explorar no longo prazo;
- eu precisaria criar mais adaptacoes para chegar no mesmo conforto de modelagem e auditoria previsto com PostgreSQL.

### MariaDB

Pontos fortes:
- leveza operacional;
- boa compatibilidade com parte do ecossistema MySQL.

Pontos de atencao para este projeto:
- eu prefiro evitar divergencias de compatibilidade ao longo dos anos;
- nao traz vantagem decisiva sobre PostgreSQL no nosso contexto.

## Regra pratica daqui para frente

- schema canonico: `PostgreSQL`
- stack alvo: `PostgreSQL + Redis + Laravel + Nginx`
- local de desenvolvimento: `SQLite` apenas quando for conveniente
- corte do Python: somente depois de homologacao no ambiente PostgreSQL
