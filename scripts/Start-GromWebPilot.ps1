param(
    [switch] $Fresh,
    [switch] $PrepareOnly,
    [switch] $SmokeTest,
    [string] $BindHost = '127.0.0.1',
    [int] $Port = 8088
)

$ErrorActionPreference = 'Stop'

function Get-EnvValue {
    param(
        [string] $Path,
        [string] $Key
    )

    if (-not (Test-Path -LiteralPath $Path)) {
        return $null
    }

    $pattern = '^{0}=(.*)$' -f [regex]::Escape($Key)

    foreach ($line in Get-Content -LiteralPath $Path) {
        if ($line -match $pattern) {
            $value = $matches[1].Trim()
            if ($value.StartsWith('"') -and $value.EndsWith('"')) {
                $value = $value.Substring(1, $value.Length - 2)
            }
            return $value
        }
    }

    return $null
}

function Set-EnvValue {
    param(
        [string] $Path,
        [string] $Key,
        [string] $Value
    )

    $escaped = $Value.Replace('"', '\"')
    $newLine = '{0}="{1}"' -f $Key, $escaped
    $pattern = '^{0}=' -f [regex]::Escape($Key)
    $lines = @()

    if (Test-Path -LiteralPath $Path) {
        $lines = Get-Content -LiteralPath $Path
    }

    $updated = $false
    $result = foreach ($line in $lines) {
        if ($line -match $pattern) {
            $updated = $true
            $newLine
        } else {
            $line
        }
    }

    if (-not $updated) {
        if ($result.Count -gt 0 -and $result[-1] -ne '') {
            $result += ''
        }
        $result += $newLine
    }

    Set-Content -LiteralPath $Path -Value $result -Encoding UTF8
}

function New-PilotPassword {
    $bytes = New-Object byte[] 12
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $rng.GetBytes($bytes)
    } finally {
        $rng.Dispose()
    }

    $token = [Convert]::ToBase64String($bytes).TrimEnd('=').Replace('+', 'A').Replace('/', 'B')

    return ('Pilot!{0}' -f $token)
}

function Invoke-Artisan {
    param(
        [string[]] $Arguments,
        [string] $RuntimePath,
        [string] $PhpPath
    )

    & $PhpPath @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw ('Falha ao executar: php {0}' -f ($Arguments -join ' '))
    }
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Resolve-Path (Join-Path $scriptRoot '..')
$runtimePath = Join-Path $projectRoot 'runtime'
$phpPath = Join-Path $projectRoot '_toolchain\bin\php.cmd'
$envExamplePath = Join-Path $runtimePath '.env.sqlite.local.example'
$envPath = Join-Path $runtimePath '.env'
$dbPath = Join-Path $runtimePath 'storage\app\grom_web_local.sqlite'
$dbJournalPath = '{0}-journal' -f $dbPath
$legacyDbPath = Resolve-Path (Join-Path $projectRoot '..\main\grom_database.sqlite3') -ErrorAction SilentlyContinue
$appUrl = 'http://{0}:{1}' -f $BindHost, $Port

if (-not (Test-Path -LiteralPath $phpPath)) {
    throw 'Nao foi localizado o PHP local do projeto em grom_web_php\_toolchain\bin\php.cmd.'
}

if (-not (Test-Path -LiteralPath $envPath)) {
    Copy-Item -LiteralPath $envExamplePath -Destination $envPath -Force
}

$adminPassword = Get-EnvValue -Path $envPath -Key 'GROM_BOOTSTRAP_ADMIN_PASSWORD'

if ([string]::IsNullOrWhiteSpace($adminPassword)) {
    $adminPassword = New-PilotPassword
    Set-EnvValue -Path $envPath -Key 'GROM_BOOTSTRAP_ADMIN_PASSWORD' -Value $adminPassword
}

Set-EnvValue -Path $envPath -Key 'APP_ENV' -Value 'local'
Set-EnvValue -Path $envPath -Key 'APP_DEBUG' -Value 'true'
Set-EnvValue -Path $envPath -Key 'APP_URL' -Value $appUrl
Set-EnvValue -Path $envPath -Key 'DB_CONNECTION' -Value 'sqlite'
Set-EnvValue -Path $envPath -Key 'DB_DATABASE' -Value (($dbPath -replace '\\', '/'))
Set-EnvValue -Path $envPath -Key 'SESSION_DRIVER' -Value 'file'
Set-EnvValue -Path $envPath -Key 'QUEUE_CONNECTION' -Value 'sync'
Set-EnvValue -Path $envPath -Key 'CACHE_STORE' -Value 'file'
Set-EnvValue -Path $envPath -Key 'GROM_LEGACY_ANALISE_SYNC_ENABLED' -Value 'true'

if ($legacyDbPath) {
    Set-EnvValue -Path $envPath -Key 'GROM_LEGACY_ANALISE_DB_PATH' -Value (($legacyDbPath.Path) -replace '\\', '/')
}

if (-not (Test-Path -LiteralPath (Split-Path -Parent $dbPath))) {
    New-Item -ItemType Directory -Path (Split-Path -Parent $dbPath) | Out-Null
}

if ($Fresh) {
    if (Test-Path -LiteralPath $dbJournalPath) {
        try {
            Remove-Item -LiteralPath $dbJournalPath -Force
        } catch {
            Write-Warning ('Nao foi possivel remover o journal SQLite anterior: {0}' -f $_.Exception.Message)
        }
    }
}

if (-not (Test-Path -LiteralPath $dbPath)) {
    New-Item -ItemType File -Path $dbPath -Force | Out-Null
}

Push-Location $runtimePath

try {
    Invoke-Artisan -PhpPath $phpPath -RuntimePath $runtimePath -Arguments @('artisan', 'key:generate', '--force', '--no-interaction')
    Invoke-Artisan -PhpPath $phpPath -RuntimePath $runtimePath -Arguments @('artisan', 'optimize:clear', '--no-interaction')

    if ($Fresh) {
        Invoke-Artisan -PhpPath $phpPath -RuntimePath $runtimePath -Arguments @('artisan', 'migrate:fresh', '--seed', '--force', '--no-interaction')
    } else {
        Invoke-Artisan -PhpPath $phpPath -RuntimePath $runtimePath -Arguments @('artisan', 'migrate', '--seed', '--force', '--no-interaction')
    }

    Invoke-Artisan -PhpPath $phpPath -RuntimePath $runtimePath -Arguments @('artisan', 'db:seed', '--class=Database\Seeders\GromPilotDemoSeeder', '--force', '--no-interaction')

    Write-Host ''
    Write-Host 'Piloto local preparado com sucesso.' -ForegroundColor Green
    Write-Host ('URL..............: {0}/login' -f $appUrl)
    Write-Host ('Acesso de teste..: {0}/acesso-teste' -f $appUrl)
    Write-Host 'Usuario admin....: admin (primeiro acesso exige troca de senha)'
    Write-Host 'Usuario gestor...: gestor.demo'
    Write-Host 'Usuario operador.: operador.demo'
    Write-Host ('Senha piloto.....: {0}' -f $adminPassword)
    Write-Host ('Banco local......: {0}' -f $dbPath)

    if ($legacyDbPath) {
        Write-Host ('Base legada......: {0}' -f $legacyDbPath.Path)
    }

    if ($PrepareOnly) {
        return
    }

    if ($SmokeTest) {
        $serverProcess = $null

        try {
            $serverProcess = Start-Process -FilePath $phpPath `
                -ArgumentList @('artisan', 'serve', "--host=$BindHost", "--port=$Port") `
                -WorkingDirectory $runtimePath `
                -PassThru

            $response = $null
            for ($attempt = 0; $attempt -lt 15; $attempt++) {
                Start-Sleep -Milliseconds 600
                try {
                    $response = Invoke-WebRequest -Uri ('{0}/login' -f $appUrl) -UseBasicParsing -TimeoutSec 10
                    break
                } catch {
                    if ($attempt -eq 14) {
                        throw
                    }
                }
            }

            $hasCurrentLoginMarkers = $response.Content -match 'Acesso\s+Grom\.Seg' -and $response.Content -match 'name="login"'

            if (-not $response -or $response.StatusCode -ne 200 -or -not $hasCurrentLoginMarkers) {
                throw 'O smoke test HTTP nao confirmou a tela de login do piloto.'
            }

            Write-Host 'Smoke test HTTP..: OK' -ForegroundColor Green
            return
        } finally {
            if ($serverProcess -and -not $serverProcess.HasExited) {
                Stop-Process -Id $serverProcess.Id -Force
            }
        }
    }

    Write-Host ''
    Write-Host 'Iniciando servidor local do piloto...' -ForegroundColor Cyan
    & $phpPath artisan serve "--host=$BindHost" "--port=$Port"
} finally {
    Pop-Location
}
