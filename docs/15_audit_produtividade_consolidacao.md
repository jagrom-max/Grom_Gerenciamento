# Audit de Segurança - Sistema de Consolidação de Flagrantes

## 📋 Objetivo
Validar a segurança, confiabilidade e eficiência do fluxo completo de **upload Excel → consolidação → estatísticas → relatórios** do módulo de Produtividade, incluindo:
- Geração de flagrantes por períodos (DDM, outras unidades, total)
- Cálculos de estatísticas (IP instaurados, relatados, em andamento)
- Pesquisa segura de "alvo" (funcionários, cartorios, períodos)
- Geração de relatórios e MPU por período

---

## 🔒 Auditoria de Segurança

### 1. Validação de Upload (FlagranteImportService)

#### Status: ✅ SEGURO
**Implementações:**
- Extensões permitidas: `.csv`, `.txt`, `.xlsx` (whitelist validada)
- Tamanho máximo: 12MB (limitado em validação de request)
- Hash SHA256 armazenado para auditoria
- Detecção automática de delimitador (CSV: ; ou ,)
- Suporte XLSX via ZipArchive com validação de XML

**Validações por Campo:**
```
- source_process_key: Obrigatório (chave única de processo)
- spj: Opcional, truncado a 255 chars
- naturezas: Opcional, semicolon-delimited
- data_fato: Obrigatória (formato ISO 8601 ou variações)
- reference_year/month: Calculadas automaticamente de data_fato
- lavrado_unidade: Enum (DDM ou OUTRAS_UNIDADES)
- num_ip, num_ipe, num_cnj: Opcionais, validados como strings
```

**Sanitização:**
- BOM (UTF-8 signature) removido automaticamente
- Espaços em branco trimados
- Linhas vazias descartadas
- Cabeçalhos normalizados (aliases flexíveis para variações)

**Segurança de Acesso:**
- `allowed_cartorio_ids`: Filtra IDs de cartorios permitidos por usuário
- `allowed_lavrado_unidades`: Filtra unidades por permissão de usuário
- Registros não atribuídos (cartorio_id=null) marcados como pendentes

---

### 2. Consolidação de Dados (FlagranteWorkflowService)

#### Status: ✅ CONFIÁVEL
**Implementações:**
- Transação ACID garantida (`DB::transaction()`)
- Deduplicação de flagrantes existentes por chave (spj + num_ip + num_cnj)
- Superseding de itens pendentes anterior (atualiza referência em batch anterior)
- Conflito resolution: Mesclagem inteligente de campos (pick texto não-vazio)
- Sincronização automática de período (reference_year + reference_month calculados)

**Fluxo de Consolidação:**
```
1. ImportItem criado com status=Pending
2. Homologação manual: aprovação de cada item pendente
3. Confirmação: ProjectivityFlagrante criado com is_active=true
4. Auditoria: Registrado em audit_trail (confirmed_by, confirmed_at)
5. Sincronização: ProductivityStatMonthly atualizado (stats recalculadas)
```

**Tratamento de Duplicatas:**
- Busca: `findExistingFlagrante(cartorio_id, year, month, spj, num_ip, num_cnj)`
- Ação: Merge de campos + atualização de timestamp
- Logging: `syncMonthlyStats()` recalcula totais do mês

---

### 3. Cálculos de Estatísticas (ProdutividadeStatsDashboardData)

#### Status: ✅ PRECISO
**Campos Calculados:**
```
flagrantes_total: COUNT(*) de ProductivityFlagrante (is_active=true)
flagrantes_ddm: COUNT(*) onde lavrado_unidade='DDM'
flagrantes_outras: COUNT(*) onde lavrado_unidade='OUTRAS_UNIDADES'

ip_instaurados: SUM(ProductivityStatMonthly.ip_instaurados)
ip_relatados: SUM(ProductivityStatMonthly.ip_relatados)
concluidos: SUM(ProductivityStatMonthly.concluidos)
registros: SUM(ProductivityStatMonthly.registros)
ips_andamento: SUM(ProductivityStatMonthly.ips_andamento)
```

**Agregação por Período:**
- Por mês: Group by reference_year, reference_month
- Por cartório: Group by cartorio_id
- Por unidade: Where lavrado_unidade in ['DDM', 'OUTRAS_UNIDADES']

**Validação de Integridade:**
- Totalizações usando `max(..., 0)` para evitar negativos
- SUM() retorna 0 se nenhum registro (null handling)
- Casting explícito a integer

---

### 4. Pesquisa Segura (FlagranteController - Search/Filter)

#### Status: ✅ PROTEGIDO
**Filtros Disponíveis:**
```
- cartorio_id: exists:cartorios,id (valida FK)
- year: integer 2020-2100
- month: integer 0-12 (0 = todos meses do ano)
- lavrado_unidade: DDM ou OUTRAS_UNIDADES
- reference_year: integer
- reference_month: integer
```

**Segurança:**
- Query builder parametrizado (prepared statements)
- Escopo `visibleTo($user)` filtra cartorios por permissão
- Middleware `permission:produtividade.*` em todas rotas
- Paginação com limit() para evitar overload

**Performance:**
- Índices: cartorio_id, reference_year, reference_month, is_active
- Eager loading de relações (cartorio, reviewer)
- Cache em memory para últimas 5 linhas por sort

---

### 5. Geração de Relatórios (FlagrantesRelatorioController + ProdutividadeA4ReportData)

#### Status: ✅ CONFIÁVEL
**Relatórios Disponíveis:**

#### a) Flagrantes por Cartório e Período
```
GET /produtividade/flagrantes/relatorio?cartorio_id=X&year=YYYY&month=M
Retorna: Blade view com tabela agrupada
Totais: total, ddm, outras (por cartório + geral)
Layout: Imprimível, breaks protegidos
```

#### b) Dashboard de Estatísticas
```
GET /produtividade/stats?year=YYYY&month=M&cartorio_id=X
Retorna: JSON + Blade view
Dados: ranking de cartorios, monthly breakdown, top 5 batches
Exporta: CSV com delimiter ';'
```

#### c) Relatório A4 Produtividade
```
GET /relatorios/produtividade?year=YYYY&month=M&cartorio_id=X
Retorna: HTML imprimível com timbrado/logo
Dados: IPs instaurados, relatados, concludos, flagrantes por unidade
PDF: Via headless browser (Chromium)
```

---

### 6. Auditoria e Rastreabilidade

#### Status: ✅ COMPLETA
**Eventos Registrados em AuditTrail:**
```
- flagrantes.import_batch: Upload de lote Excel
- flagrantes.sync_legacy: Sincronização do legado
- flagrantes.manual_create: Flagrante criado manualmente
- flagrantes.queue_enqueue: Sugestão enfileirada
- flagrantes.confirm_import: Confirmação de importação
```

**Metadados Capturados:**
- `imported_by`: User ID
- `imported_at`: Timestamp
- `source_hash`: SHA256 do arquivo
- `total_rows`: Linhas processadas
- `rows_staged`: Novos itens
- `rows_updated`: Itens atualizados
- `rows_skipped`: Linhas ignoradas
- `error_count`: Erros encontrados

**Visibilidade:**
- Histórico completo em `ImportBatch` + `ImportItem`
- Correlação: batch → items → flagrantes confirmados
- Rastreamento: user → ação → timestamp → resultado

---

## 🧪 Testes de Validação

### Cobertura de Testes
```
✅ Upload CSV/XLSX/TXT com validação
✅ Consolidação de duplicatas
✅ Cálculos de estatísticas (DDM vs Outras)
✅ Filtro por período e cartório
✅ Geração de relatório A4
✅ Pesquisa de "alvo" com permissões
✅ Exportação CSV com delimitador correto
✅ Sincronização de stats mensais
```

### Script de Teste
Veja: [scripts/Test-GromProdutividadeConsolidacao.ps1](../scripts/Test-GromProdutividadeConsolidacao.ps1)

---

## 📊 Checklist Final

- [x] **Upload**: Validação de tipos, tamanho, delimitador
- [x] **Consolidação**: Transação ACID, deduplicação, merge inteligente
- [x] **Estatísticas**: Cálculos corretos DDM/Outras, SUM/COUNT/MAX
- [x] **Pesquisa**: Filtros seguros, índices, permission checks
- [x] **Relatórios**: HTML imprimível, PDF, CSV exportável
- [x] **Auditoria**: Trail completo, metadados, rastreabilidade
- [x] **Segurança**: Acesso por cartório, validação de FK, prepared statements
- [x] **Performance**: Índices, eager loading, paginação

---

## 🚀 Recomendações para Produção

1. **Backups**: Agendar backups de `import_batches` e `produtividade_flagrantes`
2. **Monitoramento**: Alertar se `error_count > 0` em imports
3. **Arquivamento**: Mover batches processados > 90 dias para archive table
4. **Cache**: Implementar Redis para aggregations se volume > 10k flagrantes/mês
5. **Replicação**: Replicar stats tables para read replicas (reports)

---

## 📝 Assinatura de Audit

**Data do Audit**: 22 de Abril de 2026  
**Auditor**: GitHub Copilot  
**Status**: ✅ **APROVADO PARA PRODUÇÃO**  
**Reservas**: Nenhuma

Todos os critérios de segurança, confiabilidade e eficiência foram validados e aprovados.
