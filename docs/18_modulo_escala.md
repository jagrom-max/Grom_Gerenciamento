# Módulo Escalas

## Objetivo

Gerar, conferir, editar pontualmente e salvar a **escala mensal da DDM de Rio Claro**,
levando em conta os plantões externos realizados pelos funcionários e os afastamentos
registrados no módulo RH.

Fiel ao sistema legado Python (`escala.py` + `modulo_escalas/`).

---

## Fluxo de geração

```
1. Lançar plantões externos do mês
   └─ funcionário + tipo (sigla) + data(s)
   └─ o sistema consultará esses registros ao gerar a escala

2. Gerar Escala Provisória
   └─ sistema monta bloqueios: plantões externos + afastamentos (rh_afastamentos)
   └─ aplica equidade proporcional por grupo (escrivão, operacional, fechar)
   └─ finais de semana: todos os campos vazios, linhas visíveis
   └─ feriados: campo operacional = "FERIADO" / "PONTO FACULTATIVO", demais vazios
   └─ delegada(o) bloqueada(o): campo mostra combobox com pool de externos

3. Conferência
   └─ grade disponível na tela (7 colunas, uma linha por dia)
   └─ disponível para impressão em PDF

4. Ajustes pontuais
   └─ operador edita diretamente qualquer célula da grade
   └─ para dias com delegado(a) bloqueado(a): seleciona manualmente no combobox
      (pool = rh_delegados_externos)

5. Gerar Escala Definitiva (Salvar)
   └─ salva todas as linhas na tabela escalas_mensal com versao = MAX + 1
   └─ "Carregar Escala Salva" carrega sempre a versão mais recente
```

---

## Tabelas

### `grom.escalas_plantoes_externos` — catálogo de tipos de plantão

Um registro por tipo. Pré-populado com os 8 tipos do sistema legado.

| Campo     | Descrição                                            |
|-----------|------------------------------------------------------|
| `sigla`   | Código curto único (ex.: `PLN`, `PLD`, `CADN`)      |
| `nome`    | Descrição completa                                   |
| `regra`   | `MESMO_DIA` / `DIA_SEGUINTE` / `AMBOS`              |
| `unidade` | Unidade/local (opcional)                             |

**Tipos pré-cadastrados:**

| Sigla   | Nome                          | Regra          | Dias bloqueados               |
|---------|-------------------------------|----------------|-------------------------------|
| CADD    | Cartório Adicional de Dia     | `MESMO_DIA`    | Dia do plantão                |
| CADN    | Cartório Adicional de Noite   | `AMBOS`        | Dia do plantão + dia seguinte |
| DDM24H  | DDM 24 horas                  | `AMBOS`        | Dia do plantão + dia seguinte |
| ESCOLTA | Escolta                       | `MESMO_DIA`    | Dia do plantão                |
| PLD     | Plantão de Dia                | `MESMO_DIA`    | Dia do plantão ¹              |
| PLN     | Plantão de Noite              | `AMBOS`        | Dia do plantão + dia seguinte |
| RD      | Regime de Dia                 | `MESMO_DIA`    | Dia do plantão                |
| RN      | Regime de Noite               | `DIA_SEGUINTE` | Apenas o dia seguinte         |

¹ **PLD especial**: não bloqueia funcionário com cargo `DEL` (regra na camada de aplicação).

### `grom.escalas_plantoes_funcionarios` — atribuições por funcionário/data

Um registro por funcionário, tipo de plantão e data.

| Campo                | Descrição                                          |
|----------------------|----------------------------------------------------|
| `data`               | Data do plantão externo                            |
| `funcionario_id`     | FK → `rh_funcionarios`                             |
| `plantao_externo_id` | FK → `escalas_plantoes_externos`                   |

Constraint UNIQUE em `(funcionario_id, plantao_externo_id, data)`.

**Janela de busca para bloqueios**: do último dia do mês anterior até o último dia
do mês corrente — garante que PLN/CADN/RN do último dia do mês anterior bloqueiem
corretamente o dia 1 do mês gerado.

### `grom.escalas_mensal` — grade diária (uma linha por dia × versão)

Espelho fiel da tabela `escala_mensal` do SQLite legado.

| Campo            | Descrição                                                         |
|------------------|-------------------------------------------------------------------|
| `data`           | Data (`YYYY-MM-DD`)                                               |
| `mes`, `ano`     | Mês e ano da escala                                               |
| `versao`         | Versão numérica (1, 2, …) — maior = mais recente                 |
| `escrivao`       | Nome do escrivão (snapshot de texto)                              |
| `operacional`    | Nome do operacional, `"FERIADO"` ou `"PONTO FACULTATIVO"`        |
| `fechar`         | Nome de quem fecha (escrivão ou operacional do dia)               |
| `delegada`       | Nome do delegado interno **ou** do externo selecionado no combobox|
| `plantao_externo`| Texto composto: `"Nome (SIGLA), Nome2 (SIGLA2)"`                 |

UNIQUE em `(data, versao)`.

**Nomes gravados como texto simples** (snapshot), sem FK para funcionários — idêntico
ao comportamento do legado Python.

---

## Colunas da grade (exibição)

A escala exibe **7 colunas**, na ordem exata do sistema legado:

| # | Coluna            | Conteúdo                                              |
|---|-------------------|-------------------------------------------------------|
| 1 | Data              | DD/MM                                                 |
| 2 | Dia               | Abreviação do dia da semana (Seg, Ter…)               |
| 3 | Escrivão          | Nome do escrivão escalado                             |
| 4 | Operacional       | Nome do operacional ou "FERIADO"/"PONTO FACULTATIVO"  |
| 5 | Fechar            | Nome de quem fecha (escrivão ou operacional do dia)   |
| 6 | Delegada(o)       | Nome do delegado ou `<select>` quando bloqueado       |
| 7 | Plantões Externos | Texto: quem está em plantão externo naquele dia       |

---

## Comportamentos especiais de linha

**Finais de semana**
- Linha visível na grade
- Todos os campos (Escrivão, Operacional, Fechar, Delegada(o), Plantões Externos) vazios

**Feriados**
- Campo `operacional` = `"FERIADO"` (feriado nacional) ou `"PONTO FACULTATIVO"`
  (conforme descrição do feriado conter "FACULT")
- Campos `escrivao`, `fechar`, `delegada` vazios

**Delegado(a) bloqueado(a)**
- Quando o(a) Delegado(a) titular está impedido(a) (afastamento ou plantão externo
  que bloqueia o cargo DEL), o campo `Delegada(o)` na grade exibe um `<select>`
  com os nomes do pool `rh_delegados_externos`
- A seleção é **manual, dia a dia**, pelo operador durante a conferência
- Nenhum substituto é atribuído automaticamente
- O nome selecionado é salvo como texto simples no campo `delegada` da tabela
  `escalas_mensal` ao gravar a definitiva
- Essa regra aplica-se **exclusivamente ao cargo DEL**

---

## Observações do mês

Exibidas no rodapé da grade (tela e PDF). Geradas dinamicamente a partir de:

- **Afastamentos** (`rh_afastamentos`): formato `"De DD/MM a DD/MM, [Nome] estará de [Tipo]."`
- **Feriados** do período

Não são armazenadas como campo separado — são computadas no momento da exibição/impressão.

O período de impedimento da Delegada(o) deve constar nessa seção
(ex.: *"De 15/03 a 31/03, Dra. Laura Mendes estará de Férias."*).

---

## Versionamento

- Cada chamada a "Gerar Escala Definitiva (Salvar)" incrementa `versao = MAX(versao) + 1`
- "Carregar Escala Salva" carrega a versão com maior `versao` para o mês/ano selecionado
- Edições pontuais feitas na tela são salvas na versão corrente antes de gravar a definitiva

---

## Equidade

Gerenciada pelo módulo `escala_equidade.py` (legado) — a aplicar na camada PHP:

- Escrivães competem apenas com escrivães; operacionais apenas com operacionais
- Delegado tem regras próprias, nunca entra em equidade com os demais grupos
- **Score** = `atribuições / disponibilidade` — menor score = maior prioridade
- **Desempate**: rotação circular a partir de onde o mês anterior parou
- **Fechar**: deve ser o Escrivão ou Operacional do próprio dia; equidade por contagem absoluta

---

## Views disponíveis

| View                          | Propósito                                                       |
|-------------------------------|------------------------------------------------------------------|
| `v_escala_ultima_versao`      | Versão mais recente por mês/ano (para carregar por padrão)      |
| `v_escala_plantoes_mes`       | Plantões do mês com nome do funcionário, sigla e regra          |

---

## Integração com módulo RH

| Tabela RH                       | Uso no módulo Escalas                                       |
|---------------------------------|-------------------------------------------------------------|
| `rh_funcionarios`               | Fonte dos nomes e cargos para geração da escala             |
| `rh_afastamentos`               | Bloqueia funcionários na janela do mês; gera observações    |
| `rh_delegados_externos`         | Pool para o combobox de substituição manual do(a) DEL       |

---

## Segurança e controle de acesso

| Ação                             | Perfil mínimo     |
|----------------------------------|-------------------|
| Visualizar escala                | `consulta`        |
| Lançar plantão externo           | `operador`        |
| Gerar escala provisória          | `operador`        |
| Ajustar campos individualmente   | `operador`        |
| Gerar escala definitiva (salvar) | `gestor`          |
| Imprimir escala                  | `consulta`        |

