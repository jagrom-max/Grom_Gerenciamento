if not exists (select * from sys.objects where object_id = object_id(N'grom.escalas_substituicoes') and type in (N'U'))
create table grom.escalas_substituicoes (
    id bigint primary key identity(1,1),
    tipo varchar(20) not null, -- 'ddm' para este caso
    delegado_externo_id uniqueidentifier not null references grom.rh_funcionarios(id),
    data_inicio date not null,
    data_fim date not null,
    motivo varchar(100) not null,
    created_at datetime2 not null default sysutcdatetime(),
    updated_at datetime2 not null default sysutcdatetime()
);

if not exists (select * from sys.indexes where name = 'idx_subst_ddm_periodo' and object_id = object_id('grom.escalas_substituicoes'))
    create index idx_subst_ddm_periodo on grom.escalas_substituicoes (data_inicio, data_fim);
