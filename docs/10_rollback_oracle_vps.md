# Rollback Oracle VPS

Roteiro objetivo para reverter uma implantação do GROM Web na VPS quando houver falha funcional, erro de migração, indisponibilidade ou degradação relevante após o deploy.

Referências:

- [docs/08_deploy_oracle_vps.md](docs/08_deploy_oracle_vps.md)
- [docs/09_checklist_homologacao_go_live.md](docs/09_checklist_homologacao_go_live.md)
- [infra/docker-compose.prod.yml](infra/docker-compose.prod.yml)

## 1. Quando acionar rollback

Execute rollback quando ocorrer pelo menos um dos cenários abaixo:

- erro 500 persistente em login, dashboard, RH, escalas ou relatórios
- migração com falha ou efeito colateral em produção
- fila parada sem recuperação rápida
- regressão crítica de autenticação ou autorização
- carga curta pós-deploy com falha estrutural
- perda de funcionalidade necessária para operação diária

## 2. Pré-condições mínimas

- acesso SSH à VPS
- acesso ao diretório `/opt/grom/grom_web_php`
- backup SQL mais recente disponível no host
- identificação do commit estável anterior
- janela de intervenção autorizada

## 3. Evidência antes da reversão

Registrar antes de alterar qualquer coisa:

```bash
cd /opt/grom/grom_web_php/infra
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=200 app > /opt/grom/backups/rollback-app-$(date +%F-%H%M%S).log
docker compose -f docker-compose.prod.yml logs --tail=200 worker > /opt/grom/backups/rollback-worker-$(date +%F-%H%M%S).log
docker compose -f docker-compose.prod.yml logs --tail=200 scheduler > /opt/grom/backups/rollback-scheduler-$(date +%F-%H%M%S).log
```

Backup emergencial do banco antes do rollback:

```bash
mkdir -p /opt/grom/backups
cd /opt/grom/grom_web_php/infra
docker compose -f docker-compose.prod.yml exec -T postgres sh -lc 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB"' > /opt/grom/backups/pre-rollback-$(date +%F-%H%M%S).sql
```

## 4. Estratégia A: rollback só de aplicação

Use esta estratégia quando o problema estiver no código ou na configuração da aplicação e não houver necessidade de restaurar o banco.

```bash
cd /opt/grom/grom_web_php
git log --oneline -n 10
git checkout <COMMIT_ESTAVEL>
cd infra
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
```

Validação imediata:

```bash
curl -I http://127.0.0.1:8080/login
curl -I https://grom.seg.br/login
docker compose -f docker-compose.prod.yml ps
```

## 5. Estratégia B: rollback com restauração de banco

Use esta estratégia quando a implantação tiver alterado o esquema ou os dados de forma incompatível.

Parar a aplicação para evitar nova escrita:

```bash
cd /opt/grom/grom_web_php/infra
docker compose -f docker-compose.prod.yml stop nginx app worker scheduler
```

Reposicionar o código para a versão estável:

```bash
cd /opt/grom/grom_web_php
git checkout <COMMIT_ESTAVEL>
```

Restaurar o banco a partir do dump estável:

```bash
cd /opt/grom/grom_web_php/infra
cat /opt/grom/backups/<BACKUP_ESTAVEL>.sql | docker compose -f docker-compose.prod.yml exec -T postgres sh -lc 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
```

Subir novamente a aplicação:

```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
```

## 6. Estratégia C: manutenção temporária sem rollback completo

Use quando o defeito for reversível em poucos minutos e ainda não houver decisão de rollback integral.

```bash
cd /opt/grom/grom_web_php/infra
docker compose -f docker-compose.prod.yml exec app php artisan down --render="errors::503"
```

Após correção ou rollback:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan up
```

## 7. Validação pós-rollback

Executar após qualquer uma das estratégias:

```bash
cd /opt/grom/grom_web_php/infra
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=100 app
curl -I https://grom.seg.br/login
```

Checklist mínimo pós-rollback:

- login responde
- dashboard responde
- RH/Admin responde
- escalas responde
- PDF principal responde
- worker e scheduler sem erro repetitivo

## 8. Encerramento do incidente

Registrar no fechamento:

- horário da falha
- versão implantada que falhou
- commit restaurado
- se houve restauração de banco
- resultado da validação pós-rollback
- próximo passo: corrigir e replanejar nova janela

## 9. Recomendação operacional

Antes de cada deploy em produção, registrar previamente:

- hash do commit atual em produção
- nome do arquivo de backup que servirá de ponto de restauração
- responsável técnico pela reversão
- prazo máximo aceitável para decidir entre correção rápida e rollback