create schema if not exists grom;
create extension if not exists pgcrypto;
create extension if not exists citext;

create or replace function grom.touch_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = now();
    return new;
end;
$$;

create table if not exists grom.app_users (
    id uuid primary key default gen_random_uuid(),
    username citext not null unique,
    full_name text not null,
    email citext unique,
    password_hash text not null,
    is_active boolean not null default true,
    must_change_password boolean not null default true,
    two_factor_enabled boolean not null default false,
    last_login_at timestamptz,
    last_login_ip inet,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create table if not exists grom.app_roles (
    id uuid primary key default gen_random_uuid(),
    code text not null unique,
    name text not null,
    description text,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create table if not exists grom.app_permissions (
    id uuid primary key default gen_random_uuid(),
    code text not null unique,
    module_code text not null,
    name text not null,
    description text,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create table if not exists grom.app_user_roles (
    user_id uuid not null references grom.app_users(id) on delete cascade,
    role_id uuid not null references grom.app_roles(id) on delete cascade,
    assigned_by uuid references grom.app_users(id),
    assigned_at timestamptz not null default now(),
    primary key (user_id, role_id)
);

create table if not exists grom.app_role_permissions (
    role_id uuid not null references grom.app_roles(id) on delete cascade,
    permission_id uuid not null references grom.app_permissions(id) on delete cascade,
    granted_by uuid references grom.app_users(id),
    granted_at timestamptz not null default now(),
    primary key (role_id, permission_id)
);

create table if not exists grom.app_user_scopes (
    id bigserial primary key,
    user_id uuid not null references grom.app_users(id) on delete cascade,
    scope_type text not null,
    scope_key text not null,
    created_by uuid references grom.app_users(id),
    created_at timestamptz not null default now(),
    unique (user_id, scope_type, scope_key)
);

create table if not exists grom.audit_events (
    id bigserial primary key,
    actor_user_id uuid references grom.app_users(id),
    module_code text not null,
    event_type text not null,
    entity_type text not null,
    entity_id text not null,
    description text,
    source_ip inet,
    user_agent text,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now()
);

create index if not exists audit_events_module_idx on grom.audit_events (module_code, created_at desc);
create index if not exists audit_events_entity_idx on grom.audit_events (entity_type, entity_id);

create table if not exists grom.integration_jobs (
    id uuid primary key default gen_random_uuid(),
    job_type text not null,
    source_system text not null,
    source_ref text,
    status text not null default 'pending',
    attempts integer not null default 0,
    payload jsonb not null default '{}'::jsonb,
    error_message text,
    available_at timestamptz not null default now(),
    started_at timestamptz,
    finished_at timestamptz,
    created_by uuid references grom.app_users(id),
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create trigger trg_app_users_touch
before update on grom.app_users
for each row execute function grom.touch_updated_at();

create trigger trg_app_roles_touch
before update on grom.app_roles
for each row execute function grom.touch_updated_at();

create trigger trg_app_permissions_touch
before update on grom.app_permissions
for each row execute function grom.touch_updated_at();

create trigger trg_integration_jobs_touch
before update on grom.integration_jobs
for each row execute function grom.touch_updated_at();

insert into grom.app_roles (code, name, description)
values
    ('super_admin', 'Super Admin', 'Acesso total ao sistema'),
    ('administrador', 'Administrador', 'Gestao ampla de usuarios e modulos'),
    ('gestor_cartorio', 'Gestor de Cartorio', 'Opera e homologa dados do cartorio'),
    ('operador', 'Operador', 'Opera rotinas permitidas'),
    ('consulta', 'Consulta', 'Acesso somente leitura'),
    ('auditor', 'Auditor', 'Acesso a trilha de auditoria')
on conflict (code) do nothing;

