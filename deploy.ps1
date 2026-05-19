#!/usr/bin/env powershell
# Deploy GROM final com SSH automático

param(
    [string]$VpsIp = "163.176.144.245",
    [string]$OciInstanceId = "ocid1.instance.oc1.sa-saopaulo-1.antxeljren3rd7qcj5d5vrfbp2f55cxm6glmhjen63rcsfiqi47ai3wvyjaq"
)

$ErrorActionPreference = "Stop"

Write-Host "`n=== GROM Deploy via SSH ===" -ForegroundColor Cyan

$HomeDir = [System.Environment]::GetFolderPath('UserProfile')
$SshDir = Join-Path $HomeDir ".ssh"
$PrivateKeyPath = Join-Path $SshDir "grom_deploy_rsa"
$PublicKeyPath = "$PrivateKeyPath.pub"

# Criar pasta .ssh se nao existir
if (-not (Test-Path $SshDir)) {
    Write-Host "Criando diretorio SSH..." -ForegroundColor Yellow
    New-Item -ItemType Directory -Path $SshDir -Force | Out-Null
}

# Gerar chave SSH
if (-not (Test-Path $PrivateKeyPath)) {
    Write-Host ">>> Gerando chave SSH RSA 4096..." -ForegroundColor Cyan
    $sshKeygenPath = "C:\Windows\System32\OpenSSH\ssh-keygen.exe"
    & $sshKeygenPath -t rsa -b 4096 -N "" -f $PrivateKeyPath -C "grom_deploy" | Out-Null
    Write-Host "OK: Chave gerada" -ForegroundColor Green
} else {
    Write-Host "OK: Chave ja existe" -ForegroundColor Green
}

# Injetar chave na OCI
Write-Host "`n>>> Atualizando chave SSH na OCI..." -ForegroundColor Cyan
$publicKeyContent = Get-Content $PublicKeyPath -Raw
$metadataJson = @{
    "ssh_authorized_keys" = @($publicKeyContent.Trim())
} | ConvertTo-Json

& oci compute instance update-metadata --instance-id $OciInstanceId --metadata $metadataJson --force 2>&1 | Out-Null
Write-Host "OK: Metadata atualizada" -ForegroundColor Green

# Testar SSH
Write-Host "`n>>> Testando SSH..." -ForegroundColor Cyan
$sshPath = "C:\Windows\System32\OpenSSH\ssh.exe"
$sshOpts = @('-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=10', '-i', $PrivateKeyPath)

$testOk = $false
for ($i = 1; $i -le 5; $i++) {
    Write-Host "  Tentativa $i..." -ForegroundColor DarkGray
    try {
        $result = & $sshPath @sshOpts opc@$VpsIp "echo OK" 2>&1
        if ($result -like "*OK*") {
            $testOk = $true
            break
        }
    } catch {}
    Start-Sleep -Seconds 5
}

if (-not $testOk) {
    Write-Host "ERRO: Nao conseguiu conectar via SSH" -ForegroundColor Red
    exit 1
}
Write-Host "OK: Conexao SSH funcionando" -ForegroundColor Green

# Executar deploy remoto
Write-Host "`n>>> Iniciando deploy remoto (pode levar 15-20 min)..." -ForegroundColor Cyan

$script = @'
#!/bin/bash
set -e

echo ">>> Corrigindo Dockerfile..."
DOCKERFILE="/opt/grom/grom_web_php/infra/php/Dockerfile"
cp "$DOCKERFILE" "$DOCKERFILE.bak" 2>/dev/null || true

cat > "$DOCKERFILE" << DOCKEREOF
ARG PHP_IMAGE=php:8.4-fpm-alpine
FROM \${PHP_IMAGE}

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
DOCKEREOF

echo "OK: Dockerfile corrigido"
echo ""
echo ">>> Reconstruindo Docker (aguarde 10-15 min)..."
cd /opt/grom/grom_web_php/infra
docker compose --env-file .env.production -f docker-compose.prod.yml down -v 2>/dev/null || true
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
echo "OK: Docker iniciado"

echo ""
echo ">>> Aguardando PostgreSQL..."
for i in {1..60}; do
  if docker compose --env-file .env.production -f docker-compose.prod.yml exec -T postgres pg_isready -U postgres >/dev/null 2>&1; then
    echo "OK: PostgreSQL pronto"
    break
  fi
  sleep 5
done

echo ""
echo ">>> Migrando banco de dados..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan db:seed --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan optimize:clear

echo ""
docker compose --env-file .env.production -f docker-compose.prod.yml ps
echo ""
echo "========================================"
echo "OK: Deploy completo!"
echo "========================================"
echo ""
'@

& $sshPath @sshOpts opc@$VpsIp "bash" <<< $script

Write-Host "`n" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Green
Write-Host "DEPLOY CONCLUIDO COM SUCESSO!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "URL: https://grom.seg.br" -ForegroundColor Yellow
Write-Host "Login: admin" -ForegroundColor Yellow
Write-Host "Senha: 03031981Gr**" -ForegroundColor Yellow
Write-Host ""
Write-Host "SSH:" -ForegroundColor Yellow
Write-Host "  ssh -i `"$PrivateKeyPath`" opc@$VpsIp" -ForegroundColor DarkGray
Write-Host ""
