# Módulo RH — Recursos Humanos

## Objetivo

Gerenciar o **quadro de pessoal** da DDM Rio Claro: cadastro de funcionários, cargos, afastamentos e delegados externos em exercício temporário na unidade, com geração de relatórios administrativos timbrados.

---

## Domínio

O módulo RH é responsável pelos seguintes contextos:

| Contexto               | Descrição                                                        |
|------------------------|------------------------------------------------------------------|
| **Cargos**             | Tabela de referência de cargos e níveis hierárquicos            |
| **Funcionários**       | Cadastro completo do quadro funcional próprio                   |
| **Afastamentos**       | Registro, aprovação e acompanhamento de ausências               |
| **Delegados externos** | Lotação temporária de servidores de outros órgãos               |

---

## Estrutura de dados

### `grom.rh_cargos`

Tabela de referência de cargos com nível hierárquico (`DIRECAO`, `MEDIO`, `OPERACIONAL`, `APOIO`).

Cargos pré-cadastrados:
- `DEL` — Delegado de Polícia (Direção)
- `INV` — Investigador de Polícia (Operacional)
- `ESC` — Escrivão de Polícia (Operacional)
- `AGP` — Agente de Polícia (Operacional)
- `PER` — Perito Criminal (Médio)
- `ADM` — Auxiliar Administrativo (Apoio)
- `EST` — Estagiário (Apoio)

### `grom.rh_funcionarios`

Campos principais:

| Campo          | Descrição                                                              |
|----------------|------------------------------------------------------------------------|
| `matricula`    | Matrícula funcional única                                              |
| `situacao`     | `ATIVO` · `AFASTADO` · `EXONERADO` · `APOSENTADO` · `CEDIDO`         |
| `turno`        | `DIURNO` · `NOTURNO` · `ADMINISTRATIVO` · `PLANTAO`                   |
| `cartorio_id`  | Cartório de lotação (FK para `grom.cartorios`)                         |
| `is_active`    | Soft-delete para manutenção de histórico                               |

### `grom.rh_tipos_afastamento`

Tipos reconhecidos pelo GROM — exatamente esses, sem exceção:

| Código       | Nome           | Exige Fundamentação |
|--------------|----------------|---------------------|
| `FERIAS`     | Férias         | ❌                  |
| `LIC_PREMIO` | Licença Prêmio | ❌                  |
| `LIC_SAUDE`  | Licença Saúde  | ❌                  |
| `CURSO`      | Curso          | ✅                  |
| `FOLGA`      | Folga          | ✅                  |
| `OUTROS`     | Outros         | ✅                  |

**Todos os tipos afetam a escala.** Os tipos com `exige_fundamentacao = true` (Curso, Folga, Outros) obrigam o preenchimento do campo `fundamentacao` na camada de aplicação — a validação é rejeitada se o campo estiver vazio.

### `grom.rh_afastamentos`

Campos principais:

| Campo            | Descrição                                                                    |
|------------------|------------------------------------------------------------------------------|
| `tipo_id`        | FK para `rh_tipos_afastamento`                                               |
| `data_inicio`    | Data de início do afastamento                                                |
| `data_fim`       | Data de término (nula = sem data certa de retorno)                           |
| `dias_previstos` | Dias previstos (pode diferir de `data_fim - data_inicio` em prorrogações)    |
| `prorrogado`     | Flag de extensão de afastamento já registrado                                |
| `fundamentacao`  | Texto obrigatório para Curso, Folga e Outros; opcional para os demais        |
| `documento_ref`  | Protocolo, laudo ou número de documento de referência                        |
| `aprovado_por`   | Usuário do sistema que aprovou o afastamento                                 |

### `grom.rh_delegados_externos`

**Finalidade:** pool de Delegados(as) externos(as) credenciados(as) para cobrir impedimentos do(a) Delegado(a) titular da DDM. Não guarda período de cobertura — o vínculo dia a dia é feito na tabela `rh_escala_delegado_substituto`.

Campos principais:

| Campo          | Descrição                                                                      |
|----------------|--------------------------------------------------------------------------------|
| `nome`         | Nome completo do(a) delegado(a) externo(a)                                     |
| `matricula`    | Matrícula funcional no órgão de origem                                         |
| `orgao_origem` | Órgão ou delegacia de origem                                                   |
| `cartorio_id`  | Cartório DDM ao qual está credenciado(a) como opção de cobertura               |
| `ato_legal`    | Portaria ou ato legal que formaliza o credenciamento                           |
| `is_active`    | Soft-disable para remover do combobox sem excluir o histórico                  |

### `grom.rh_escala_delegado_substituto`

Registro das atribuições diárias de substituto na escala mensal.

| Campo                    | Descrição                                                                   |
|--------------------------|-----------------------------------------------------------------------------|
| `cartorio_id`            | Cartório DDM onde ocorre a substituição                                     |
| `data_dia`               | Data específica (UNIQUE por cartório — um substituto por dia)               |
| `titular_funcionario_id` | FK obrigatória para `rh_funcionarios` — quem está impedido naquele dia. **Validado por trigger: cargo obrigatoriamente DEL.** |
| `delegado_externo_id`    | FK para `rh_delegados_externos` — escolhido manualmente pelo operador       |
| `afastamento_id`         | FK opcional para `rh_afastamentos` — impedimento formal de origem (pode ser nulo em casos como plantão noturno em outra unidade) |

**Regra fundamental — exclusiva para cargo DEL:**
- A substituição via combobox **só ocorre para o cargo Delegado(a) de Polícia (DEL)**.
- Nenhum outro cargo tem este mecanismo.
- O trigger `trg_escala_subst_cargo_del` rejeita inserções com titular de cargo diferente de `DEL`.

**Sem atribuição automática:** o sistema nunca atribui um substituto automaticamente. O operador escolhe manualmente no combobox.

**Observações do mês (gerado automaticamente pelo módulo de Escalas):**
Quando existe um afastamento registrado para o(a) titular no período, o módulo gera automaticamente o texto nas observações do mês:
> *"De 01/04 a 15/04, Dra. [Nome] estará de Férias."*

Para impedimentos sem afastamento formal (ex.: plantão noturno em outra unidade), o operador insere manualmente a observação.

---

## Views disponíveis

| View                                  | Propósito                                              |
|---------------------------------------|--------------------------------------------------------|
| `v_rh_funcionarios_ativos`            | Lista de ativos com cargo e cartório                  |
| `v_rh_afastamentos_ativos`            | Afastamentos em andamento (data_fim ≥ hoje)           |
| `v_rh_afastamentos_consolidado_ano`   | Total de dias por funcionário/tipo/ano                |
| `v_rh_quadro_cartorio`                | Quadro de pessoal por cartório (ativos, afastados)    |
| `v_rh_delegados_externos_pool`        | Pool de delegados externos ativos por cartório        |
| `v_rh_escala_substitutos`             | Atribuições de substituto com titular e tipo de impedimento |

---

## Fluxos operacionais

### Registro de afastamento

```
1. Operador registra afastamento (funcionario_id, tipo_id, data_inicio, data_fim)
2. Gestor revisa e aprova (aprovado_por, aprovado_em)
3. Sistema atualiza situacao do funcionário → 'AFASTADO'
4. Módulo de Escalas é notificado (se tipo.afeta_escala = true)
5. Ao fim do afastamento: situacao reverte para 'ATIVO'
```

### Prorrogação de afastamento

```
1. Operador registra novo afastamento com prorrogado = true
2. data_fim anterior é atualizada no registro original
3. Histórico mantido — nenhum registro é deletado
```

### Credenciamento de delegado externo

```
1. Gestor cadastra delegado em rh_delegados_externos (nome, órgão, cartório, ato legal)
2. is_active = true → aparece no combobox da escala mensal do cartório vinculado
3. Para desativar: is_active = false (histórico de atribuições preservado)
```

### Substituição na escala mensal

```
REGRA: aplica-se SOMENTE ao cargo DEL (Delegado de Polícia).
Outros cargos não possuem este mecanismo.

1. Operador abre a escala do mês
2. Sistema identifica dias em que o(a) Delegado(a) titular (cargo DEL)
   está impedido(a) (afastamento registrado ou impedimento manual)
3. Nesses dias, o campo do(a) Delegado(a) na grade exibe combobox
   com o pool do cartório (rh_delegados_externos)
4. Operador seleciona o substituto dia a dia — sem fundamentação
5. Sistema grava em rh_escala_delegado_substituto
6. Observações do mês (geradas automaticamente quando há afastamento):
   "De DD/MM a DD/MM, [Nome] estará de [Tipo]."
   Para impedimentos sem afastamento formal, operador registra manualmente.
```

---

## Relatórios disponíveis

### 1. Quadro de pessoal
- Lista de funcionários ativos por cartório e cargo
- Totais: ativos, afastados, cedidos
- Percentual de preenchimento vs. quadro autorizado
- Filtros: cartório, cargo, situação, turno

### 2. Relatório de afastamentos por período
- Consolidado por funcionário, tipo e cartório
- Total de dias por categoria (médico, férias, etc.)
- Afastamentos em andamento destacados
- Exportação CSV/XLSX com todos os campos
- Relatório A4 timbrado para apresentação institucional

### 3. Delegados externos em exercício
- Lista com órgão de origem, ato legal e período
- Data de retorno prevista
- Cartório de exercício

---

## Integração com módulo de Escalas

**Regra de substituição — exclusiva para cargo DEL:**

Quando um afastamento é registrado para o(a) Delegado(a) titular (cargo `DEL`):
- O módulo de Escalas marca os dias impedidos na grade mensal do cartório
- **Apenas nesses dias**, e **apenas para o cargo DEL**, o campo do(a) Delegado(a) exibe um *combobox* com o pool de externos (`rh_delegados_externos` do cartório)
- **Nenhum substituto é atribuído automaticamente** — a escolha é manual, dia a dia
- O sistema gera automaticamente nas observações do mês o texto:
  > *"De DD/MM a DD/MM, [Nome] estará de [Tipo de Afastamento]."*
- Para impedimentos sem afastamento formal (ex.: plantão noturno em outra unidade — o dia e o seguinte), o operador marca os dias manualmente e insere a observação

**Outros cargos** (INV, ESC, AGP, PER, ADM, EST): ausências afetam a escala normalmente, mas **não** acionam o mecanismo de combobox/substituto externo.

---

## Segurança e controle de acesso

| Ação                             | Perfil mínimo     |
|----------------------------------|-------------------|
| Visualizar quadro               | `consulta`        |
| Registrar afastamento           | `operador`        |
| Aprovar afastamento             | `gestor_cartorio` |
| Cadastrar/editar funcionário    | `administrador`   |
| Gerar relatório RH              | `operador`        |
| Exportar dados completos        | `gestor_cartorio` |

Regras adicionais:
- Escopo por cartório: operadores e gestores só visualizam seu cartório
- `super_admin` e `administrador` têm acesso a todos os cartórios
- Toda alteração registrada em `audit_events` (módulo `rh`)
- Dados de contato (email, telefone) mascarados para perfil `consulta`

---

## Campos calculados nas views

| Campo                | Fórmula                                               |
|----------------------|-------------------------------------------------------|
| `dias_decorridos`    | `current_date − data_inicio`                         |
| `dias_na_unidade`    | `current_date − data_inicio` (delegados externos)    |
| `total_dias`         | `dias_previstos` ou `(data_fim − data_inicio) + 1`   |

---

## Índices de performance

| Índice                    | Colunas                          | Condição parcial     |
|---------------------------|----------------------------------|----------------------|
| `idx_rh_func_cartorio`    | `cartorio_id`                    | `is_active = true`   |
| `idx_rh_func_situacao`    | `situacao`                       | `is_active = true`   |
| `idx_rh_afas_funcionario` | `funcionario_id, data_inicio desc`| —                   |
| `idx_rh_afas_periodo`     | `data_inicio, data_fim`          | —                    |
| `idx_rh_deleg_ext_cartorio`| `cartorio_id, data_inicio desc` | —                    |

---

## Dados de referência pré-carregados

O script `004_rh.sql` já insere:
- 7 cargos padrão (`INSERT ... ON CONFLICT DO NOTHING`)
- 6 tipos de afastamento padrão

Esses registros servem de base e podem ser complementados via interface administrativa sem necessidade de nova migration.
