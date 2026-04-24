# Deploy Oracle VPS

## Alvo

- VPS Linux com Docker Engine e Docker Compose plugin
- dominio `grom.seg.br`
- terminacao TLS no Nginx do host
- stack da aplicacao isolada em containers
- PostgreSQL e Redis sem exposicao publica

## Arquivos de apoio no repositorio

- `infra/docker-compose.prod.yml`
- `infra/.env.production.example`
- `infra/nginx/grom.seg.br.conf.example`
- `infra/scripts/deploy-prod.sh`
- `infra/scripts/healthcheck-prod.sh`
- `infra/scripts/backup-prod.sh`
- `docs/11_runbook_implantacao_oracle_vps.html`

## Topologia recomendada

- Nginx do host: recebe `80/443`, responde ao Let's Encrypt e faz proxy para `127.0.0.1:8080`
- Container `nginx`: atende a aplicacao Laravel em HTTP interno
- Container `app`: PHP-FPM
- Container `worker`: filas Redis
- Container `scheduler`: agenda Laravel
- Container `postgres`: banco canonico
- Container `redis`: sessao, cache e fila

## Preparacao da VPS

Pacotes base no Ubuntu 24.04:

```bash
sudo apt update
sudo apt install -y ca-certificates curl gnupg nginx certbot python3-certbot-nginx git ufw
```

Docker:

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker
docker --version
docker compose version
```

Firewall:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

## Publicacao do codigo

```bash
sudo mkdir -p /opt/grom
sudo chown $USER:$USER /opt/grom
git clone <REPOSITORIO> /opt/grom/grom_web_php
cd /opt/grom/grom_web_php
```

## Preparacao do ambiente

```bash
cd /opt/grom/grom_web_php/infra
cp .env.production.example .env.production
```

Ajustes obrigatorios em `.env.production`:

- `APP_URL=https://grom.seg.br`
- `APP_KEY` gerada no proprio servidor
- `DB_PASSWORD` forte
- `GROM_BOOTSTRAP_ADMIN_PASSWORD` forte
- `SESSION_DOMAIN=grom.seg.br`
- `GROM_LEGACY_ANALISE_SYNC_ENABLED=false`
- `GROM_PILOT_DEMO_SEED_ENABLED=false`

Gerar `APP_KEY` sem alterar o `.env` do ambiente local:

```bash
docker run --rm -v /opt/grom/grom_web_php/runtime:/app -w /app php:8.4-cli-alpine php artisan key:generate --show
```

Copie o valor retornado para `APP_KEY` em `infra/.env.production`.

## Subida inicial da stack

Modo recomendado com baixa interferencia humana:

```bash
cd /opt/grom/grom_web_php/infra
bash ./scripts/deploy-prod.sh
```

Modo manual, se necessario:

```bash
cd /opt/grom/grom_web_php/infra
docker compose -f docker-compose.prod.yml up -d --build
```

Migracoes e seed estrutural:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
```

Observacao:

- o `DatabaseSeeder` nao injeta mais a seed demo em producao por padrao
- a sincronizacao legada de produtividade so roda se a base legada estiver configurada e acessivel

## Nginx do host e HTTPS

Instale o vhost do host com base em `infra/nginx/grom.seg.br.conf.example`:

```bash
sudo cp /opt/grom/grom_web_php/infra/nginx/grom.seg.br.conf.example /etc/nginx/sites-available/grom.seg.br.conf
sudo ln -s /etc/nginx/sites-available/grom.seg.br.conf /etc/nginx/sites-enabled/grom.seg.br.conf
sudo nginx -t
sudo systemctl reload nginx
```

Emitir certificado:

```bash
sudo certbot --nginx -d grom.seg.br
sudo nginx -t
sudo systemctl reload nginx
```

## Validacao pos-deploy

Modo recomendado:

```bash
cd /opt/grom/grom_web_php/infra
bash ./scripts/healthcheck-prod.sh
```

Healthchecks basicos:

```bash
cd /opt/grom/grom_web_php/infra
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=100 app
docker compose -f docker-compose.prod.yml logs --tail=100 worker
docker compose -f docker-compose.prod.yml logs --tail=100 scheduler
```

Aplicacao:

```bash
curl -I http://127.0.0.1:8080/login
curl -I https://grom.seg.br/login
```

Carga curta via host ou estacao autorizada:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\load-test.ps1 -BaseUrl https://grom.seg.br -FailOnThreshold
```

## Atualizacao de versao

```bash
cd /opt/grom/grom_web_php
git pull
cd infra
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
```

## Backup minimo recomendado

Modo recomendado:

```bash
cd /opt/grom/grom_web_php/infra
bash ./scripts/backup-prod.sh
```

Banco PostgreSQL:

```bash
cd /opt/grom/grom_web_php/infra
docker compose -f docker-compose.prod.yml exec -T postgres pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" > /opt/grom/backups/grom_web_$(date +%F).sql
```

Volumes:

- `postgres_data`
- `redis_data`
- `runtime/storage`

## Checklist de corte

- HTTPS valido em `grom.seg.br`
- `APP_DEBUG=false`
- `GROM_PILOT_DEMO_SEED_ENABLED=false`
- `GROM_LEGACY_ANALISE_SYNC_ENABLED=false` em operacao normal
- `docker compose ps` sem servicos unhealthy
- restore de backup testado
- carga curta sem HTTP 500

Para contingencia operacional, usar [docs/10_rollback_oracle_vps.md](docs/10_rollback_oracle_vps.md).