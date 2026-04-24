#!/usr/bin/env powershell
<#
.SYNOPSIS
    Envia o codigo GROM Web para a VPS e executa o bootstrap completo automatizado.

.DESCRIPTION
    - Copia o repositorio para /opt/grom/grom_web_php na VPS via SCP/rsync
    - Executa infra/scripts/bootstrap-vps.sh como root via SSH
    - Exibe as credenciais geradas ao final

.PARAMETER VpsIp
    IP publico (ou hostname) da VPS. Obrigatorio.

.PARAMETER SshUser
    Usuario SSH (padrao: root). Se nao for root, precisa de sudo sem senha.

.PARAMETER SshKeyPath
    Caminho para a chave privada SSH (.pem ou similar). Se omitido, usa autenticacao por senha.

.PARAMETER Domain
    Dominio do sistema (padrao: grom.seg.br).

.PARAMETER CertbotEmail
    E-mail para o certificado Let's Encrypt (padrao: admin@<Domain>).

.PARAMETER AdminPassword
    Senha do administrador GROM. Se omitido, sera gerada automaticamente na VPS.

.PARAMETER SkipUpload
    Pula a etapa de upload (util se o codigo ja estiver na VPS).

.EXAMPLE
    .\Deploy-ToVPS.ps1 -VpsIp 129.148.x.x -SshKeyPath C:\chaves\grom_vps.pem

.EXAMPLE
    .\Deploy-ToVPS.ps1 -VpsIp 129.148.x.x -SshUser ubuntu -Domain grom.seg.br -AdminPassword "MinhaS3nhaF0rte!"
#>

param(
    [Parameter(Mandatory)]
    [string] $VpsIp,

    [string] $SshUser = 'root',
    [string] $SshKeyPath = '',
    [string] $Domain = 'grom.seg.br',
    [string] $CertbotEmail = '',
    [Parameter()]
    [SecureString] $AdminPassword = $null,
    [switch] $SkipUpload
)

$ErrorActionPreference = 'Stop'

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
function Write-Step {
    param([string] $Msg)
    Write-Host "`n>>> $Msg" -ForegroundColor Cyan
}

function Write-Ok {
    param([string] $Msg)
    Write-Host "    OK: $Msg" -ForegroundColor Green
}

function Write-Warn {
    param([string] $Msg)
    Write-Host "    AVISO: $Msg" -ForegroundColor Yellow
}

function Assert-Command {
    param([string] $Name)
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Comando '$Name' nao encontrado. Instale-o e adicione ao PATH."
    }
}

# ---------------------------------------------------------------------------
# Verificacoes iniciais
# ---------------------------------------------------------------------------
Write-Step "Verificando pre-requisitos locais..."

Assert-Command 'ssh'
Assert-Command 'scp'

$RepoRoot = Split-Path $PSScriptRoot -Parent
if (-not (Test-Path (Join-Path $RepoRoot 'runtime'))) {
    throw "Execute este script a partir da raiz do repositorio ou da pasta scripts/."
}

if ([string]::IsNullOrWhiteSpace($CertbotEmail)) {
    $CertbotEmail = "admin@$Domain"
}

# Montar args SSH
$SshOpts = @('-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=15')
if (-not [string]::IsNullOrWhiteSpace($SshKeyPath)) {
    if (-not (Test-Path $SshKeyPath)) { throw "Chave SSH nao encontrada: $SshKeyPath" }
    $SshOpts += @('-i', $SshKeyPath)
}
$SshTarget = "${SshUser}@${VpsIp}"

Write-Ok "SSH target: $SshTarget"
Write-Ok "Dominio   : $Domain"
Write-Ok "Email TLS : $CertbotEmail"

# ---------------------------------------------------------------------------
# Teste de conectividade SSH
# ---------------------------------------------------------------------------
Write-Step "Testando conexao SSH com a VPS..."
$testCmd = @('ssh') + $SshOpts + @($SshTarget, 'echo GROM_SSH_OK')
$testResult = & $testCmd[0] @($testCmd[1..($testCmd.Length-1)]) 2>&1
if ($testResult -notmatch 'GROM_SSH_OK') {
    throw "Falha na conexao SSH. Saida: $testResult"
}
Write-Ok "Conexao SSH estabelecida."

# ---------------------------------------------------------------------------
# Upload do repositorio
# ---------------------------------------------------------------------------
if (-not $SkipUpload) {
    Write-Step "Enviando repositorio para a VPS (pode demorar alguns minutos)..."

    # Criar estrutura de diretorios na VPS
    $mkdirCmd = @('ssh') + $SshOpts + @($SshTarget, 'mkdir -p /opt/grom')
    & $mkdirCmd[0] @($mkdirCmd[1..($mkdirCmd.Length-1)])

    # Usar scp recursivo (excluindo node_modules, vendor, storage/app, .git pesado)
    # Cria um tar local e envia, descompactando na VPS
    $TarFile = Join-Path $env:TEMP "grom_deploy_$(Get-Date -Format 'yyyyMMddHHmmss').tar.gz"

    Write-Host "    Compactando repositorio..." -ForegroundColor DarkGray

    # Usar tar do Git for Windows ou WSL se disponivel, senao 7-zip
    $tarBin = Get-Command 'tar' -ErrorAction SilentlyContinue
    if ($tarBin) {
        # Excluir pastas pesadas e desnecessarias para producao
        $excludes = @(
            '--exclude=./runtime/vendor',
            '--exclude=./runtime/node_modules',
            '--exclude=./runtime/storage/app/*.sqlite',
            '--exclude=./runtime/storage/app/*.sqlite-*',
            '--exclude=./runtime/.env',
            '--exclude=./_toolchain',
            '--exclude=./.git',
            '--exclude=./scripts/load-test-report-*.json'
        )
        Push-Location $RepoRoot
        & tar @excludes -czf $TarFile .
        Pop-Location
    } else {
        throw "Comando 'tar' nao encontrado. Instale Git for Windows ou habilite WSL."
    }

    $tarSize = [math]::Round((Get-Item $TarFile).Length / 1MB, 1)
    Write-Host "    Arquivo: $TarFile ($($tarSize) MB)" -ForegroundColor DarkGray

    # Enviar tar para VPS
    $scpArgs = $SshOpts + @($TarFile, "${SshTarget}:/tmp/grom_deploy.tar.gz")
    & scp @scpArgs

    # Descompactar na VPS
    $extractCmd = @('ssh') + $SshOpts + @(
        $SshTarget,
        'rm -rf /opt/grom/grom_web_php && mkdir -p /opt/grom/grom_web_php && tar -xzf /tmp/grom_deploy.tar.gz -C /opt/grom/grom_web_php && rm /tmp/grom_deploy.tar.gz'
    )
    & $extractCmd[0] @($extractCmd[1..($extractCmd.Length-1)])

    Remove-Item $TarFile -Force -ErrorAction SilentlyContinue
    Write-Ok "Repositorio enviado e extraido em /opt/grom/grom_web_php."
} else {
    Write-Warn "Upload pulado (--SkipUpload). Assumindo que o codigo ja esta na VPS."
}

# ---------------------------------------------------------------------------
# Executar bootstrap na VPS
# ---------------------------------------------------------------------------
Write-Step "Executando bootstrap completo na VPS (pode levar 3-8 minutos)..."

$bootstrapEnv = "GROM_DOMAIN='$Domain' GROM_CERTBOT_EMAIL='$CertbotEmail' GROM_REPO_DIR='/opt/grom/grom_web_php'"
if ($null -ne $AdminPassword -and $AdminPassword.Length -gt 0) {
    $adminPlain = [System.Net.NetworkCredential]::new('', $AdminPassword).Password
    $bootstrapEnv += " GROM_ADMIN_PASSWORD='$adminPlain'"
}

$bootstrapCmd = @('ssh') + $SshOpts + @(
    $SshTarget,
    "chmod +x /opt/grom/grom_web_php/infra/scripts/bootstrap-vps.sh && $bootstrapEnv bash /opt/grom/grom_web_php/infra/scripts/bootstrap-vps.sh '$Domain' '$CertbotEmail'"
)
& $bootstrapCmd[0] @($bootstrapCmd[1..($bootstrapCmd.Length-1)])

# ---------------------------------------------------------------------------
# Buscar arquivo de credenciais gerado na VPS
# ---------------------------------------------------------------------------
Write-Step "Copiando arquivo de credenciais da VPS..."
$LocalCredFile = Join-Path $RepoRoot "grom-credenciais-producao.txt"
$scpCredArgs = $SshOpts + @("${SshTarget}:/root/grom-credenciais.txt", $LocalCredFile)
& scp @scpCredArgs
if (Test-Path $LocalCredFile) {
    Write-Ok "Credenciais salvas em: $LocalCredFile"
    Write-Host "`n" -NoNewline
    Get-Content $LocalCredFile | Write-Host -ForegroundColor Yellow

    # Remover arquivo de credenciais da VPS por seguranca
    $rmCredCmd = @('ssh') + $SshOpts + @($SshTarget, 'rm -f /root/grom-credenciais.txt')
    & $rmCredCmd[0] @($rmCredCmd[1..($rmCredCmd.Length-1)]) 2>$null
    Write-Warn "Arquivo de credenciais removido da VPS."
} else {
    Write-Warn "Nao foi possivel copiar as credenciais automaticamente."
    Write-Warn "Acesse a VPS e veja: cat /root/grom-credenciais.txt"
}

# ---------------------------------------------------------------------------
# Validacao final a partir da maquina local
# ---------------------------------------------------------------------------
Write-Step "Validando acesso externo..."
try {
    $resp = Invoke-WebRequest -UseBasicParsing -Uri "https://$Domain/login" -TimeoutSec 20 -ErrorAction Stop
    if ($resp.StatusCode -eq 200) {
        Write-Ok "https://$Domain/login respondeu 200 - SISTEMA NO AR!"
    }
} catch {
    Write-Warn "Nao foi possivel validar https://$Domain/login a partir desta maquina."
    Write-Warn "Isso pode ser normal se o DNS ainda nao propagou. Tente em alguns minutos."
}

Write-Host "`n" -NoNewline
Write-Host "=======================================================" -ForegroundColor Green
Write-Host " GROM Web implantado com sucesso!" -ForegroundColor Green
Write-Host " Acesse: https://$Domain/login" -ForegroundColor Green
Write-Host "=======================================================" -ForegroundColor Green
