# Checklist de Homologação e Go-Live

Checklist objetivo para levar o GROM Web ao ar na Oracle VPS com dominio `grom.seg.br`.

Referências principais:

- [docs/08_deploy_oracle_vps.md](docs/08_deploy_oracle_vps.md)
- [docs/10_rollback_oracle_vps.md](docs/10_rollback_oracle_vps.md)
- [infra/docker-compose.prod.yml](infra/docker-compose.prod.yml)
- [infra/.env.production.example](infra/.env.production.example)
- [infra/nginx/grom.seg.br.conf.example](infra/nginx/grom.seg.br.conf.example)
- [scripts/load-test.ps1](scripts/load-test.ps1)

## Status desta execução (24/04/2026)

- [x] Baseline Git criado com `.gitignore` de produção/segurança.
- [x] `php artisan migrate --force` executado com sucesso.
- [x] Teste focal de RH (`RhAccessTest`) aprovado.
- [x] Quality gate executado e aprovado (`101 passed`, `composer audit` sem vulnerabilidades).
- [ ] Rodar carga curta em `https://grom.seg.br` após publicação externa.
- [ ] Fechar evidências finais de homologação e assinatura de go-live.

## 1. Pré-deploy

- [ ] VPS Linux acessível via SSH e com horário correto.
- [ ] Docker Engine e `docker compose` instalados.
- [ ] Nginx, Certbot e UFW instalados no host.
- [ ] DNS de `grom.seg.br` apontando para o IP público da VPS.
- [ ] Portas `80` e `443` liberadas no firewall.
- [ ] Repositório publicado em `/opt/grom/grom_web_php`.
- [ ] Quality gate local aprovado com `scripts/Invoke-GromQualityGate.ps1` (testes + audit; smoke opcional).

## 2. Ambiente de produção

- [ ] Arquivo `infra/.env.production` criado a partir de `infra/.env.production.example`.
- [ ] `APP_ENV=production`.
- [ ] `APP_DEBUG=false`.
- [ ] `APP_URL=https://grom.seg.br`.
- [ ] `APP_KEY` gerada no servidor.
- [ ] `DB_CONNECTION=pgsql`.
- [ ] `DB_PASSWORD` definida com valor forte.
- [ ] `SESSION_DRIVER=redis`.
- [ ] `CACHE_STORE=redis`.
- [ ] `QUEUE_CONNECTION=redis`.
- [ ] `SESSION_DOMAIN=grom.seg.br`.
- [ ] `SESSION_SECURE_COOKIE=true`.
- [ ] `GROM_PILOT_DEMO_SEED_ENABLED=false`.
- [ ] `GROM_LEGACY_ANALISE_SYNC_ENABLED=false` para operação normal.
- [ ] `GROM_BOOTSTRAP_ADMIN_PASSWORD` definida com valor forte.

## 3. Subida da stack

- [ ] `docker compose -f docker-compose.prod.yml up -d --build` executado sem erro.
- [ ] `docker compose -f docker-compose.prod.yml ps` sem serviços `unhealthy`.
- [ ] Container `app` iniciado.
- [ ] Container `worker` iniciado.
- [ ] Container `scheduler` iniciado.
- [ ] Container `postgres` iniciado.
- [ ] Container `redis` iniciado.
- [ ] Container `nginx` interno iniciado.

## 4. Banco e bootstrap

- [ ] `php artisan migrate --force` executado no container `app`.
- [ ] `php artisan db:seed --force` executado no container `app`.
- [ ] Confirmado que a seed demo não entrou indevidamente.
- [ ] Confirmado que a sincronização legada não rodou sem necessidade.
- [ ] `php artisan optimize:clear` executado após migração.

## 5. HTTPS e proxy reverso

- [ ] Vhost do host instalado a partir de `infra/nginx/grom.seg.br.conf.example`.
- [ ] `nginx -t` no host sem erro.
- [ ] Certificado emitido com `certbot`.
- [ ] Redirecionamento HTTP → HTTPS ativo.
- [ ] `curl -I https://grom.seg.br/login` retorna sucesso.

## 6. Validação funcional mínima

- [ ] Tela de login abre em `https://grom.seg.br/login`.
- [ ] Login com administrador funciona.
- [ ] Dashboard abre sem erro 500.
- [ ] RH/Admin abre sem erro 500.
- [ ] Escala mensal abre sem erro 500.
- [ ] Plantões abre sem erro 500.
- [ ] Relatório A4 de produtividade abre.
- [ ] PDF de produtividade baixa corretamente.
- [ ] Auditoria registra login/logout.

## 7. Sessão, fila e agenda

- [ ] Sessões persistem corretamente via Redis.
- [ ] Worker processa filas sem erro nos logs.
- [ ] Scheduler executa sem falhas recorrentes.
- [ ] Não há acúmulo anormal de jobs falhos.

## 8. Carga e estabilidade

- [ ] Carga curta executada com `scripts/load-test.ps1` apontando para `https://grom.seg.br`.
- [ ] Sem HTTP 500 durante a rodada.
- [ ] Login responde de forma estável.
- [ ] Dashboard responde de forma estável.
- [ ] PDF responde de forma estável.
- [ ] Resultado da carga arquivado para referência.

## 9. Segurança operacional

- [ ] Banco PostgreSQL não exposto publicamente.
- [ ] Redis não exposto publicamente.
- [ ] Segredos mantidos fora do repositório.
- [ ] `APP_DEBUG=false` validado no ambiente real.
- [ ] Cookies seguros ativos em HTTPS.
- [ ] Acesso administrativo restrito ao necessário.

## 10. Backup e contingência

- [ ] `pg_dump` executado com sucesso.
- [ ] Diretório de backup existe na VPS.
- [ ] Backup fora do container armazenado no host.
- [ ] Restore testado ou procedimento documentado.
- [ ] Plano de rollback definido e revisado com base em [docs/10_rollback_oracle_vps.md](docs/10_rollback_oracle_vps.md).

## 11. Critério de go-live

- [ ] HTTPS válido em `grom.seg.br`.
- [ ] Homologação funcional aprovada.
- [ ] Carga curta aprovada sem erro 500.
- [ ] Backup validado.
- [ ] Logs e healthchecks revisados.
- [ ] Responsável pelo corte definido.
- [ ] Janela de implantação definida.

## 12. Pós-go-live imediato

- [ ] Revisar logs de `app`, `worker` e `scheduler` após a abertura.
- [ ] Confirmar novo login real de usuário autorizado.
- [ ] Confirmar geração de pelo menos um PDF em produção.
- [ ] Confirmar que não houve seed demo ou sync legado indevido.
- [ ] Registrar data/hora do corte e versão implantada.