# INSTRUÇÕES: Executar no Console OCI Cloud Shell

## Passo 1: Abrir a OCI Cloud Shell
- Acesse https://cloud.oracle.com/
- Faça login com sua conta OCI
- No canto superior direito, clique no ícone >_ (Cloud Shell)
- Aguarde a Cloud Shell carregar (leva alguns segundos)

## Passo 2: Copiar e executar comando SSH no Cloud Shell

Copie EXATAMENTE este comando e cole no Cloud Shell:

```bash
ssh -o StrictHostKeyChecking=no opc@163.176.144.245 << 'ENDSCRIPT'

# === CORRIGIR DOCKERFILE ===
echo ">>> Corrigindo Dockerfile..."

DOCKERFILE="/opt/grom/grom_web_php/infra/php/Dockerfile"
cp "$DOCKERFILE" "$DOCKERFILE.bak"

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

# === RECONSTRUIR DOCKER ===
echo ""
echo ">>> Reconstruindo Docker Compose (pode levar 5-10 minutos)..."
cd /opt/grom/grom_web_php/infra

docker compose --env-file .env.production -f docker-compose.prod.yml down -v 2>/dev/null || true
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build

echo "✓ Docker Compose iniciado"

# === AGUARDAR POSTGRESQL ===
echo ""
echo ">>> Aguardando PostgreSQL ficar pronto (até 5 minutos)..."
for i in {1..60}; do
  if docker compose --env-file .env.production -f docker-compose.prod.yml exec -T postgres pg_isready -U postgres >/dev/null 2>&1; then
    echo "✓ PostgreSQL pronto na tentativa $i"
    break
  fi
  echo "  Tentativa $i/60..."
  sleep 5
done

# === EXECUTAR MIGRAÇÕES ===
echo ""
echo ">>> Executando migrações Laravel..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan migrate --force

echo ""
echo ">>> Executando database seeders..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan db:seed --force

echo ""
echo ">>> Limpando cache de otimização..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan optimize:clear

# === STATUS FINAL ===
echo ""
echo ">>> Status dos containers (deve mostrar 5 em UP):"
docker compose --env-file .env.production -f docker-compose.prod.yml ps

echo ""
echo "=== DEPLOY COMPLETADO COM SUCESSO ==="
echo ""
echo "Acesse: https://grom.seg.br"
echo ""

ENDSCRIPT
```

## Passo 3: Pressionar Enter e Aguardar

O processo levará:
- ~10-15 min: Docker build (recompilando images com Redis)
- ~2 min: Migrações e seeders
- **Total: ~15-20 minutos**

Você verá mensagens de progresso como:
```
✓ Dockerfile corrigido
✓ Docker Compose iniciado
✓ PostgreSQL pronto na tentativa 5
✓ Status dos containers (deve mostrar 5 em UP)
=== DEPLOY COMPLETADO COM SUCESSO ===
```

## Passo 4: Validar Deployment

Depois que terminar, execute no Cloud Shell:

```bash
# Testar HTTPS
curl -I https://grom.seg.br

# Testar página de login
curl https://grom.seg.br/login
```

Deve retornar HTTP 200.

---

## Se der erro durante o build:

Se o Docker build falhar novamente com mensagem sobre autoconf, execute:

```bash
ssh -o StrictHostKeyChecking=no opc@163.176.144.245 "docker compose --env-file /opt/grom/grom_web_php/infra/.env.production -f /opt/grom/grom_web_php/infra/docker-compose.prod.yml logs scheduler 2>&1 | tail -30"
```

E compartilhe a saída (últimas 30 linhas).

---

## Credenciais Admin

Login: `admin`
Senha: `03031981Gr**`
URL: `https://grom.seg.br`
