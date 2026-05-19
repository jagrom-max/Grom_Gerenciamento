#!/bin/bash
# Deploy GROM - Execute na OCI Cloud Shell

VPS_IP="163.176.144.245"
SSH_OPTS="-o StrictHostKeyChecking=no"

echo ">>> Conectando à VPS em $VPS_IP..."
ssh $SSH_OPTS opc@$VPS_IP << 'REMOTE_DEPLOY'

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
echo ">>> Reconstruindo Docker Compose..."
cd /opt/grom/grom_web_php/infra

# Limpar containers antigos
docker compose --env-file .env.production -f docker-compose.prod.yml down -v 2>/dev/null || true

# Build e start (vai levar 10-15 minutos)
echo "  [Aguarde: Docker build pode levar 10-15 minutos...]"
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build

echo "✓ Docker iniciado"

echo ""
echo ">>> Aguardando PostgreSQL..."
for i in {1..60}; do
  if docker compose --env-file .env.production -f docker-compose.prod.yml exec -T postgres pg_isready -U postgres >/dev/null 2>&1; then
    echo "✓ PostgreSQL pronto (tentativa $i)"
    break
  fi
  echo "  [$i/60] Aguardando..."
  sleep 5
done

echo ""
echo ">>> Executando migrações..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan migrate --force

echo ""
echo ">>> Executando seeders..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan db:seed --force

echo ""
echo ">>> Limpando cache..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan optimize:clear

echo ""
echo ">>> Status dos containers:"
docker compose --env-file .env.production -f docker-compose.prod.yml ps

echo ""
echo "========================================"
echo "✓ DEPLOY COMPLETADO COM SUCESSO!"
echo "========================================"
echo ""
echo "Acesse: https://grom.seg.br"
echo "Login: admin"
echo "Senha: 03031981Gr**"
echo ""

REMOTE_DEPLOY

echo ""
echo "✓ Script de deploy concluído"
