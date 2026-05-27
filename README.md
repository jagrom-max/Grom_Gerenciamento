# GROM Web PHP

Sistema web institucional da DDM Rio Claro, construido sobre PHP 8.3 + Laravel 11.
Reconstrução assistida do GROM legado (Python/Qt) em plataforma web durável, auditável e hospedável.

---

## Estado atual do sistema — abril/2026

### Módulos completamente funcionais

| Módulo | Status | Observação |
|--------|--------|------------|
| Autenticação e sessão | ✅ | Login, logout, troca de senha, usuario ativo |
| RBAC (roles + permissions) | ✅ | Seeds por perfil; hasPermission() em toda a UI |
| Auditoria | ✅ | Log de login, logout, ações — consultável em /auditoria |
| Funcionários | ✅ | Cadastro, matrícula, cargo, setor, admissão |
| RH / Afastamentos | ✅ | Histórico de pessoal, timeline, afastamentos com período |
| Confronto mensal | ✅ | Todos os servidores ativos com/sem afastamento |
| Composição dos cartórios | ✅ | Por setor, com delegados externos |
| Estatísticas RH | ✅ | Tendência mensal, efetivo ativo/afastado |
| Delegados externos | ✅ | Sync do legado (10 registros); nome simplificado usado na escala |
| Feriados de RH | ✅ | Por escopo (municipal, estadual, nacional) |
| Agenda de afastamentos | ✅ | Consultável por período em /calendarios |
| Cartórios | ✅ | Módulo web de produtividade; stats mensais |
| Flagrantes | ✅ | Fila de confirmação; importação auditável |
| Pendências | ✅ | Saneamento cartório-sem-vínculo; confirmação manual |
| Painel operacional | ✅ | Consolidado de produtividade com filtros |
| Mandados de Prisão | ✅ | Migrado do legado (15 registros); independente do banco Python |
| Objetos Apreendidos | ✅ | Módulo operacional |
| Escalas e plantões | ✅ | Histórico migrado (1130 dias de escala); edição web por mês |
| Escala mensal (edição) | ✅ | Escrivão, operacional, fechar, delegada, plantão externo |
| Relatórios (central) | ✅ | Roteamento para todos os relatórios disponíveis |
| Relatório Produtividade A4 | ✅ | Geração PDF por mês e cartório |
| Acompanhamento Operacional | ✅ | Relatório gerencial com dados de produtividade |
| Análise de Dados | ✅ | Painel analítico; 1082 BOs migrados; upload XLSX; pesquisa nominal |
| Backup | ✅ | Observação local de backup do banco |

---

### Dashboard

O dashboard centraliza:
- Cartões de métricas em tempo real (efetivo, afastamentos, fila, produtividade)
- Acesso rápido por área: Operacional, RH, Escalas, Relatórios, Manutenção
- Painel de situação do dia (afastados, agendados, feriado próximo)
- Tabelas detalhadas de cartórios, funcionários, afastamentos, delegados, escala

---

### Navegação

O menu superior está organizado em:

| Menu | Conteúdo |
|------|----------|
| Dashboard | Painel inicial |
| Operacional | Painel, Mandados, Objetos, Cartórios, Flagrantes, Estatísticas |
| Pessoas | Funcionários, RH/Admin, Confronto, Composição, Estatísticas RH |
| Escalas | Escala Mensal, Plantões, Agenda de Afastamentos |
| Relatórios | Central, Produtividade A4, Acompanhamento Operacional, Análise |
| Manutenção | Backup, Auditoria, Usuários, Perfis de Acesso |

Dropdowns fecham automaticamente ao selecionar um item.

---

### Banco de dados

- **Banco principal**: SQLite em `storage/app/grom_web_local.sqlite`
- **Banco legado**: `C:/grom_gerenciamento_final/main/grom_database.sqlite3` (somente leitura — já não é necessário para operação normal; mantido como fonte de re-sync)
- **Migração**: PostgreSQL é o destino canônico para produção/hospedagem; SQLite é exclusivo para dev local

> **Independência do legado**: todos os dados operacionais foram migrados para o banco PHP em abril/2026.
> Para re-sincronizar a qualquer momento:
> ```powershell
> php artisan grom:import-legado-bos
> php artisan grom:import-legado-mandados --actor=admin
> php artisan grom:import-legado-escalas --actor=admin
> ```

---

### Pendências conhecidas para próxima iteração

| Item | Descrição |
|------|-----------|
| Tipificações dos mandados | Estrutura Lei (combobox) + Art. + § implementada; LEIS canônicas disponíveis |
| Múltiplos artigos por mandado | Tipificações extras com UI dinâmica (+ botão); salvas em JSON |
| Hospedagem | Stack alvo: HTTPS + PostgreSQL + Redis + PHP-FPM + Nginx |

---

## Infraestrutura local de execução

**Executar o servidor local:**
```cmd
.\executar_piloto_local.cmd
```

**Endereço local fixo para testes:**
```cmd
.\iniciar_grom_local_fixo.cmd
.\acessar_grom_local.cmd
```

Acesso fixo: http://127.0.0.1:8088/login

Ou diretamente:
```powershell
$env:PATH = "C:\grom_gerenciamento_final\grom_web_php\_toolchain\php83;" + $env:PATH
cd C:\grom_gerenciamento_final\grom_web_php\runtime
php artisan serve --host=127.0.0.1 --port=8088
```

Acesso: http://127.0.0.1:8088

**Após modificar views:**
```powershell
php artisan view:clear
```

---

## Arquitetura técnica

- **Backend**: PHP 8.3 / Laravel 11
- **Banco de dev**: SQLite (grom_web_local.sqlite)
- **Banco de produção**: PostgreSQL (canônico)
- **Cache/fila**: Redis (para produção)
- **Servidor web**: Nginx + PHP-FPM (para hospedagem)
- **Autenticação**: Sessão PHP com RBAC nativo
- **Auditoria**: Tabela `audit_logs` com actor, action, target, IP, timestamps
- **Relatórios**: Geração server-side com HTML/CSS imprimível + PDF

## Deploy Oracle VPS

O repositório agora inclui base operacional para produção/homologação em VPS Linux:

- [docs/08_deploy_oracle_vps.md](docs/08_deploy_oracle_vps.md)
- [docs/09_checklist_homologacao_go_live.md](docs/09_checklist_homologacao_go_live.md)
- [docs/10_rollback_oracle_vps.md](docs/10_rollback_oracle_vps.md)
- [docs/11_runbook_implantacao_oracle_vps.html](docs/11_runbook_implantacao_oracle_vps.html)
- [docs/12_folha_unica_implantacao_vps.html](docs/12_folha_unica_implantacao_vps.html)
- [infra/docker-compose.prod.yml](infra/docker-compose.prod.yml)
- [infra/.env.production.example](infra/.env.production.example)
- [infra/nginx/grom.seg.br.conf.example](infra/nginx/grom.seg.br.conf.example)

Para reduzir a implantacao ao minimo de operacao manual na VPS, use:

- [infra/scripts/deploy-prod.sh](infra/scripts/deploy-prod.sh)
- [infra/scripts/healthcheck-prod.sh](infra/scripts/healthcheck-prod.sh)
- [infra/scripts/backup-prod.sh](infra/scripts/backup-prod.sh)

Para impressão e execução direta em mesa, use [docs/12_folha_unica_implantacao_vps.html](docs/12_folha_unica_implantacao_vps.html).

Diretrizes adotadas:

- PostgreSQL e Redis não ficam expostos publicamente
- Nginx do host termina TLS e faz proxy para `127.0.0.1:8080`
- seed demo do piloto não entra mais automaticamente em produção
- sincronização legada fica desabilitada por padrão no ambiente final

---

## Piloto local executável

Scripts de apoio:
- `executar_piloto_local.cmd` — atalho Windows para iniciar
- `scripts/Start-GromWebPilot.ps1` — prepara ambiente, banco, seed e seed demonstrativa
- `runtime/database/seeders/GromPilotDemoSeeder.php` — cartórios, flagrantes, usuários de teste

Fluxos:
```powershell
# Preparar sem subir servidor:
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebPilot.ps1 -Fresh -PrepareOnly

# Preparar e smoke test HTTP:
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebPilot.ps1 -Fresh -SmokeTest

# Iniciar normalmente:
.\executar_piloto_local.cmd
```

O script usa SQLite local descartável, aplica migrations + seeds, aponta sincronização para o legado quando disponível e sobe o Laravel em `http://127.0.0.1:8088`.

## Teste de carga

O repositório inclui um teste de carga sem dependência externa em [scripts/load-test.ps1](scripts/load-test.ps1).

Pré-requisito:
- subir o sistema localmente em `http://127.0.0.1:8088`

Exemplo rápido:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebPilot.ps1 -Fresh -PrepareOnly

$env:PATH = "C:\grom_gerenciamento_final\grom_web_php\_toolchain\php83;" + $env:PATH
cd .\runtime
php artisan serve --host=127.0.0.1 --port=8088
```

Em outro terminal:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\load-test.ps1
```

O script executa quatro cenários:
- burst de login simultâneo com 20 autenticações
- dashboard a 50 req/s
- listagem de funcionários a 50 req/s
- geração do PDF A4 de produtividade a 10 req/s

Observação:
- quando executado sobre `php artisan serve`, os números são conservadores porque o servidor embutido do PHP é serial; para medir concorrência real, prefira subir a stack do [infra/docker-compose.yml](infra/docker-compose.yml) ou rodar no ambiente de homologação/VPS

Parâmetros úteis:

```powershell
# Só validar configuração e credenciais resolvidas
powershell -ExecutionPolicy Bypass -File .\scripts\load-test.ps1 -DryRun

# Ajustar duração e taxa
powershell -ExecutionPolicy Bypass -File .\scripts\load-test.ps1 -DurationSec 60 -AppRps 80 -PdfRps 15

# Falhar o processo se p95 > 500 ms ou existir HTTP 500
powershell -ExecutionPolicy Bypass -File .\scripts\load-test.ps1 -FailOnThreshold
```

Saída:
- resumo tabular no console
- relatório JSON salvo automaticamente em `scripts/load-test-report-AAAAmmdd-HHMMss.json`

## Stack Docker local

Se o ambiente tiver `docker-compose` disponivel, a stack local mais proxima de homologacao pode ser preparada com:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebDocker.ps1 -Build -SmokeTest -EscalaMensalTest -EscalaLifecycleTest -EscalaPlantaoConsistencyTest -LoadTest
```

O fluxo sobe `app`, `nginx`, `postgres`, `redis`, `worker` e `scheduler`, gera `APP_KEY`, limpa cache e executa migrations no banco PostgreSQL local da stack.
Quando `-SmokeTest` e usado, o script tambem valida login, dashboard, funcionarios, escalas e o PDF A4 pela URL em `127.0.0.1:8080`.
Quando `-EscalaMensalTest` e usado, o script valida a geracao do mes configurado e a impressao A4 `1/1`.
Quando `-EscalaLifecycleTest` e usado, o script valida gerar provisoria, fechar definitiva e abrir nova versao para o mes configurado.
Quando `-EscalaPlantaoConsistencyTest` e usado, o script valida a consistencia de plantao externo no impresso (cria atribuicao, atualiza sigla e confirma refletir no A4).
Quando `-LoadTest` e usado, uma rodada curta de carga autenticada e executada em `127.0.0.1:8080` e salva em `scripts/load-test-report-*.json`.

Observacao:
- `-EscalaLifecycleTest` altera o estado de versoes no mes testado. Use um mes de homologacao (ex.: mes futuro).

Atalhos uteis:

```powershell
# Recriar banco da stack local
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebDocker.ps1 -Build -Fresh

# Recriar banco e validar HTTP apos a subida
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebDocker.ps1 -Build -Fresh -SmokeTest

# Validar escala mensal (ano/mes parametrizaveis)
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebDocker.ps1 -Build -SmokeTest -EscalaMensalTest -EscalaMensalYear 2026 -EscalaMensalMonth 5

# Validar ciclo de vida da escala (use mes de homologacao)
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebDocker.ps1 -Build -SmokeTest -EscalaLifecycleTest -EscalaLifecycleYear 2026 -EscalaLifecycleMonth 6

# Validar consistencia dos plantoes externos na impressao A4
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebDocker.ps1 -Build -SmokeTest -EscalaPlantaoConsistencyTest -EscalaMensalYear 2026 -EscalaMensalMonth 5

# Recriar banco, validar HTTP e rodar carga curta
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebDocker.ps1 -Build -Fresh -SmokeTest -EscalaMensalTest -LoadTest

# Tornar a carga estrita (falha se p95 > 500 ms ou houver HTTP 500)
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebDocker.ps1 -Build -SmokeTest -LoadTest -LoadTestFailOnThreshold

# Subir sem seed
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebDocker.ps1 -Build -NoSeed

# Parar a stack
powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebDocker.ps1 -Down
```

URL esperada: `http://127.0.0.1:8080/login`

## Validacao da escala mensal

Para validar a geracao web da escala e a impressao A4 de um mes especifico:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\Test-GromEscalaMensal.ps1 -BaseUrl http://127.0.0.1:8088 -Year 2026 -Month 5
```

O script:
- autentica com `gestor.demo`
- tenta gerar a escala do mes informado via fluxo web real
- valida a tela do mes
- valida a tela de impressao A4 com marcador `1/1`

Se quiser falhar explicitamente quando a acao de gerar nao responder com sucesso:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\Test-GromEscalaMensal.ps1 -BaseUrl http://127.0.0.1:8088 -Year 2026 -Month 5 -RequireGeneration
```

## Validacao do ciclo de vida da escala

Para validar geracao, fechamento como definitiva e abertura de nova versao em um mes de teste:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\Test-GromEscalaLifecycle.ps1 -BaseUrl http://127.0.0.1:8088 -Year 2026 -Month 6
```

O script valida a sequencia:
- gerar escala provisoria
- gravar como definitiva
- criar nova versao provisoria

## Validacao de consistencia do plantao externo

Para validar a sincronizacao da coluna de plantao externo no mes/impresso A4:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\Test-GromEscalaPlantaoConsistency.ps1 -BaseUrl http://127.0.0.1:8088 -Year 2026 -Month 5
```

O script valida a sequencia:
- cria um plantao externo de teste no catalogo
- atribui esse plantao para um funcionario em um dia util do mes
- confirma no impresso A4 o marcador `Funcionario (SIGLA_ORIGINAL)`
- atualiza a sigla do plantao externo
- confirma no impresso A4 a troca para `Funcionario (SIGLA_ATUALIZADA)` sem manter a sigla antiga

---

## Princípios desta reconstrução

1. O sistema Python permanece intacto até homologação completa do módulo web equivalente.
2. Todo módulo migrado passa por validação paralela antes de corte definitivo.
3. O banco legado (SQLite) é fonte de extração, não destino permanente.
4. Toda escrita crítica é auditada.
5. Os relatórios nascem padronizados, não por exceção.
6. Interface server-driven: sem frontend framework; HTML semântico com CSS puro, renderizável em qualquer navegador moderno.


## Regra de ouro dos relatórios

Todo relatório novo ou existente deve usar o timbrado consolidado do sistema. Não se cria variação visual própria por módulo, tipo de documento ou autoria da tela.

### Padrão obrigatório

- Use [`x-report.default`](/c:/grom_gerenciamento_final/grom_web_php/runtime/resources/views/components/report/default.blade.php) como porta de entrada para relatórios novos.
- O componente [`x-report.a4-shell`](/c:/grom_gerenciamento_final/grom_web_php/runtime/resources/views/components/report/a4-shell.blade.php) é a base única do timbrado institucional.
- Cabeçalho, brasão, logo, rodapé e margens são definidos pelo shell consolidado, não pela view do relatório.
- Se um relatório precisar de ajustes de conteúdo, ajuste apenas a área interna do documento, nunca a identidade visual base.

### Exemplo de uso

```blade
<x-report.default
    title="Nome do Relatório"
    period="Abril / 2026"
    :generatedAt="now()"
    origin="Módulo / Origem"
    footer-note="Cartório Central - Gerenciamento"
>
    {{-- Conteúdo específico do relatório --}}
</x-report.default>
```

### Regra prática

- Nada de timbrado paralelo.
- Nada de cabeçalho inventado por tela.
- Nada de rodapé alternativo.
- Se estiver em dúvida, comece sempre por `x-report.default`.
