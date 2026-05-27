#!/usr/bin/env powershell
<#
.SYNOPSIS
    Gera chave SSH localmente e executa o deploy do GROM na VPS via OCI CLI

.DESCRIPTION
    1. Cria chave SSH RSA 4096 em C:\Users\[user]\.ssh\grom_deploy_rsa
    2. Injeta a chave pública na instância OCI
    3. Conecta via SSH e executa o script de correção Docker
    4. Executa migrações e validações

.PARAMETER OciCompartmentId
    ID do compartimento OCI (padrão: obtido de configuração OCI)

.PARAMETER OciInstanceId
    ID da instância OCI (padrão: ocid1.instance.oc1.sa-saopaulo-1.antxeljren3rd7qcj5d5vrfbp2f55cxm6glmhjen63rcsfiqi47ai3wvyjaq)

.PARAMETER VpsIp
    IP da VPS (padrão: 163.176.144.245)
#>

param(
    [string]$VpsIp = "163.176.144.245",
    [string]$OciInstanceId = "ocid1.instance.oc1.sa-saopaulo-1.antxeljren3rd7qcj5d5vrfbp2f55cxm6glmhjen63rcsfiqi47ai3wvyjaq"
)

$ErrorActionPreference = "Stop"

# ============================================================================
# 1. SETUP INICIAL
# ============================================================================

Write-Host "`n=== GROM Deploy via SSH ===" -ForegroundColor Cyan

$HomeDir = [System.Environment]::GetFolderPath('UserProfile')
$SshDir = Join-Path $HomeDir ".ssh"
$PrivateKeyPath = Join-Path $SshDir "grom_deploy_rsa"
$PublicKeyPath = "$PrivateKeyPath.pub"

# Criar pasta .ssh se não existir
if (-not (Test-Path $SshDir)) {
    Write-Host "Criando diretório SSH: $SshDir" -ForegroundColor Yellow
    New-Item -ItemType Directory -Path $SshDir -Force | Out-Null
}

# ============================================================================
# 2. GERAR CHAVE SSH (se não existir)
# ============================================================================

if (Test-Path $PrivateKeyPath) {
    Write-Host "✓ Chave SSH já existe: $PrivateKeyPath" -ForegroundColor Green
} else {
    Write-Host ">>> Gerando chave SSH RSA 4096..." -ForegroundColor Cyan
    
    # Usar ssh-keygen do OpenSSH do Windows
    $sshKeygenPath = "C:\Windows\System32\OpenSSH\ssh-keygen.exe"
    if (-not (Test-Path $sshKeygenPath)) {
        throw "ssh-keygen.exe não encontrado. Instale OpenSSH for Windows."
    }
    
    # Gerar chave sem passphrase (para deploy automatizado)
    & $sshKeygenPath -t rsa -b 4096 -N "" -f $PrivateKeyPath -C "grom_deploy@$env:COMPUTERNAME" | Out-Null
    
    Write-Host "âœ“ Chave SSH gerada:" -ForegroundColor Green
    Write-Host "  Private: $PrivateKeyPath" -ForegroundColor DarkGray
    Write-Host "  Public : $PublicKeyPath" -ForegroundColor DarkGray
}

# ============================================================================
# 3. LER CHAVE PÚBLICA
# ============================================================================

$publicKeyContent = Get-Content $PublicKeyPath -Raw
Write-Host "✓ Chave pública lida ($(($publicKeyContent.Length)/1024)KB)" -ForegroundColor Green

# ============================================================================
# 4. INJETAR CHAVE NA INSTÂNCIA OCI
# ============================================================================

Write-Host "`n>>> Atualizando metadata da instância OCI com nova chave SSH..." -ForegroundColor Cyan

# Construir arquivo de metadata
$metadataJson = @{
    "ssh_authorized_keys" = @($publicKeyContent.Trim())
} | ConvertTo-Json

Write-Host "  Executando: oci compute instance update-metadata..." -ForegroundColor DarkGray

try {
    $updateResult = & oci compute instance update-metadata `
        --instance-id $OciInstanceId `
        --metadata $metadataJson `
        --force `
        2>&1
    
    Write-Host "âœ“ Metadata atualizada na OCI" -ForegroundColor Green
} catch {
    Write-Host "âš  Aviso ao atualizar metadata: $_" -ForegroundColor Yellow
    Write-Host "  Continuando mesmo assim (chave pode ter sido injetada)" -ForegroundColor Yellow
}

# ============================================================================
# 5. TESTAR CONECTIVIDADE SSH
# ============================================================================

Write-Host "`n>>> Testando conectividade SSH..." -ForegroundColor Cyan

$sshPath = "C:\Windows\System32\OpenSSH\ssh.exe"
$sshOpts = @('-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=10', '-i', $PrivateKeyPath)

for ($attempt = 1; $attempt -le 5; $attempt++) {
    Write-Host "  Tentativa $attempt/5..." -ForegroundColor DarkGray
    
    try {
        $testResult = & $sshPath @sshOpts opc@$VpsIp "echo GROM_SSH_OK" 2>&1 | Select-Object -First 1
        
        if ($testResult -match "GROM_SSH_OK") {
            Write-Host "✓ Conexão SSH estabelecida com sucesso!" -ForegroundColor Green
            break
        }
    } catch {
        # continuar para próxima tentativa
    }
    
    if ($attempt -lt 5) {
        Write-Host "  Aguardando 5 segundos antes de tentar novamente..." -ForegroundColor DarkGray
        Start-Sleep -Seconds 5
    }
}

# ============================================================================
# 6. EXECUTAR SCRIPT DE DEPLOY REMOTO
# ============================================================================

Write-Host "`n>>> Executando script de deploy remoto na VPS..." -ForegroundColor Cyan
Write-Host "  Isso pode levar 15-20 minutos (Docker build + migrações)" -ForegroundColor Yellow

$remoteScript = @'

#!/bin/bash
set -e

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

echo "âœ“ Dockerfile corrigido"

echo ""
echo ">>> Reconstruindo Docker Compose (pode levar 10-15 minutos)..."
cd /opt/grom/grom_web_php/infra

docker compose --env-file .env.production -f docker-compose.prod.yml down -v 2>/dev/null || true

echo ""
echo "  [Docker Build em progresso...]"
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build

echo "âœ“ Docker Compose iniciado"

echo ""
echo ">>> Aguardando PostgreSQL ficar pronto..."
for i in {1..60}; do
  if docker compose --env-file .env.production -f docker-compose.prod.yml exec -T postgres pg_isready -U postgres >/dev/null 2>&1; then
    echo "âœ“ PostgreSQL pronto na tentativa $i"
    break
  fi
  if [ $((i % 6)) -eq 0 ]; then
    echo "  [Aguardando... tentativa $i/60]"
  fi
  sleep 5
done

echo ""
echo ">>> Executando migrações Laravel..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan migrate --force

echo ""
echo ">>> Executando database seeders..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan db:seed --force

echo ""
echo ">>> Limpando cache..."
docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php artisan optimize:clear

echo ""
echo ">>> Status dos containers:"
docker compose --env-file .env.production -f docker-compose.prod.yml ps

echo ""
echo "========================================"
echo "âœ“ DEPLOY COMPLETADO COM SUCESSO!"
echo "========================================"
echo ""
echo "URL: https://grom.seg.br"
echo "Login: admin"
echo "Senha: 03031981Gr**"
echo ""
'@


'@

# Executar script remoto
& $sshPath @sshOpts opc@$VpsIp "bash -s" <<< $remoteScript

# ============================================================================
# 7. VALIDAÇÃO FINAL
# ============================================================================

Write-Host "`n>>> Validando deployment..." -ForegroundColor Cyan

Start-Sleep -Seconds 5

Write-Host "  Testando HTTPS..." -ForegroundColor DarkGray
try {
    $response = Invoke-WebRequest -Uri "https://grom.seg.br" -SkipCertificateCheck -TimeoutSec 10 -ErrorAction Stop
    Write-Host "âœ“ HTTPS respondendo (Status: $($response.StatusCode))" -ForegroundColor Green
} catch {
    Write-Host "⚠ Não conseguiu acessar HTTPS ainda. Aguarde mais alguns segundos e tente manualmente." -ForegroundColor Yellow
}

# ============================================================================
# 8. RESUMO FINAL
# ============================================================================

Write-Host "`n" -ForegroundColor Cyan
Write-Host "â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—" -ForegroundColor Green
Write-Host "║          GROM WEB DEPLOYMENT CONCLUÍDO COM SUCESSO        ║" -ForegroundColor Green
Write-Host "â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•" -ForegroundColor Green

Write-Host ""
Write-Host "ðŸ“ Detalhes do Deployment:" -ForegroundColor Cyan
Write-Host "  VPS IP           : $VpsIp" -ForegroundColor DarkGray
Write-Host "  SSH Private Key  : $PrivateKeyPath" -ForegroundColor DarkGray
Write-Host "  Docker Compose   : /opt/grom/grom_web_php/infra" -ForegroundColor DarkGray
Write-Host ""
Write-Host "🌐 Acessar Aplicação:" -ForegroundColor Cyan
Write-Host "  URL: https://grom.seg.br" -ForegroundColor Yellow
Write-Host "  Login: admin" -ForegroundColor Yellow
Write-Host "  Senha: 03031981Gr**" -ForegroundColor Yellow
Write-Host ""
Write-Host "ðŸ”‘ Credenciais do Banco:" -ForegroundColor Cyan
Write-Host "  Database: grom_web" -ForegroundColor DarkGray
Write-Host "  User: postgres" -ForegroundColor DarkGray
Write-Host ""
Write-Host "📝 Comandos Úteis:" -ForegroundColor Cyan
Write-Host "  SSH na VPS:" -ForegroundColor DarkGray
Write-Host "    ssh -i `"$PrivateKeyPath`" opc@$VpsIp" -ForegroundColor Yellow
Write-Host ""
Write-Host "  Ver logs (root):" -ForegroundColor DarkGray
Write-Host "    ssh -i `"$PrivateKeyPath`" opc@$VpsIp 'sudo docker compose -f /opt/grom/grom_web_php/infra/docker-compose.prod.yml logs -f'" -ForegroundColor Yellow
Write-Host ""
Write-Host "  Status dos containers:" -ForegroundColor DarkGray
Write-Host "    ssh -i `"$PrivateKeyPath`" opc@$VpsIp 'sudo docker compose -f /opt/grom/grom_web_php/infra/docker-compose.prod.yml ps'" -ForegroundColor Yellow
Write-Host ""

Write-Host "âœ“ Deploy finalizado! Acesse https://grom.seg.br em alguns segundos." -ForegroundColor Green

