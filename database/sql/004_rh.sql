-- =============================================================================
-- 004_rh.sql
-- Módulo: RH — Recursos Humanos
-- Engloba: cargos, funcionários, tipos de afastamento, afastamentos,
--          delegados externos (pool) e atribuições dia-a-dia na escala.
--
-- Hipóteses de afastamento (definidas pelo GROM):
--   FERIAS · LIC_PREMIO · LIC_SAUDE · CURSO · FOLGA · OUTROS
--   Curso, Folga e Outros exigem campo fundamentacao preenchido.
--
-- Delegado externo: pool de Delegados(as) disponíveis para cobrir impedimentos
--   do(a) Delegado(a) titular da DDM. Quando o titular estiver impedido(a), a
--   escala mensal exibe combobox com o pool para atribuição manual dia a dia.
--   Sem fundamentação na atribuição. O período de impedimento consta nas
--   observações do mês da escala.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 1. Cargos
-- ---------------------------------------------------------------------------
create table if not exists grom.rh_cargos (
    id          uuid        primary key default gen_random_uuid(),
    codigo      text        not null unique,
    nome        text        not null,
    descricao   text,
    nivel       text        not null default 'OPERACIONAL',
    is_active   boolean     not null default true,
    created_at  timestamptz not null default now(),
    updated_at  timestamptz not null default now(),

    constraint ck_cargo_nivel check (
        nivel in ('DIRECAO', 'MEDIO', 'OPERACIONAL', 'APOIO')
    )
);

create trigger trg_rh_cargos_touch
    before update on grom.rh_cargos
    for each row execute function grom.touch_updated_at();

insert into grom.rh_cargos (codigo, nome, nivel) values
    ('DEL',  'Delegado de Polícia',            'DIRECAO'),
    ('INV',  'Investigador de Polícia',         'OPERACIONAL'),
    ('ESC',  'Escrivão de Polícia',             'OPERACIONAL'),
    ('AGP',  'Agente de Polícia',               'OPERACIONAL'),
    ('PER',  'Perito Criminal',                 'MEDIO'),
    ('ADM',  'Auxiliar Administrativo',         'APOIO'),
    ('EST',  'Estagiário',                      'APOIO')
on conflict (codigo) do nothing;

-- ---------------------------------------------------------------------------
-- 2. Funcionários
-- ---------------------------------------------------------------------------
create table if not exists grom.rh_funcionarios (
    id              uuid        primary key default gen_random_uuid(),
    matricula       text        not null unique,
    nome_completo   text        not null,
    nome_social     text,
    cargo_id        uuid        references grom.rh_cargos(id) on delete restrict,

    -- Contato
    email           citext,
    telefone        text,

    -- Vínculo funcional
    data_ingresso   date,
    data_saida      date,
    situacao        text        not null default 'ATIVO',

    -- Lotação
    cartorio_id     uuid        references grom.cartorios(id) on delete set null,
    turno           text,

    -- Controle
    is_active       boolean     not null default true,
    notes           text,
    created_by      uuid        references grom.app_users(id),
    created_at      timestamptz not null default now(),
    updated_at      timestamptz not null default now(),

    constraint ck_func_situacao check (
        situacao in ('ATIVO', 'AFASTADO', 'EXONERADO', 'APOSENTADO', 'CEDIDO')
    ),
    constraint ck_func_turno check (
        turno is null
        or turno in ('DIURNO', 'NOTURNO', 'ADMINISTRATIVO', 'PLANTAO')
    ),
    constraint ck_func_datas check (
        data_saida is null or data_saida >= data_ingresso
    )
);

create index if not exists idx_rh_func_cartorio
    on grom.rh_funcionarios (cartorio_id)
    where is_active = true;

create index if not exists idx_rh_func_situacao
    on grom.rh_funcionarios (situacao)
    where is_active = true;

create trigger trg_rh_funcionarios_touch
    before update on grom.rh_funcionarios
    for each row execute function grom.touch_updated_at();

-- ---------------------------------------------------------------------------
-- 3. Tipos de afastamento
--    Exatamente as hipóteses reconhecidas pelo GROM.
--    exige_fundamentacao = true → campo fundamentacao obrigatório no registro.
-- ---------------------------------------------------------------------------
create table if not exists grom.rh_tipos_afastamento (
    id                    uuid        primary key default gen_random_uuid(),
    codigo                text        not null unique,
    nome                  text        not null,
    descricao             text,
    afeta_escala          boolean     not null default true,
    exige_fundamentacao   boolean     not null default false,
    created_at            timestamptz not null default now()
);

-- Férias, Licença Prêmio e Licença Saúde: sem fundamentacao obrigatória.
-- Curso, Folga e Outros: fundamentacao obrigatória (exige_fundamentacao = true).
insert into grom.rh_tipos_afastamento (codigo, nome, afeta_escala, exige_fundamentacao) values
    ('FERIAS',      'Férias',            true,  false),
    ('LIC_PREMIO',  'Licença Prêmio',    true,  false),
    ('LIC_SAUDE',   'Licença Saúde',     true,  false),
    ('CURSO',       'Curso',             true,  true),
    ('FOLGA',       'Folga',             true,  true),
    ('OUTROS',      'Outros',            true,  true)
on conflict (codigo) do nothing;

-- ---------------------------------------------------------------------------
-- 4. Afastamentos
-- ---------------------------------------------------------------------------
create table if not exists grom.rh_afastamentos (
    id                  uuid        primary key default gen_random_uuid(),
    funcionario_id      uuid        not null references grom.rh_funcionarios(id) on delete restrict,
    tipo_id             uuid        not null references grom.rh_tipos_afastamento(id),

    data_inicio         date        not null,
    data_fim            date,
    dias_previstos      integer,
    prorrogado          boolean     not null default false,

    -- Para tipos com exige_fundamentacao = true (Curso, Folga, Outros):
    -- fundamentacao é obrigatória e deve ser validada na camada de aplicação.
    fundamentacao       text,
    documento_ref       text,

    -- Aprovação
    aprovado_por        uuid        references grom.app_users(id),
    aprovado_em         timestamptz,

    -- Controle
    created_by          uuid        references grom.app_users(id),
    notes               text,
    created_at          timestamptz not null default now(),
    updated_at          timestamptz not null default now(),

    constraint ck_afas_datas check (
        data_fim is null or data_fim >= data_inicio
    ),
    constraint ck_afas_dias check (
        dias_previstos is null or dias_previstos > 0
    )
);

create index if not exists idx_rh_afas_funcionario
    on grom.rh_afastamentos (funcionario_id, data_inicio desc);

create index if not exists idx_rh_afas_periodo
    on grom.rh_afastamentos (data_inicio, data_fim);

create trigger trg_rh_afastamentos_touch
    before update on grom.rh_afastamentos
    for each row execute function grom.touch_updated_at();

-- ---------------------------------------------------------------------------
-- 5. Delegados externos — pool de disponíveis para cobertura da DDM
--
--    Cadastro de Delegados(as) externos(as) disponíveis para assumir a DDM
--    quando o(a) Delegado(a) titular estiver impedido(a). Não há atribuição
--    de período aqui — o vínculo dia a dia é feito em
--    rh_escala_delegado_substituto ao gerar/editar a escala mensal.
-- ---------------------------------------------------------------------------
create table if not exists grom.rh_delegados_externos (
    id                      uuid        primary key default gen_random_uuid(),

    -- Identificação do delegado externo
    nome                    text        not null,
    matricula               text,
    orgao_origem            text        not null,

    -- Cartório DDM ao qual este delegado está vinculado como opção de cobertura
    cartorio_id             uuid        not null references grom.cartorios(id) on delete restrict,

    -- Base legal do credenciamento (portaria, etc.)
    ato_legal               text,
    obs                     text,

    -- Pool ativo/inativo (soft-disable sem excluir histórico)
    is_active               boolean     not null default true,

    created_by              uuid        references grom.app_users(id),
    created_at              timestamptz not null default now(),
    updated_at              timestamptz not null default now()
);

create index if not exists idx_rh_deleg_ext_cartorio
    on grom.rh_delegados_externos (cartorio_id)
    where is_active = true;

create trigger trg_rh_delegados_externos_touch
    before update on grom.rh_delegados_externos
    for each row execute function grom.touch_updated_at();

-- ---------------------------------------------------------------------------
-- 6. Atribuições de substituto na escala — dia a dia
--
--    REGRA: aplica-se EXCLUSIVAMENTE ao cargo DEL (Delegado de Polícia).
--    Nenhum outro cargo usa este mecanismo de substituição.
--
--    Quando a escala mensal detecta que o(a) Delegado(a) titular está
--    impedido(a) num determinado dia, o campo daquele dia na grade exibe
--    um combobox com o pool de delegados externos (rh_delegados_externos)
--    para atribuição manual. Nenhum substituto é atribuído automaticamente.
--
--    O período de impedimento é registrado nas observações do mês da escala
--    (texto gerado pelo sistema a partir do afastamento: "De DD/MM a DD/MM,
--    [Nome] estará de [Tipo]"). Não há campo de fundamentação na atribuição.
-- ---------------------------------------------------------------------------
create table if not exists grom.rh_escala_delegado_substituto (
    id                      uuid        primary key default gen_random_uuid(),

    cartorio_id             uuid        not null references grom.cartorios(id) on delete restrict,
    data_dia                date        not null,

    -- Titular impedido neste dia — DEVE ter cargo DEL (validado por trigger)
    titular_funcionario_id  uuid        not null references grom.rh_funcionarios(id) on delete restrict,

    -- Delegado externo escolhido manualmente pelo operador (do pool do cartório)
    delegado_externo_id     uuid        not null references grom.rh_delegados_externos(id) on delete restrict,

    -- Afastamento que originou o impedimento (opcional — cobre casos como
    -- plantão noturno em outra unidade, sem afastamento formal registrado)
    afastamento_id          uuid        references grom.rh_afastamentos(id) on delete set null,

    created_by              uuid        references grom.app_users(id),
    created_at              timestamptz not null default now(),
    updated_at              timestamptz not null default now(),

    -- Um substituto por dia por cartório
    constraint uq_escala_subst_dia unique (cartorio_id, data_dia)
);

-- Trigger: garante que apenas funcionários com cargo DEL podem ter substituto
create or replace function grom.chk_escala_subst_cargo_del()
returns trigger language plpgsql as $$
declare
    v_cargo text;
begin
    select c.codigo into v_cargo
    from grom.rh_funcionarios f
    join grom.rh_cargos c on c.id = f.cargo_id
    where f.id = new.titular_funcionario_id;

    if v_cargo is distinct from 'DEL' then
        raise exception
            'Substituição de delegado(a) só é permitida para cargo DEL. Cargo encontrado: %',
            coalesce(v_cargo, 'sem cargo');
    end if;
    return new;
end;
$$;

create trigger trg_escala_subst_cargo_del
    before insert or update on grom.rh_escala_delegado_substituto
    for each row execute function grom.chk_escala_subst_cargo_del();

create index if not exists idx_rh_escala_subst_cartorio_mes
    on grom.rh_escala_delegado_substituto (cartorio_id, data_dia desc);

create index if not exists idx_rh_escala_subst_externo
    on grom.rh_escala_delegado_substituto (delegado_externo_id);

create index if not exists idx_rh_escala_subst_titular
    on grom.rh_escala_delegado_substituto (titular_funcionario_id);

create trigger trg_rh_escala_subst_touch
    before update on grom.rh_escala_delegado_substituto
    for each row execute function grom.touch_updated_at();

-- ---------------------------------------------------------------------------
-- 6. Views de relatório
-- ---------------------------------------------------------------------------

-- 6a. Funcionários ativos com cargo e cartório
create or replace view grom.v_rh_funcionarios_ativos as
select
    f.id,
    f.matricula,
    f.nome_completo,
    f.nome_social,
    f.situacao,
    f.turno,
    f.email,
    f.telefone,
    f.data_ingresso,
    c.codigo        as cargo_codigo,
    c.nome          as cargo_nome,
    c.nivel         as cargo_nivel,
    ct.code         as cartorio_code,
    ct.name         as cartorio_nome
from grom.rh_funcionarios f
left join grom.rh_cargos   c  on c.id  = f.cargo_id
left join grom.cartorios   ct on ct.id = f.cartorio_id
where f.is_active = true
  and f.situacao  = 'ATIVO'
order by ct.number, c.nivel, f.nome_completo;

-- 6b. Afastamentos em andamento (data_fim nula ou futura)
create or replace view grom.v_rh_afastamentos_ativos as
select
    a.id,
    f.matricula,
    f.nome_completo,
    f.nome_social,
    ct.code                                     as cartorio_code,
    ct.name                                     as cartorio_nome,
    ta.codigo                                   as tipo_codigo,
    ta.nome                                     as tipo_nome,
    ta.exige_fundamentacao,
    a.data_inicio,
    a.data_fim,
    a.dias_previstos,
    a.prorrogado,
    (current_date - a.data_inicio)::integer     as dias_decorridos,
    a.fundamentacao,
    a.documento_ref,
    a.notes
from grom.rh_afastamentos a
join grom.rh_funcionarios f  on f.id  = a.funcionario_id
join grom.rh_tipos_afastamento ta on ta.id = a.tipo_id
left join grom.cartorios ct on ct.id = f.cartorio_id
where (a.data_fim is null or a.data_fim >= current_date)
order by a.data_inicio asc;

-- 6c. Consolidado de afastamentos por funcionário (período corrente = ano atual)
create or replace view grom.v_rh_afastamentos_consolidado_ano as
select
    f.id                                        as funcionario_id,
    f.matricula,
    f.nome_completo,
    ct.code                                     as cartorio_code,
    extract(year from a.data_inicio)::integer   as reference_year,
    ta.codigo                                   as tipo_codigo,
    ta.nome                                     as tipo_nome,
    ta.exige_fundamentacao,
    count(a.id)::integer                        as qtd_afastamentos,
    sum(
        coalesce(a.dias_previstos,
            (coalesce(a.data_fim, current_date) - a.data_inicio)::integer + 1,
        0)
    )::integer                                  as total_dias
from grom.rh_afastamentos a
join grom.rh_funcionarios f        on f.id  = a.funcionario_id
join grom.rh_tipos_afastamento ta  on ta.id = a.tipo_id
left join grom.cartorios ct        on ct.id = f.cartorio_id
where f.is_active = true
group by
    f.id, f.matricula, f.nome_completo,
    ct.code,
    extract(year from a.data_inicio),
    ta.codigo, ta.nome, ta.exige_fundamentacao
order by total_dias desc;

-- 6d. Resumo de quadro de pessoal por cartório
create or replace view grom.v_rh_quadro_cartorio as
select
    ct.id                                                   as cartorio_id,
    ct.code                                                 as cartorio_code,
    ct.name                                                 as cartorio_nome,
    count(f.id)                                             as total_funcionarios,
    sum(case when f.situacao = 'ATIVO'      then 1 else 0 end)::integer as ativos,
    sum(case when f.situacao = 'AFASTADO'   then 1 else 0 end)::integer as afastados,
    sum(case when f.situacao = 'CEDIDO'     then 1 else 0 end)::integer as cedidos,
    sum(case when f.turno    = 'DIURNO'     then 1 else 0 end)::integer as turno_diurno,
    sum(case when f.turno    = 'NOTURNO'    then 1 else 0 end)::integer as turno_noturno,
    sum(case when f.turno    = 'ADMINISTRATIVO' then 1 else 0 end)::integer as turno_admin
from grom.cartorios ct
left join grom.rh_funcionarios f
    on f.cartorio_id = ct.id
   and f.is_active   = true
where ct.is_active = true
group by ct.id, ct.code, ct.name
order by ct.number;

-- 6e. Pool de delegados externos ativos por cartório
create or replace view grom.v_rh_delegados_externos_pool as
select
    de.id,
    de.nome                                     as delegado_externo_nome,
    de.matricula,
    de.orgao_origem,
    ct.code                                     as cartorio_code,
    ct.name                                     as cartorio_nome,
    de.ato_legal,
    de.obs,
    de.created_at
from grom.rh_delegados_externos de
join grom.cartorios ct on ct.id = de.cartorio_id
where de.is_active = true
order by ct.number, de.nome;

-- 6f. Atribuições de substituto — mês/cartório
create or replace view grom.v_rh_escala_substitutos as
select
    s.id,
    s.data_dia,
    ct.code                                     as cartorio_code,
    ct.name                                     as cartorio_nome,
    -- Titular impedido
    ft.matricula                                as titular_matricula,
    ft.nome_completo                            as titular_nome,
    -- Delegado externo designado
    de.nome                                     as delegado_externo_nome,
    de.orgao_origem,
    -- Impedimento de origem (se vinculado a afastamento formal)
    ta.nome                                     as tipo_afastamento,
    a.data_inicio                               as afastamento_inicio,
    a.data_fim                                  as afastamento_fim,
    a.fundamentacao                             as afastamento_fundamentacao,
    s.created_at
from grom.rh_escala_delegado_substituto s
join grom.cartorios                  ct on ct.id = s.cartorio_id
join grom.rh_funcionarios            ft on ft.id = s.titular_funcionario_id
join grom.rh_delegados_externos      de on de.id = s.delegado_externo_id
left join grom.rh_afastamentos        a on a.id  = s.afastamento_id
left join grom.rh_tipos_afastamento  ta on ta.id = a.tipo_id
order by s.data_dia desc;
