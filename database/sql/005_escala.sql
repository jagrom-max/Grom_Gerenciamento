-- =============================================================================
-- 005_escala.sql
-- Módulo: Escalas
-- =============================================================================
-- 005_escala.sql
-- Módulo: Escalas
-- Espelho fiel do sistema Python legado (escala.py / modulo_escalas/).
--
-- Tabelas:
--   escalas_plantoes_externos       — catálogo de tipos de plantão externo
--   escalas_plantoes_funcionarios   — plantões lançados por funcionário/data
--   escalas_mensal                  — escala gerada (uma linha por dia × versão)
--
-- Fluxo:
--   1. Lançar plantões externos do mês (funcionário + tipo + data(s))
--   2. Gerar escala provisória — sistema considera plantões externos +
--      afastamentos (rh_afastamentos) + equidade e demais regras
--   3. Conferir na tela e/ou imprimir
--   4. Fazer ajustes pontuais (edição direta)
--   5. Gerar escala definitiva (salvar versão v1, v2, ...)
--
-- Delegado(a) titular impedido(a): campo "delegada" recebe o nome do
--   Delegado Externo selecionado no combobox (pool em rh_delegados_externos).
--   O nome é gravado como texto simples, igual ao legado Python.
--   O período de impedimento consta nas observações do mês.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 1. Catálogo de plantões externos (tipos)
--    Cada tipo tem uma sigla (ex.: PLD, PLN, CADD) e uma regra que determina
--    quais dias o funcionário fica bloqueado para a escala interna.
--
--    Regras (campo: regra):
--      MESMO_DIA    → bloqueia apenas o dia do plantão
--      DIA_SEGUINTE → bloqueia apenas o dia seguinte ao plantão
--      AMBOS        → bloqueia o dia do plantão e o seguinte
--
--    Exceção: PLD não bloqueia funcionário com cargo DEL.
--    Essa regra é aplicada na camada de aplicação.
-- ---------------------------------------------------------------------------
if not exists (select * from sys.objects where object_id = object_id(N'grom.escalas_plantoes_externos') and type in (N'U'))
create table grom.escalas_plantoes_externos (
    id          uniqueidentifier primary key default NEWID(),
    nome        varchar(80) not null,
    sigla       varchar(20) not null,
    regra       varchar(20) not null,
    unidade     varchar(80),
    obs         text,
    is_active   bit         not null default 1,
    legacy_id   int         unique,
    created_at  datetime2   not null default sysutcdatetime(),
    updated_at  datetime2   not null default sysutcdatetime(),

    constraint uq_escalas_pe_sigla unique (sigla),
    constraint ck_escalas_pe_regra check (regra in ('MESMO_DIA', 'DIA_SEGUINTE', 'AMBOS'))
);

-- Seed: tipos do sistema legado (sigla e regra conforme escala_bloqueios.py)
if not exists (select 1 from grom.escalas_plantoes_externos where sigla = 'CADD')
    insert into grom.escalas_plantoes_externos (sigla, nome, regra) values ('CADD',    'Cartório Adicional de Dia',    'MESMO_DIA');
if not exists (select 1 from grom.escalas_plantoes_externos where sigla = 'CADN')
    insert into grom.escalas_plantoes_externos (sigla, nome, regra) values ('CADN',    'Cartório Adicional de Noite',  'AMBOS');
if not exists (select 1 from grom.escalas_plantoes_externos where sigla = 'DDM24H')
    insert into grom.escalas_plantoes_externos (sigla, nome, regra) values ('DDM24H',  'DDM 24 horas',                 'AMBOS');
if not exists (select 1 from grom.escalas_plantoes_externos where sigla = 'ESCOLTA')
    insert into grom.escalas_plantoes_externos (sigla, nome, regra) values ('ESCOLTA', 'Escolta',                       'MESMO_DIA');
if not exists (select 1 from grom.escalas_plantoes_externos where sigla = 'PLD')
    insert into grom.escalas_plantoes_externos (sigla, nome, regra) values ('PLD',     'Plantão de Dia',               'MESMO_DIA');
if not exists (select 1 from grom.escalas_plantoes_externos where sigla = 'PLN')
    insert into grom.escalas_plantoes_externos (sigla, nome, regra) values ('PLN',     'Plantão de Noite',             'AMBOS');
if not exists (select 1 from grom.escalas_plantoes_externos where sigla = 'RD')
    insert into grom.escalas_plantoes_externos (sigla, nome, regra) values ('RD',      'Regime de Dia',                'MESMO_DIA');
if not exists (select 1 from grom.escalas_plantoes_externos where sigla = 'RN')
    insert into grom.escalas_plantoes_externos (sigla, nome, regra) values ('RN',      'Regime de Noite',              'DIA_SEGUINTE');

create trigger trg_escalas_plantoes_externos_touch
    before update on grom.escalas_plantoes_externos
    for each row execute function grom.touch_updated_at();

-- ---------------------------------------------------------------------------
-- 2. Plantões externos por funcionário/data
--    Registro de quais funcionários realizaram plantão externo em cada data.
--    O sistema usa esses dados para montar os bloqueios ao gerar a escala.
--    Inclui plantões do último dia do mês anterior (para cálculo do dia 1).
if not exists (select * from sys.objects where object_id = object_id(N'grom.escalas_plantoes_funcionarios') and type in (N'U'))
create table if not exists grom.escalas_plantoes_funcionarios (
    id                  bigint      primary key identity(1,1),
    data                date        not null,
    funcionario_id      uniqueidentifier not null references grom.rh_funcionarios(id),
    plantao_externo_id  uniqueidentifier not null references grom.escalas_plantoes_externos(id),
    legacy_id           int         unique,
    created_by          uniqueidentifier,
    created_at          datetime2   not null default sysutcdatetime(),

    constraint uq_plantao_func_data unique (funcionario_id, plantao_externo_id, data)
);

create index idx_plant_func_data on grom.escalas_plantoes_funcionarios (data);

create index idx_plant_func_fid on grom.escalas_plantoes_funcionarios (funcionario_id);

-- ---------------------------------------------------------------------------
-- 3. Escala mensal — uma linha por dia × versão
--    Espelho fiel do legado Python (tabela escala_mensal do SQLite).
--
--    Versioning: cada vez que o operador salva a definitiva, incrementa versão.
--    A versão mais alta é a vigente. Não há coluna de status separada.
--
--    Nomes gravados como snapshot de texto (campo livre), igual ao legado.
--    delegada: nome do Delegado interno OU do Externo selecionado no combobox.
--    plantao_externo: texto composto "Nome (SIGLA), Nome2 (SIGLA2)".
--
--    Finais de semana: todos os campos de nome ficam NULL/vazio.
--    Feriados: campo operacional = 'FERIADO' ou 'PONTO FACULTATIVO'.
-- ---------------------------------------------------------------------------
if not exists (select * from sys.objects where object_id = object_id(N'grom.escalas_mensal') and type in (N'U'))
create table grom.escalas_mensal (
    id              uniqueidentifier primary key default NEWID(),
    data            date        not null,
    mes             smallint    not null,
    ano             smallint    not null,
    versao          smallint    not null default 1,

    -- Snapshots de nome (texto livre — compatível com legado Python)
    escrivao        varchar(100),
    operacional     varchar(100),
    fechar          varchar(100),
    delegada        varchar(100),
    plantao_externo text,           -- ex.: "Laura (PLD), Marina (PLN)"

    -- Auditoria
    legacy_id       integer,
    created_by      uniqueidentifier,
    updated_by      uniqueidentifier,
    created_at      datetime2 not null default sysutcdatetime(),
    updated_at      datetime2 not null default sysutcdatetime(),

    constraint uq_escalas_mensal_data_versao unique (data, versao),
    constraint ck_escalas_mensal_mes check (mes between 1 and 12),
    constraint ck_escalas_mensal_ano check (ano between 2020 and 2100),
    constraint ck_escalas_mensal_versao check (versao >= 1)
);

create index idx_escalas_mensal_ano_mes_versao on grom.escalas_mensal (ano, mes, versao);


-- Trigger deve ser criada em T-SQL se necessário

-- =============================================================================
-- VIEWS
-- =============================================================================

-- Última versão de cada mês (para carregar a escala vigente por padrão)
create or replace view grom.v_escala_ultima_versao as
select distinct on (ano, mes)
    ano,
    mes,
    versao
from grom.escalas_mensal
order by ano, mes, versao desc;

-- Plantões do mês com nome do funcionário (base dos bloqueios e da exibição)
create or replace view grom.v_escala_plantoes_mes as
select
    pf.data,
    coalesce(f.nome_simplificado, f.nome_completo) as nome_exib,
    f.nome_completo,
    pe.sigla,
    pe.regra,
    pe.nome                                         as tipo_nome,
    pf.funcionario_id,
    pf.plantao_externo_id,
    extract(year  from pf.data)::smallint           as ano,
    extract(month from pf.data)::smallint           as mes
from grom.escalas_plantoes_funcionarios pf
join grom.rh_funcionarios           f  on f.id = pf.funcionario_id  and f.is_active
join grom.escalas_plantoes_externos pe on pe.id = pf.plantao_externo_id
order by pf.data, f.nome_completo;
