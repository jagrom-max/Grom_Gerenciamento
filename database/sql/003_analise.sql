-- =============================================================================
-- 003_analise.sql
-- Módulo: Análise de Dados — Boletins de Ocorrência (BOs) Unificados
-- Engloba: flagrantes, não-flagrantes, termos circunstanciados e rastreamento
--          de MPU (Medida Protetiva de Urgência) sem Inquérito Policial.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 1. Tabela principal: analise_bos
--    Consolida TODOS os BOs independente de tipo ou origem.
--    Substitui analise_ocorrencias do legado e estende produtividade_flagrantes
--    para tipos não-flagrante, mantendo FK para import_items.
-- ---------------------------------------------------------------------------
create table if not exists grom.analise_bos (
    id                  uuid        primary key default gen_random_uuid(),
    cartorio_id         uuid        references grom.cartorios(id) on delete restrict,
    source_item_id      uuid        unique references grom.import_items(id) on delete set null,

    -- Identificação do BO
    num_bo              text,
    num_rai             text,
    tipo_ocorrencia     text        not null,

    -- Conteúdo
    naturezas           text,
    local_fato          text,
    data_fato           date,
    data_registro       date,

    -- Vínculos com investigação
    num_spj             text,
    num_ip              text,
    num_ipe             text,
    num_cnj             text,

    -- MPU — Medida Protetiva de Urgência
    tem_mpu             boolean     not null default false,
    num_mpu             text,
    mpu_status          text,
    -- Flag crítico: MPU deferida sem IP instaurado — exige acompanhamento urgente
    mpu_sem_ip          boolean     not null default false,
    mpu_obs             text,

    -- Origem / unidade
    lavrado_unidade     text        not null default 'OUTRAS_UNIDADES',

    -- Status do BO
    status_bo           text        not null default 'ABERTO',

    -- Controle
    manually_entered    boolean     not null default false,
    is_active           boolean     not null default true,
    confirmed_by        uuid        references grom.app_users(id),
    confirmed_at        timestamptz,
    notes               text,
    created_at          timestamptz not null default now(),
    updated_at          timestamptz not null default now(),

    -- Constraints
    constraint ck_bos_tipo check (
        tipo_ocorrencia in ('FLAGRANTE', 'NAO_FLAGRANTE', 'TERMO_CIRCUNSTANCIADO')
    ),
    constraint ck_bos_unidade check (
        lavrado_unidade in ('DDM', 'OUTRAS_UNIDADES')
    ),
    constraint ck_bos_status check (
        status_bo in ('ABERTO', 'ARQUIVADO', 'CONVERTIDO_IP', 'ENCERRADO')
    ),
    constraint ck_bos_mpu_status check (
        mpu_status is null
        or mpu_status in ('PENDENTE', 'DEFERIDA', 'INDEFERIDA', 'CUMPRIDA', 'REVOGADA')
    ),
    -- MPU sem IP só pode ser verdadeiro quando tem_mpu = true
    constraint ck_bos_mpu_sem_ip check (
        not mpu_sem_ip or tem_mpu
    )
);

-- Índices principais
create index if not exists idx_bos_cartorio_data
    on grom.analise_bos (cartorio_id, data_fato desc);

create index if not exists idx_bos_tipo_status
    on grom.analise_bos (tipo_ocorrencia, status_bo);

create index if not exists idx_bos_mpu_sem_ip
    on grom.analise_bos (mpu_sem_ip, is_active)
    where mpu_sem_ip = true and is_active = true;

create index if not exists idx_bos_num_ip
    on grom.analise_bos (lower(num_ip))
    where num_ip is not null and btrim(num_ip) <> '';

-- Unicidade por cartório + número do BO
create unique index if not exists ux_bos_cartorio_num_bo
    on grom.analise_bos (cartorio_id, lower(num_bo))
    where num_bo is not null and btrim(num_bo) <> '';

-- Trigger de atualização de timestamp
create trigger trg_analise_bos_touch
    before update on grom.analise_bos
    for each row execute function grom.touch_updated_at();

-- ---------------------------------------------------------------------------
-- 2. Histórico de mudança de status do BO
-- ---------------------------------------------------------------------------
create table if not exists grom.analise_bos_status_hist (
    id              bigserial   primary key,
    bo_id           uuid        not null references grom.analise_bos(id) on delete cascade,
    status_anterior text,
    status_novo     text        not null,
    motivo          text,
    changed_by      uuid        references grom.app_users(id),
    changed_at      timestamptz not null default now()
);

create index if not exists idx_bos_status_hist_bo
    on grom.analise_bos_status_hist (bo_id, changed_at desc);

-- ---------------------------------------------------------------------------
-- 3. Histórico de MPU vinculado ao BO
--    Permite rastrear múltiplos pedidos de MPU dentro de um mesmo BO.
-- ---------------------------------------------------------------------------
create table if not exists grom.analise_bos_mpus (
    id              uuid        primary key default gen_random_uuid(),
    bo_id           uuid        not null references grom.analise_bos(id) on delete cascade,
    num_mpu         text,
    status_mpu      text        not null default 'PENDENTE',
    data_pedido     date,
    data_decisao    date,
    tem_ip_vinculado boolean    not null default false,
    num_ip_vinculado text,
    obs             text,
    created_by      uuid        references grom.app_users(id),
    created_at      timestamptz not null default now(),
    updated_at      timestamptz not null default now(),

    constraint ck_mpus_status check (
        status_mpu in ('PENDENTE', 'DEFERIDA', 'INDEFERIDA', 'CUMPRIDA', 'REVOGADA')
    )
);

create index if not exists idx_bos_mpus_sem_ip
    on grom.analise_bos_mpus (bo_id)
    where status_mpu = 'DEFERIDA' and not tem_ip_vinculado;

create trigger trg_analise_bos_mpus_touch
    before update on grom.analise_bos_mpus
    for each row execute function grom.touch_updated_at();

-- ---------------------------------------------------------------------------
-- 4. Tabela de changelog de consolidação analítica
--    Registra toda alteração significativa em analise_bos para rastreabilidade.
-- ---------------------------------------------------------------------------
create table if not exists grom.analise_change_log (
    id              bigserial   primary key,
    bo_id           uuid        not null references grom.analise_bos(id) on delete cascade,
    field_name      text        not null,
    old_value       text,
    new_value       text,
    changed_by      uuid        references grom.app_users(id),
    changed_at      timestamptz not null default now()
);

create index if not exists idx_analise_change_log_bo
    on grom.analise_change_log (bo_id, changed_at desc);

-- ---------------------------------------------------------------------------
-- 5. Views
-- ---------------------------------------------------------------------------

-- 5a. Flagrantes — compatibilidade com módulo de Produtividade
create or replace view grom.v_analise_flagrantes as
select
    b.id,
    b.cartorio_id,
    b.num_bo,
    b.num_spj         as spj,
    b.num_ip,
    b.num_ipe,
    b.num_cnj,
    b.naturezas,
    b.data_fato,
    b.lavrado_unidade,
    b.status_bo,
    b.is_active,
    b.confirmed_by,
    b.confirmed_at,
    b.notes,
    b.created_at,
    b.updated_at
from grom.analise_bos b
where b.tipo_ocorrencia = 'FLAGRANTE'
  and b.is_active = true;

-- 5b. Não-flagrantes ativos
create or replace view grom.v_analise_nao_flagrantes as
select
    b.id,
    b.cartorio_id,
    b.num_bo,
    b.num_rai,
    b.naturezas,
    b.local_fato,
    b.data_fato,
    b.data_registro,
    b.num_ip,
    b.tem_mpu,
    b.num_mpu,
    b.mpu_status,
    b.mpu_sem_ip,
    b.status_bo,
    b.is_active,
    b.created_at
from grom.analise_bos b
where b.tipo_ocorrencia = 'NAO_FLAGRANTE'
  and b.is_active = true;

-- 5c. CRÍTICOS: MPU deferida sem IP instaurado
--    Esta view alimenta o painel de pendências críticas.
create or replace view grom.v_mpu_sem_ip_criticos as
select
    b.id                            as bo_id,
    b.cartorio_id,
    c.code                          as cartorio_code,
    c.name                          as cartorio_nome,
    b.num_bo,
    b.num_rai,
    b.naturezas,
    b.data_fato,
    b.data_registro,
    b.num_mpu,
    b.mpu_status,
    b.mpu_obs,
    b.lavrado_unidade,
    b.status_bo,
    b.notes,
    b.created_at,
    -- Dias sem IP desde o registro do BO
    (current_date - b.data_registro)::integer as dias_sem_ip
from grom.analise_bos b
join grom.cartorios c on c.id = b.cartorio_id
where b.mpu_sem_ip   = true
  and b.is_active    = true
  and b.status_bo    not in ('ARQUIVADO', 'ENCERRADO')
order by b.data_registro asc; -- mais antigos primeiro = mais urgentes

-- 5d. Rollup mensal de BOs por cartório
create or replace view grom.v_bos_rollup_monthly as
select
    b.cartorio_id,
    extract(year  from b.data_fato)::integer as reference_year,
    extract(month from b.data_fato)::integer as reference_month,
    count(*)                                                              as bos_total,
    sum(case when b.tipo_ocorrencia = 'FLAGRANTE'           then 1 else 0 end)::integer as flagrantes_total,
    sum(case when b.tipo_ocorrencia = 'NAO_FLAGRANTE'       then 1 else 0 end)::integer as nao_flagrantes_total,
    sum(case when b.tipo_ocorrencia = 'TERMO_CIRCUNSTANCIADO' then 1 else 0 end)::integer as tcs_total,
    sum(case when b.lavrado_unidade = 'DDM'                 then 1 else 0 end)::integer as ddm_total,
    sum(case when b.lavrado_unidade = 'OUTRAS_UNIDADES'     then 1 else 0 end)::integer as outras_total,
    sum(case when b.tem_mpu                                 then 1 else 0 end)::integer as com_mpu,
    sum(case when b.mpu_sem_ip                              then 1 else 0 end)::integer as mpu_sem_ip_criticos
from grom.analise_bos b
where b.is_active = true
  and b.data_fato is not null
group by
    b.cartorio_id,
    extract(year  from b.data_fato),
    extract(month from b.data_fato);

-- 5e. Resumo de BOs por cartório (corrente e acumulado)
create or replace view grom.v_bos_resumo_cartorio as
select
    c.id                            as cartorio_id,
    c.code                          as cartorio_code,
    c.name                          as cartorio_nome,
    count(b.id)                     as bos_total,
    sum(case when b.tipo_ocorrencia = 'FLAGRANTE'     then 1 else 0 end)::integer as flagrantes,
    sum(case when b.tipo_ocorrencia = 'NAO_FLAGRANTE' then 1 else 0 end)::integer as nao_flagrantes,
    sum(case when b.tipo_ocorrencia = 'TERMO_CIRCUNSTANCIADO' then 1 else 0 end)::integer as tcs,
    sum(case when b.tem_mpu                           then 1 else 0 end)::integer as com_mpu,
    sum(case when b.mpu_sem_ip                        then 1 else 0 end)::integer as mpu_criticos,
    sum(case when b.status_bo = 'ABERTO'              then 1 else 0 end)::integer as em_aberto,
    sum(case when b.status_bo = 'CONVERTIDO_IP'       then 1 else 0 end)::integer as convertidos_ip
from grom.cartorios c
left join grom.analise_bos b
    on b.cartorio_id = c.id
   and b.is_active   = true
where c.is_active = true
group by c.id, c.code, c.name
order by mpu_criticos desc, bos_total desc;
