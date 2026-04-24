# Seguranca e Operacao

## Modelo de acesso

Entrada na plataforma:
- login por usuario e senha;
- criacao de usuarios apenas por administrador;
- perfis e permissoes limitadas por RBAC;
- escopo por modulo, unidade e cartorio quando aplicavel;
- reautenticacao para acoes sensiveis;
- MFA recomendada para administradores e acessos externos.

Perfis base:
- `super_admin`
- `administrador`
- `gestor_cartorio`
- `operador`
- `consulta`
- `auditor`

## Politica de autorizacao

Regras:
- deny by default;
- permissao explicita por acao;
- filtro por escopo de negocio;
- toda acao administrativa relevante entra em auditoria.

## Controles obrigatorios

- HTTPS obrigatorio;
- cookies seguros, `HttpOnly` e `SameSite`;
- CSRF em formularios;
- rate limit de autenticacao e consultas sensiveis;
- logs de autenticacao, autorizacao, importacao e exportacao;
- trilha de alteracao de registros sensiveis;
- senha armazenada apenas como hash;
- segredos fora do codigo;
- roles e usuarios do banco separados por ambiente.

## Protecao de dados

Principios:
- minimo privilegio;
- minimizacao de dados;
- mascaramento em tela quando possivel;
- nao logar senhas, tokens ou dados desnecessarios;
- retencao controlada de relatorios e exports;
- storage privado para arquivos;
- backups criptografados.

## Topologia operacional

Ambientes previstos:
- `local`
- `hml`
- `prod`

Servicos minimos:
- `nginx`
- `app`
- `worker`
- `scheduler`
- `postgres`
- `redis`
- `storage privado`

## Backup e continuidade

Banco:
- backup diario;
- snapshots frequentes;
- restore testado mensalmente;
- estrategia de PITR recomendada em producao.

Arquivos:
- relatorios e anexos em storage versionado;
- copia offsite;
- politica de retencao por classe de documento.

## Regras para acesso remoto

Para dados de seguranca publica:
- preferir VPS ou cloud privada;
- usar HTTPS com certificado valido;
- restringir administracao por VPN, IP allowlist ou camada equivalente;
- nao expor banco diretamente na internet;
- nao usar compartilhamento de pasta Windows como mecanismo principal.

## Observabilidade

Precisamos de:
- log estruturado de aplicacao;
- log de fila;
- log de exportacao e importacao;
- painel de erros;
- healthcheck de banco, fila, storage e scheduler.

## Criterio de producao

O sistema so vai para hospedagem final quando houver:
- ambiente de homologacao aprovado;
- restore de backup testado;
- controle de acesso validado;
- HTTPS ativo;
- dominio configurado;
- plano de incidente documentado.

