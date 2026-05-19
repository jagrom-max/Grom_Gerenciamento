# 🚀 Deploy GROM - OCI Cloud Shell

Como a VPS não está acessível via SSH da sua máquina local, você precisa usar a **OCI Cloud Shell** (que já tem acesso SSH autorizado).

## Passo 1: Abrir OCI Cloud Shell

1. Acesse: https://cloud.oracle.com/
2. Faça login
3. Clique no ícone **>_** (Cloud Shell) no canto superior direito
4. Aguarde abrir (leva ~10 segundos)

## Passo 2: Copiar e Executar o Script

Na OCI Cloud Shell, copie e **cole exatamente** este comando:

```bash
bash << 'SCRIPT'
#!/bin/bash
set -e

VPS_IP="137.131.210.199"
SSH_OPTS="-o StrictHostKeyChecking=no"

echo ">>> Conectando à VPS..."
ssh $SSH_OPTS opc@$VPS_IP << 'REMOTE'

echo ">>> Corrigindo Dockerfile..."
DOCKERFILE="/opt/grom/grom_web_php/infra/php/Dockerfile"
cp "$DOCKERFILE" "$DOCKERFILE.bak" 2>/dev/null || true

cat > "$DOCKERFILE" << 'EOF'
ARG PHP_IMAGE=php:8.4-fpm-alpine
FROM ${PHP_IMAGE}

RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    unzip \
    zip

RUN docker-php-ext-install \
    intl \
    pdo \
    pdo_pgsql \
    zip

RUN apk add --no-cache \
    autoconf \
    gcc \
    libc-dev \
    make && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    apk del autoconf gcc libc-dev make

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
EOF

echo "✓ Dockerfile corrigido"

echo ""
echo ">>> Reconstruindo Docker (aguarde 10-15 min)..."
cd /opt/grom/grom_web_php/infra

docker compose --env-file .env.production -f docker-compose.prod.yml down -v 2>/dev/null || true

echo "  [Fazendo build...]"
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build

echo "✓ Docker iniciado"

echo ""
echo ">>> Aguardando PostgreSQL..."
for i in {1..60}; do
  if docker compose --env-file .env.production -f docker-compose.prod.yml exec -T postgres pg_isready -U postgres >/dev/null 2>&1; then
    echo "✓ PostgreSQL pronto"
    break
  fi
  sleep 5
done

echo ""
echo ">>> Migrações..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan db:seed --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan optimize:clear

echo ""
docker compose --env-file .env.production -f docker-compose.prod.yml ps

echo ""
echo "========================================"
echo "✓ DEPLOY COMPLETO!"
echo "========================================"
echo ""
echo "URL: https://grom.seg.br"
echo "Login: admin"
echo "Senha: 03031981Gr**"
echo ""

REMOTE

SCRIPT
```

## Passo 3: Aguardar Conclusão

O processo vai levar:
- **2-3 min**: Conectar SSH
- **10-15 min**: Docker build
- **2-3 min**: Migrações e seeders
- **Total: ~15-20 minutos**

Você verá mensagens como:
```
✓ Dockerfile corrigido
✓ Docker iniciado
✓ PostgreSQL pronto
✓ DEPLOY COMPLETO!
```

## Passo 4: Testar

Quando terminar, acesse: **https://grom.seg.br**

| Campo | Valor |
|-------|-------|
| **Login** | admin |
| **Senha** | 03031981Gr** |

---

## ⚠️ Se der erro

Se o Docker build falhar de novo, execute na Cloud Shell:

```bash
ssh -o StrictHostKeyChecking=no opc@163.176.144.245 'docker compose -f /opt/grom/grom_web_php/infra/docker-compose.prod.yml logs -f scheduler 2>&1 | tail -30'
```

E compartilhe as últimas 30 linhas do erro.
