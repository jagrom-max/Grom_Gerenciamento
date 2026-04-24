param(
    [switch] $SkipTests,
    [switch] $SkipComposerAudit,
    [switch] $RunSmoke,
    [string] $SmokeBaseUrl = 'http://127.0.0.1:8080',
    [string] $SmokeUsername = 'gestor.demo',
    [string] $SmokePassword,
    [int] $SmokeYear = (Get-Date).Year,
    [int] $SmokeMonth = (Get-Date).Month
)

$ErrorActionPreference = 'Stop'

function Write-Step {
    param([string] $Message)
    Write-Host "`n>>> $Message" -ForegroundColor Cyan
}

function Write-Ok {
    param([string] $Message)
    Write-Host "    OK: $Message" -ForegroundColor Green
}

function Write-Fail {
    param([string] $Message)
    Write-Host "    FALHA: $Message" -ForegroundColor Red
}

function Resolve-ToolPath {
    param(
        [string] $RuntimePath,
        [string] $RelativeToolPath,
        [string] $FallbackCommand
    )

    $candidate = Join-Path $RuntimePath $RelativeToolPath
    if (Test-Path -LiteralPath $candidate) {
        return $candidate
    }

    return $FallbackCommand
}

function Invoke-QualityStep {
    param(
        [string] $Name,
        [scriptblock] $Action
    )

    $startedAt = Get-Date
    try {
        & $Action
        $durationSec = [Math]::Round(((Get-Date) - $startedAt).TotalSeconds, 2)
        return [pscustomobject]@{
            step = $Name
            ok = $true
            duration_sec = $durationSec
            details = 'OK'
        }
    } catch {
        $durationSec = [Math]::Round(((Get-Date) - $startedAt).TotalSeconds, 2)
        return [pscustomobject]@{
            step = $Name
            ok = $false
            duration_sec = $durationSec
            details = $_.Exception.Message
        }
    }
}

$projectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$runtimePath = Join-Path $projectRoot 'runtime'
if (-not (Test-Path -LiteralPath $runtimePath)) {
    throw "Diretorio runtime nao encontrado em: $runtimePath"
}

$phpBin = Resolve-ToolPath -RuntimePath $runtimePath -RelativeToolPath '..\_toolchain\bin\php.cmd' -FallbackCommand 'php'
$composerBin = Resolve-ToolPath -RuntimePath $runtimePath -RelativeToolPath '..\_toolchain\bin\composer.cmd' -FallbackCommand 'composer'
$smokeScript = Join-Path $projectRoot 'scripts\Test-GromWebSmoke.ps1'

Write-Host '=========================================' -ForegroundColor Yellow
Write-Host ' GROM Quality Gate (Pre-Deploy)' -ForegroundColor Yellow
Write-Host '=========================================' -ForegroundColor Yellow
Write-Host "Projeto : $projectRoot"
Write-Host "Runtime : $runtimePath"

$results = @()

Push-Location $runtimePath
try {
    if (-not $SkipTests) {
        Write-Step 'Executando suite de testes (artisan test)...'
        $results += Invoke-QualityStep -Name 'tests' -Action {
            & $phpBin artisan test --stop-on-failure 2>&1 | Out-Host
            if ($LASTEXITCODE -ne 0) {
                throw "Suite de testes retornou codigo $LASTEXITCODE."
            }
        }

        if ($results[-1].ok) {
            Write-Ok 'Testes concluidos sem falhas.'
        } else {
            Write-Fail $results[-1].details
        }
    }

    if (-not $SkipComposerAudit) {
        Write-Step 'Executando auditoria de seguranca do Composer...'
        $results += Invoke-QualityStep -Name 'composer_audit' -Action {
            $previousPreference = $ErrorActionPreference
            $ErrorActionPreference = 'Continue'
            try {
                $auditOutput = (& $composerBin audit --no-interaction 2>&1 | Out-String)
                $exitCode = $LASTEXITCODE
            } finally {
                $ErrorActionPreference = $previousPreference
            }

            if ($auditOutput -match 'No security vulnerability advisories found') {
                Write-Host $auditOutput.Trim()
                return
            }

            if ($exitCode -ne 0) {
                throw "Composer audit falhou (codigo $exitCode): $($auditOutput.Trim())"
            }

            Write-Host $auditOutput.Trim()
        }

        if ($results[-1].ok) {
            Write-Ok 'Composer audit sem vulnerabilidades conhecidas.'
        } else {
            Write-Fail $results[-1].details
        }
    }
} finally {
    Pop-Location
}

if ($RunSmoke) {
    if (-not (Test-Path -LiteralPath $smokeScript)) {
        $results += [pscustomobject]@{
            step = 'smoke_http'
            ok = $false
            duration_sec = 0
            details = "Script de smoke nao encontrado em $smokeScript"
        }
    } else {
        Write-Step 'Executando smoke test HTTP ponta-a-ponta...'
        $results += Invoke-QualityStep -Name 'smoke_http' -Action {
            $args = @(
                '-NoProfile',
                '-ExecutionPolicy', 'Bypass',
                '-File', $smokeScript,
                '-BaseUrl', $SmokeBaseUrl,
                '-Username', $SmokeUsername,
                '-Year', $SmokeYear,
                '-Month', $SmokeMonth
            )

            if (-not [string]::IsNullOrWhiteSpace($SmokePassword)) {
                $args += @('-Password', $SmokePassword)
            }

            & powershell @args
            if ($LASTEXITCODE -ne 0) {
                throw "Smoke test falhou com codigo $LASTEXITCODE."
            }
        }

        if ($results[-1].ok) {
            Write-Ok 'Smoke test concluido com sucesso.'
        } else {
            Write-Fail $results[-1].details
        }
    }
}

Write-Host "`nResumo do Quality Gate:" -ForegroundColor Yellow
$results | Format-Table step, ok, duration_sec, details -AutoSize

$failed = @($results | Where-Object { -not $_.ok })
if ($failed.Count -gt 0) {
    Write-Host "`nQuality Gate: REPROVADO" -ForegroundColor Red
    exit 1
}

Write-Host "`nQuality Gate: APROVADO" -ForegroundColor Green
exit 0
