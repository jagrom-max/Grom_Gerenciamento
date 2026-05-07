-- Tabela para registrar substituições longas da Delegada DDM por Delegado Externo
create table if not exists grom.escalas_substituicoes (
    id serial primary key,
    tipo varchar(20) not null, -- 'ddm' para este caso
    delegado_externo_id uuid not null references grom.rh_funcionarios(id) on delete restrict,
    data_inicio date not null,
    data_fim date not null,
    motivo varchar(100) not null,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create index if not exists idx_subst_ddm_periodo on grom.escalas_substituicoes (data_inicio, data_fim);
