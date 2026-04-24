#!/usr/bin/env powershell
<#
.SYNOPSIS
    Deploy-ToVPS-HTTP.ps1 — Implantação via HTTP (sem SSH necessário)
    
.DESCRIPTION
    - Inicia um servidor local simples (Node.js ou PowerShell)
    - Você acessa a VPS por Console Serial e executa um comando curl
    - VPS baixa e aplica tudo automaticamente

.PARAMETER VpsIp
    IP ou dominio da VPS (para gerar URL de acesso).

.PARAMETER Domain
    Dominio publico (padrao: grom.seg.br).

.PARAMETER CertbotEmail
    E-mail para Let's Encrypt (padrao: admin@grom.seg.br).

.PARAMETER AdminPassword
    Senha do admin (gerada automaticamente se omitida).

.EXAMPLE
    .\Deploy-ToVPS-HTTP.ps1 -VpsIp 137.131.241.192 -Domain grom.seg.br -AdminPassword (ConvertTo-SecureString '03031981Gr**' -AsPlainText -Force)
#>

param(
    [Parameter(Mandatory)]
    [string] $VpsIp,

    [string] $Domain = 'grom.seg.br',
    [string] $CertbotEmail = 'admin@grom.seg.br',
    [Parameter()]
    [SecureString] $AdminPassword = $null
)

$ErrorActionPreference = 'Stop'

function Write-Step {
    param([string] $Msg)
    Write-Host "`n>>> $Msg" -ForegroundColor Cyan
}

function Write-Ok {
    param([string] $Msg)
    Write-Host "    OK: $Msg" -ForegroundColor Green
}

function Write-Info {
    param([string] $Msg)
    Write-Host "    $Msg" -ForegroundColor DarkCyan
}

function Get-PreferredLocalIPv4 {
    param([string] $RemoteIp)

    try {
        $udp = New-Object System.Net.Sockets.UdpClient
        $udp.Connect($RemoteIp, 53)
        $ep = [System.Net.IPEndPoint]$udp.Client.LocalEndPoint
        $udp.Close()
        if ($ep.Address -and $ep.Address.ToString() -ne '0.0.0.0') {
            return $ep.Address.ToString()
        }
    } catch {
        # fallback abaixo
    }

    $fallback = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object {
            $_.IPAddress -ne '127.0.0.1' -and
            $_.IPAddress -notlike '169.254.*'
        } |
        Select-Object -First 1 -ExpandProperty IPAddress

    if ($fallback) {
        return $fallback
    }

    throw "Nao foi possivel detectar o IP local IPv4 para servir os arquivos."
}

# Parâmetros
$RepoRoot = Split-Path $PSScriptRoot -Parent
$ServerPort = 8888
$ScriptName = 'bootstrap.sh'
$TarName = 'grom_deploy.tar.gz'
$HostIp = Get-PreferredLocalIPv4 -RemoteIp $VpsIp
$SourceBaseUrl = "http://${HostIp}:$ServerPort"

Write-Step "Deploy GROM Web via HTTP"
Write-Info "Dominio  : $Domain"
Write-Info "VPS IP   : $VpsIp"
Write-Info "Origem   : $SourceBaseUrl"
Write-Info "Porta    : $ServerPort"

# Preparar variáveis de ambiente
$envVars = @(
    "GROM_DOMAIN='$Domain'"
    "GROM_CERTBOT_EMAIL='$CertbotEmail'"
    "GROM_REPO_DIR='/opt/grom/grom_web_php'"
    "GROM_SOURCE_BASE_URL='$SourceBaseUrl'"
)

if ($null -ne $AdminPassword -and $AdminPassword.Length -gt 0) {
    $adminPlain = [System.Net.NetworkCredential]::new('', $AdminPassword).Password
    $envVars += "GROM_ADMIN_PASSWORD='$adminPlain'"
}

$envString = $envVars -join ' '
$bootstrapCmdPipe = ('sudo bash -c "curl -fsSL {0}/bootstrap.sh | {1} bash"' -f $SourceBaseUrl, $envString)
$bootstrapCmdFile = ('sudo bash -c "curl -fsSL {0}/bootstrap.sh > /tmp/bootstrap.sh ; {1} bash /tmp/bootstrap.sh"' -f $SourceBaseUrl, $envString)

# Criar tar do projeto
Write-Step "Preparando arquivos..."

$TarPath = Join-Path $env:TEMP $TarName
$excludes = @(
    '--exclude=./runtime/vendor',
    '--exclude=./runtime/node_modules',
    '--exclude=./runtime/storage/app/*.sqlite*',
    '--exclude=./runtime/.env',
    '--exclude=./_toolchain',
    '--exclude=./.git',
    '--exclude=./scripts/load-test-report-*.json'
)

if (Get-Command 'tar' -ErrorAction SilentlyContinue) {
    Push-Location $RepoRoot
    & tar @excludes -czf $TarPath .
    Pop-Location
    Write-Ok "Arquivo compactado: $TarPath"
} else {
    throw "Comando 'tar' nao encontrado."
}

# Copiar bootstrap script
$BootstrapPath = Join-Path $env:TEMP $ScriptName
Copy-Item (Join-Path $RepoRoot "infra\scripts\bootstrap-vps-http.sh") $BootstrapPath -Force
Write-Ok "Bootstrap script copiado."

# Iniciar servidor HTTP simples via TCP (sem exigir URLACL)
Write-Step "Iniciando servidor HTTP na porta $ServerPort..."

$listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Any, $ServerPort)
$listener.Start()

function Send-HttpResponse {
    param(
        [System.Net.Sockets.NetworkStream] $Stream,
        [int] $StatusCode,
        [string] $ContentType,
        [byte[]] $Body
    )

    $statusText = switch ($StatusCode) {
        200 { 'OK' }
        404 { 'Not Found' }
        default { 'Internal Server Error' }
    }

    $header = (
        "HTTP/1.1 {0} {1}`r`nContent-Type: {2}`r`nContent-Length: {3}`r`nConnection: close`r`n`r`n" -f
        $StatusCode, $statusText, $ContentType, $Body.Length
    )

    $headerBytes = [System.Text.Encoding]::ASCII.GetBytes($header)
    $Stream.Write($headerBytes, 0, $headerBytes.Length)
    $Stream.Write($Body, 0, $Body.Length)
}

Write-Host "    Servidor rodando..." -ForegroundColor Green
Write-Host ""
Write-Host "=======================================================" -ForegroundColor Yellow
Write-Host " COPIE E EXECUTE ESTE COMANDO NA VPS:"
Write-Host "=======================================================" -ForegroundColor Yellow
Write-Host ""
Write-Host $bootstrapCmdPipe -ForegroundColor Cyan
Write-Host ""
Write-Host "OU:" -ForegroundColor Yellow
Write-Host ""
Write-Host $bootstrapCmdFile -ForegroundColor Cyan
Write-Host ""
Write-Host "=======================================================" -ForegroundColor Yellow
Write-Host ""

# Loop de atendimento
$requestCount = 0
$deployStarted = $false

try {
    while ($true) {
        $client = $listener.AcceptTcpClient()
        $stream = $client.GetStream()
        $reader = New-Object System.IO.StreamReader($stream, [System.Text.Encoding]::ASCII, $false, 1024, $true)

        try {
            $requestLine = $reader.ReadLine()
            if ([string]::IsNullOrWhiteSpace($requestLine)) {
                continue
            }

            while ($true) {
                $line = $reader.ReadLine()
                if ([string]::IsNullOrEmpty($line)) {
                    break
                }
            }

            $parts = $requestLine -split ' '
            $method = if ($parts.Length -ge 1) { $parts[0] } else { 'GET' }
            $rawPath = if ($parts.Length -ge 2) { $parts[1] } else { '/' }
            $path = $rawPath.Split('?')[0]

            Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Requisicao: $method $rawPath" -ForegroundColor DarkGray

            if ($path -eq '/' -or $path -eq '') {
                $html = @"
<!DOCTYPE html>
<html>
<head><title>GROM Web Deploy</title></head>
<body style="font-family:monospace; margin:40px;">
<h2>GROM Web — Deploy via HTTP</h2>
<p>Execute na VPS:</p>
<pre style="background:#f0f0f0; padding:10px; border-radius:5px;">
$bootstrapCmdPipe
</pre>
<hr/>
<p>Arquivos disponíveis:</p>
<ul>
  <li><a href="/bootstrap.sh">/bootstrap.sh</a></li>
  <li><a href="/$TarName">/$TarName</a></li>
</ul>
</body>
</html>
"@
                [byte[]]$buffer = [System.Text.Encoding]::UTF8.GetBytes($html)
                Send-HttpResponse -Stream $stream -StatusCode 200 -ContentType 'text/html; charset=utf-8' -Body $buffer
            }
            elseif ($path -eq "/$ScriptName") {
                $bytes = [System.IO.File]::ReadAllBytes($BootstrapPath)
                Send-HttpResponse -Stream $stream -StatusCode 200 -ContentType 'application/x-sh' -Body $bytes
                $deployStarted = $true
                Write-Host "    [DEPLOY INICIADO] Bootstrap enviado para a VPS" -ForegroundColor Green
            }
            elseif ($path -eq "/$TarName") {
                $bytes = [System.IO.File]::ReadAllBytes($TarPath)
                Send-HttpResponse -Stream $stream -StatusCode 200 -ContentType 'application/gzip' -Body $bytes
                Write-Host "    [TAR ENVIADO] Repositorio ($([math]::Round($bytes.Length/1MB,1))MB)" -ForegroundColor Green
            }
            else {
                $msg = "Nao encontrado"
                [byte[]]$buffer = [System.Text.Encoding]::UTF8.GetBytes($msg)
                Send-HttpResponse -Stream $stream -StatusCode 404 -ContentType 'text/plain; charset=utf-8' -Body $buffer
            }

            $requestCount++
        }
        catch {
            Write-Host "    ERRO: $_" -ForegroundColor Red
            $msg = "Erro interno"
            [byte[]]$buffer = [System.Text.Encoding]::UTF8.GetBytes($msg)
            Send-HttpResponse -Stream $stream -StatusCode 500 -ContentType 'text/plain; charset=utf-8' -Body $buffer
        }
        finally {
            $reader.Close()
            $stream.Close()
            $client.Close()
        }
    }
}
finally {
    $listener.Stop()
    
    Write-Host ""
    Write-Host "Servidor encerrado." -ForegroundColor DarkGray
    Write-Host "Total de requisicoes: $requestCount" -ForegroundColor DarkGray
    
    if ($deployStarted) {
        Write-Host ""
        Write-Host "O deploy foi iniciado na VPS. Aguarde 8-10 minutos para conclusao." -ForegroundColor Green
        Write-Host "A VPS estara disponivel em: https://$Domain/login" -ForegroundColor Cyan
    }
}
