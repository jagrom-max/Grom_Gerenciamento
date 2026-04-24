param(
    [string] $ComposeFile = 'infra/docker-compose.prod.yml',
    [string] $BaseUrl = 'http://127.0.0.1:8080',
    [string] $ExternalLoginUrl = 'https://grom.seg.br/login',
    [switch] $Build,
    [switch] $SkipComposeUp,
    [switch] $SkipMigrate,
    [switch] $SkipSmoke,
    [switch] $SkipLoad,
    [switch] $SkipExternalProbe,
    [switch] $RequireExternalProbe,
    [int] $LoadDurationSec = 30,
    [int] $LoadLoginConcurrency = 20,
    [int] $LoadAppRps = 50,
    [int] $LoadPdfRps = 10,
    [int] $LoadSessionPoolSize = 8,
    [int] $LoadMaxParallelism = 24,
    [switch] $FailOnThreshold
)

$ErrorActionPreference = 'Stop'

function Write-Section {
    param([string] $Text)

    Write-Host ''
    Write-Host ('=== {0} ===' -f $Text) -ForegroundColor Cyan
}

function Get-ComposeMode {
    if (Get-Command docker -ErrorAction SilentlyContinue) {
        & docker compose version *> $null
        if ($LASTEXITCODE -eq 0) {
            return 'docker'
        }
    }

    if (Get-Command docker-compose -ErrorAction SilentlyContinue) {
        return 'docker-compose'
    }

    throw 'Docker Compose nao encontrado. Instale Docker Engine + Docker Compose (plugin v2 ou docker-compose legacy).'
}

function Invoke-Compose {
    param(
        [string] $Mode,
        [string[]] $Arguments
    )

    if ($Mode -eq 'docker') {
        & docker compose @Arguments
    } else {
        & docker-compose @Arguments
    }

    if ($LASTEXITCODE -ne 0) {
        throw ('Falha ao executar Compose: {0}' -f ($Arguments -join ' '))
    }
}

function Invoke-PowerShellScript {
    param(
        [string] $ScriptPath,
        [string[]] $Arguments
    )

    & powershell -ExecutionPolicy Bypass -File $ScriptPath @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw ('Falha ao executar script: {0}' -f $ScriptPath)
    }
}

function Test-ExternalLoginUrl {
    param([string] $Url)

    try {
        $response = Invoke-WebRequest -Uri $Url -Method Get -TimeoutSec 20
        return [pscustomobject]@{
            ok = $true
            statusCode = [int] $response.StatusCode
            error = $null
        }
    } catch {
        return [pscustomobject]@{
            ok = $false
            statusCode = 0
            error = $_.Exception.Message
        }
    }
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Resolve-Path (Join-Path $scriptRoot '..')
$composePath = Join-Path $projectRoot $ComposeFile
$smokePath = Join-Path $scriptRoot 'Test-GromWebSmoke.ps1'
$loadPath = Join-Path $scriptRoot 'load-test.ps1'

if (-not (Test-Path -LiteralPath $composePath)) {
    throw ('Compose file nao encontrado: {0}' -f $composePath)
}

if (-not (Test-Path -LiteralPath $smokePath)) {
    throw ('Script de smoke nao encontrado: {0}' -f $smokePath)
}

if (-not (Test-Path -LiteralPath $loadPath)) {
    throw ('Script de carga nao encontrado: {0}' -f $loadPath)
}

$mode = Get-ComposeMode
$results = New-Object System.Collections.Generic.List[object]

Push-Location $projectRoot

try {
    Write-Section 'Preflight'
    Write-Host ('Compose mode.....: {0}' -f $mode)
    Write-Host ('Compose file.....: {0}' -f $composePath)
    Write-Host ('Base URL.........: {0}' -f $BaseUrl)
    Write-Host ('External URL.....: {0}' -f $ExternalLoginUrl)

    if (Get-Command php -ErrorAction SilentlyContinue) {
        Write-Host 'Host PHP.........: disponivel (php no PATH)'
    } else {
        Write-Host 'Host PHP.........: indisponivel (nao bloqueia, sera usado php no container app)' -ForegroundColor Yellow
    }

    Write-Section 'Compose / Containers'

    if (-not $SkipComposeUp) {
        $upArgs = @('-f', $composePath, 'up', '-d')
        if ($Build) {
            $upArgs += '--build'
        }

        Invoke-Compose -Mode $mode -Arguments $upArgs
        $results.Add([pscustomobject]@{ step = 'compose_up'; ok = $true; detail = 'OK' })
    } else {
        $results.Add([pscustomobject]@{ step = 'compose_up'; ok = $true; detail = 'SKIPPED' })
    }

    Invoke-Compose -Mode $mode -Arguments @('-f', $composePath, 'ps')
    Invoke-Compose -Mode $mode -Arguments @('-f', $composePath, 'exec', '-T', 'app', 'php', '-v')
    $results.Add([pscustomobject]@{ step = 'container_php'; ok = $true; detail = 'OK' })

    Write-Section 'Migrations'

    if (-not $SkipMigrate) {
        Invoke-Compose -Mode $mode -Arguments @('-f', $composePath, 'exec', '-T', 'app', 'php', 'artisan', 'migrate', '--force', '--no-interaction')
        $results.Add([pscustomobject]@{ step = 'migrate'; ok = $true; detail = 'OK' })
    } else {
        $results.Add([pscustomobject]@{ step = 'migrate'; ok = $true; detail = 'SKIPPED' })
    }

    Write-Section 'Smoke HTTP'

    if (-not $SkipSmoke) {
        Invoke-PowerShellScript -ScriptPath $smokePath -Arguments @('-BaseUrl', $BaseUrl)
        $results.Add([pscustomobject]@{ step = 'smoke'; ok = $true; detail = 'OK' })
    } else {
        $results.Add([pscustomobject]@{ step = 'smoke'; ok = $true; detail = 'SKIPPED' })
    }

    Write-Section 'Load Test'

    if (-not $SkipLoad) {
        $loadArgs = @(
            '-BaseUrl', $BaseUrl,
            '-DurationSec', $LoadDurationSec,
            '-LoginConcurrency', $LoadLoginConcurrency,
            '-AppRps', $LoadAppRps,
            '-PdfRps', $LoadPdfRps,
            '-SessionPoolSize', $LoadSessionPoolSize,
            '-MaxParallelism', $LoadMaxParallelism
        )

        if ($FailOnThreshold) {
            $loadArgs += '-FailOnThreshold'
        }

        Invoke-PowerShellScript -ScriptPath $loadPath -Arguments $loadArgs
        $results.Add([pscustomobject]@{ step = 'load_test'; ok = $true; detail = 'OK' })
    } else {
        $results.Add([pscustomobject]@{ step = 'load_test'; ok = $true; detail = 'SKIPPED' })
    }

    Write-Section 'External Probe'

    if (-not $SkipExternalProbe) {
        $probe = Test-ExternalLoginUrl -Url $ExternalLoginUrl
        if ($probe.ok) {
            Write-Host ('External login....: HTTP {0}' -f $probe.statusCode) -ForegroundColor Green
            $results.Add([pscustomobject]@{ step = 'external_probe'; ok = $true; detail = ('HTTP {0}' -f $probe.statusCode) })
        } else {
            Write-Host ('External login....: FALHA ({0})' -f $probe.error) -ForegroundColor Yellow
            $results.Add([pscustomobject]@{ step = 'external_probe'; ok = $false; detail = $probe.error })

            if ($RequireExternalProbe) {
                throw 'Probe externa obrigatoria falhou.'
            }
        }
    } else {
        $results.Add([pscustomobject]@{ step = 'external_probe'; ok = $true; detail = 'SKIPPED' })
    }

    Write-Section 'Resumo'
    $results | Format-Table step, ok, detail -AutoSize

    if (@($results | Where-Object { -not $_.ok }).Count -gt 0 -and $RequireExternalProbe) {
        throw 'Go-live check finalizou com falhas em etapa obrigatoria.'
    }

    Write-Host ''
    Write-Host 'Go-live check finalizado.' -ForegroundColor Green
} finally {
    Pop-Location
}
