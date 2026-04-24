create table if not exists grom.cartorios (
    id uuid primary key default gen_random_uuid(),
    number integer not null,
    code text not null unique,
    name text not null,
    designacao text,
    notes text,
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create table if not exists grom.cartorio_status_history (
    id bigserial primary key,
    cartorio_id uuid not null references grom.cartorios(id) on delete cascade,
    status text not null,
    reason text,
    changed_by uuid references grom.app_users(id),
    changed_at timestamptz not null default now()
);

create table if not exists grom.cartorio_manager_history (
    id bigserial primary key,
    cartorio_id uuid not null references grom.cartorios(id) on delete cascade,
    manager_name text not null,
    changed_by uuid references grom.app_users(id),
    changed_at timestamptz not null default now()
);

create table if not exists grom.import_batches (
    id uuid primary key default gen_random_uuid(),
    source_name text not null,
    source_hash text,
    source_period_start date,
    source_period_end date,
    imported_by uuid references grom.app_users(id),
    imported_at timestamptz not null default now(),
    total_rows integer not null default 0,
    notes text
);

create table if not exists grom.import_items (
    id uuid primary key default gen_random_uuid(),
    batch_id uuid not null references grom.import_batches(id) on delete cascade,
    source_process_key text not null,
    cartorio_id uuid references grom.cartorios(id),
    spj text,
    naturezas text,
    num_ip text,
    num_ipe text,
    num_cnj text,
    data_fato date,
    status_origem text,
    lavrado_unidade text not null default 'OUTRAS_UNIDADES',
    payload jsonb not null default '{}'::jsonb,
    import_status text not null default 'pending',
    confirmed_by uuid references grom.app_users(id),
    confirmed_at timestamptz,
    rejected_reason text,
    created_at timestamptz not null default now(),
    unique (batch_id, source_process_key)
);

create table if not exists grom.produtividade_flagrantes (
    id uuid primary key default gen_random_uuid(),
    cartorio_id uuid not null references grom.cartorios(id) on delete restrict,
    source_item_id uuid unique references grom.import_items(id) on delete set null,
    spj text,
    naturezas text,
    num_ip text,
    num_ipe text,
    num_cnj text,
    data_fato date,
    lavrado_unidade text not null,
    manually_confirmed boolean not null default false,
    confirmed_by uuid references grom.app_users(id),
    confirmed_at timestamptz,
    notes text,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    check (lavrado_unidade in ('DDM', 'OUTRAS_UNIDADES'))
);

create unique index if not exists ux_flagrantes_cartorio_spj
    on grom.produtividade_flagrantes (cartorio_id, lower(spj))
    where spj is not null and btrim(spj) <> '';

create unique index if not exists ux_flagrantes_cartorio_ip
    on grom.produtividade_flagrantes (cartorio_id, lower(num_ip))
    where num_ip is not null and btrim(num_ip) <> '';

create unique index if not exists ux_flagrantes_cartorio_cnj
    on grom.produtividade_flagrantes (cartorio_id, lower(num_cnj))
    where num_cnj is not null and btrim(num_cnj) <> '';

create table if not exists grom.produtividade_stats_monthly (
    id uuid primary key default gen_random_uuid(),
    cartorio_id uuid not null references grom.cartorios(id) on delete cascade,
    reference_year integer not null,
    reference_month integer not null,
    ip_instaurados integer not null default 0,
    ip_relatados integer not null default 0,
    cotas integer not null default 0,
    despachos integer not null default 0,
    concluidos integer not null default 0,
    registros integer not null default 0,
    ips_andamento integer not null default 0,
    flagrantes_total integer not null default 0,
    flagrantes_ddm integer not null default 0,
    flagrantes_outras integer not null default 0,
    source_mode text not null default 'AUTO',
    manual_notes text,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    unique (cartorio_id, reference_year, reference_month),
    check (reference_month between 1 and 12)
);

create or replace view grom.v_flagrantes_rollup_monthly as
select
    f.cartorio_id,
    extract(year from f.data_fato)::integer as reference_year,
    extract(month from f.data_fato)::integer as reference_month,
    count(*)::integer as flagrantes_total,
    sum(case when f.lavrado_unidade = 'DDM' then 1 else 0 end)::integer as flagrantes_ddm,
    sum(case when f.lavrado_unidade = 'OUTRAS_UNIDADES' then 1 else 0 end)::integer as flagrantes_outras
from grom.produtividade_flagrantes f
where f.data_fato is not null
group by
    f.cartorio_id,
    extract(year from f.data_fato),
    extract(month from f.data_fato);

create trigger trg_cartorios_touch
before update on grom.cartorios
for each row execute function grom.touch_updated_at();

create trigger trg_produtividade_flagrantes_touch
before update on grom.produtividade_flagrantes
for each row execute function grom.touch_updated_at();

create trigger trg_produtividade_stats_monthly_touch
before update on grom.produtividade_stats_monthly
for each row execute function grom.touch_updated_at();

