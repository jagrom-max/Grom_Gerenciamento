#!/bin/bash
# fix-docker-and-deploy.sh
# Executar na OCI Cloud Shell para corrigir o Dockerfile e completar o deploy

set -e

VPS_IP="163.176.144.245"
REPO_DIR="/opt/grom/grom_web_php"
INFRA_DIR="${REPO_DIR}/infra"
ENV_FILE="${INFRA_DIR}/.env.production"

echo ">>> Conectando ao VPS via SSH..."
ssh -o StrictHostKeyChecking=no opc@"${VPS_IP}" << 'REMOTE_SCRIPT'

echo ">>> Corrigindo Dockerfile..."

# Ler o Dockerfile atual
DOCKERFILE="/opt/grom/grom_web_php/infra/php/Dockerfile"

# Criar backup
cp "${DOCKERFILE}" "${DOCKERFILE}.bak"

# Substituir a seção de PECL redis
cat > "${DOCKERFILE}" << 'EOF'
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

# Verificar conteúdo
echo ">>> Conteúdo do Dockerfile:"
cat "${DOCKERFILE}"

# Reconstruir o stack Docker
echo -e "\n>>> Reconstruindo Docker Compose stack..."
cd /opt/grom/grom_web_php/infra

# Remover containers antigos para forçar rebuild limpo
docker compose --env-file .env.production -f docker-compose.prod.yml down -v 2>/dev/null || true

# Build e start
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build

echo "✓ Docker Compose iniciado"

# Aguardar PostgreSQL ficar pronto
echo -e "\n>>> Aguardando PostgreSQL ficar pronto..."
for i in {1..60}; do
  if docker compose --env-file .env.production -f docker-compose.prod.yml exec -T postgres pg_isready -U postgres 2>/dev/null; then
    echo "✓ PostgreSQL pronto"
    break
  fi
  echo "  Tentativa $i/60..."
  sleep 5
done

# Executar migrações
echo -e "\n>>> Executando migrações Laravel..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan migrate --force

echo -e "\n>>> Executando seeders..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan db:seed --force

echo -e "\n>>> Limpando cache de otimização..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan optimize:clear

# Status dos containers
echo -e "\n>>> Status dos containers:"
docker compose --env-file .env.production -f docker-compose.prod.yml ps

echo -e "\n>>> Deploy completado com sucesso!"

REMOTE_SCRIPT

echo "✓ Deploy remoto concluído"
