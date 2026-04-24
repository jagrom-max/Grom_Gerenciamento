# Módulo Análise — Boletins de Ocorrência (BOs) Unificados

## Objetivo

Consolidar **todos os Boletins de Ocorrência** em uma única tabela canônica — independentemente de tipo (flagrante, não-flagrante ou termo circunstanciado) —, com rastreamento de MPU (Medida Protetiva de Urgência) e identificação automática de pendências críticas: **MPU deferida sem Inquérito Policial instaurado**.

---

## Problema que resolve

No sistema legado, BOs eram tratados fragmentariamente:

- Flagrantes em `prod_flagrantes` (módulo de Produtividade)
- Ocorrências gerais em `analise_ocorrencias`
- Sem distinção padronizada entre tipos de BO
- Sem rastreamento estruturado de MPU
- Sem alerta automático para o caso crítico: MPU deferida + ausência de IP

Isso tornava impossível:
- Gerar relatório unificado de BOs por cartório e período
- Identificar cases de risco (vítimas com MPU e sem acompanhamento)
- Auditar a evolução de cada BO (abertura → IP → encerramento)

---

## Estrutura de dados

### Tabela principal: `grom.analise_bos`

| Campo            | Tipo      | Descrição                                               |
|------------------|-----------|---------------------------------------------------------|
| `tipo_ocorrencia`| `text`    | `FLAGRANTE` · `NAO_FLAGRANTE` · `TERMO_CIRCUNSTANCIADO`|
| `status_bo`      | `text`    | `ABERTO` · `CONVERTIDO_IP` · `ARQUIVADO` · `ENCERRADO` |
| `tem_mpu`        | `boolean` | O BO possui MPU associada                               |
| `mpu_status`     | `text`    | `PENDENTE` · `DEFERIDA` · `INDEFERIDA` · `CUMPRIDA` · `REVOGADA` |
| `mpu_sem_ip`     | `boolean` | **Flag crítico**: MPU deferida sem IP instaurado        |
| `num_ip`         | `text`    | Número do Inquérito Policial vinculado (quando existir) |
| `lavrado_unidade`| `text`    | `DDM` · `OUTRAS_UNIDADES`                              |

### Tabela de MPUs: `grom.analise_bos_mpus`

Permite múltiplos pedidos de MPU dentro de um mesmo BO, cada um com seu status e vínculo com IP.

### Histórico de status: `grom.analise_bos_status_hist`

Toda mudança de `status_bo` é registrada com motivo, responsável e timestamp.

### Changelog analítico: `grom.analise_change_log`

Rastreia alterações campo a campo para auditoria completa.

---

## Views disponíveis

| View                            | Propósito                                                  |
|---------------------------------|------------------------------------------------------------|
| `v_analise_flagrantes`          | Compatibilidade com módulo de Produtividade                |
| `v_analise_nao_flagrantes`      | Não-flagrantes ativos com MPU                              |
| `v_mpu_sem_ip_criticos`         | **Painel crítico**: MPU ativas sem IP, ordenadas por urgência |
| `v_bos_rollup_monthly`          | Totais mensais por cartório e tipo                         |
| `v_bos_resumo_cartorio`         | Resumo por cartório para dashboard                         |

---

## Caso crítico: MPU sem IP

### O que é

Uma MPU (Medida Protetiva de Urgência) é deferida judicialmente para proteger a vítima. A expectativa operacional é que um IP (Inquérito Policial) seja instaurado para investigar o caso de fundo.

**Quando `mpu_sem_ip = true`**: a MPU foi deferida, mas nenhum IP foi vinculado — o caso exige revisão imediata.

### Como é detectado

1. Na inserção/atualização de um BO: se `tem_mpu = true`, `mpu_status = 'DEFERIDA'` e `num_ip` é vazio → `mpu_sem_ip` é marcado como `true`.
2. Na confirmação de um IP (quando `num_ip` é preenchido): `mpu_sem_ip` deve ser automaticamente redefinido para `false`.
3. A view `v_mpu_sem_ip_criticos` exibe os casos ativos ordenados por `dias_sem_ip` (mais antigos primeiro).

### Regras de negócio

- `mpu_sem_ip = true` implica `tem_mpu = true` (constraint `ck_bos_mpu_sem_ip`)
- Só BOs com `status_bo` em `ABERTO` aparecem no painel crítico
- A resolução exige preenchimento de `num_ip` **ou** justificativa de arquivamento

---

## Tipos de BO e fluxos

```
FLAGRANTE
  Lavrado → Confirmado → IP instaurado automaticamente → Em andamento → Encerrado

NAO_FLAGRANTE
  Registrado → Triagem
     ├── Com MPU → Acompanhamento → IP instaurado (resolve mpu_sem_ip)
     └── Sem MPU → Investigação → IP instaurado ou Arquivado

TERMO_CIRCUNSTANCIADO
  Registrado → Encaminhado ao JECRIM → Encerrado
```

---

## Compatibilidade com o módulo de Produtividade

A view `v_analise_flagrantes` reproduz o contrato de dados de `produtividade_flagrantes` para que:
- Os relatórios de produtividade continuem funcionando sem alteração
- A tabela `produtividade_flagrantes` possa ser gradualmente depreciada
- A migração de dados do legado possa ser feita via `source_item_id` (FK para `import_items`)

---

## Importação e staging

BOs chegam via `import_items` (batch de upload). O fluxo:

1. Arquivo CSV/XLSX importado → `import_batch` + `import_items` (status `pending`)
2. Homologação manual pelo gestor → item confirmado
3. `analise_bos` criado com `source_item_id` apontando para o `import_item`
4. Se BO é flagrante: view `v_analise_flagrantes` o expõe ao módulo de Produtividade

---

## Segurança e auditoria

- Acesso via permissão `analise.bos.*` (deny by default)
- Escopo por cartório via `app_user_scopes`
- Toda confirmação registrada em `audit_events` (módulo `analise`)
- Campos sensíveis (vítima, endereço) mascarados para perfil `consulta`
- Queries parametrizadas obrigatórias — nenhum valor de filtro interpolado diretamente

---

## Índices de performance

| Índice                    | Colunas                          | Condição parcial                          |
|---------------------------|----------------------------------|-------------------------------------------|
| `idx_bos_cartorio_data`   | `cartorio_id, data_fato desc`    | —                                         |
| `idx_bos_tipo_status`     | `tipo_ocorrencia, status_bo`     | —                                         |
| `idx_bos_mpu_sem_ip`      | `mpu_sem_ip, is_active`          | `mpu_sem_ip = true AND is_active = true`  |
| `idx_bos_num_ip`          | `lower(num_ip)`                  | `num_ip IS NOT NULL`                      |
| `ux_bos_cartorio_num_bo`  | `cartorio_id, lower(num_bo)`     | `num_bo IS NOT NULL` (unicidade)          |

---

## Relatórios disponíveis

1. **Tabela unificada de BOs** — por período, tipo, cartório e status
2. **Relatório MPU sem IP** — lista crítica para gestão imediata
3. **Rollup mensal** — flagrantes vs. não-flagrantes vs. TCs por cartório
4. **Exportação CSV/XLSX** — com todos os campos, respeitando escopo do usuário
5. **Relatório A4 timbrado** — via template canônico (ver `docs/05_relatorios_timbrado.md`)
