param(
    [switch] $Build,
    [switch] $Fresh,
    [switch] $NoSeed,
    [switch] $SmokeTest,
    [switch] $EscalaMensalTest,
    [switch] $EscalaLifecycleTest,
    [switch] $EscalaPlantaoConsistencyTest,
    [switch] $ProdutividadeConsolidacaoTest,
    [int] $EscalaMensalYear = 0,
    [int] $EscalaMensalMonth = 0,
    [int] $EscalaLifecycleYear = 0,
    [int] $EscalaLifecycleMonth = 0,
    [switch] $LoadTest,
    [switch] $LoadTestFailOnThreshold,
    [int] $LoadTestDurationSec = 10,
    [int] $LoadTestLoginConcurrency = 6,
    [int] $LoadTestAppRps = 8,
    [int] $LoadTestPdfRps = 2,
    [int] $LoadTestSessionPoolSize = 6,
    [int] $LoadTestMaxParallelism = 10,
    [switch] $Down,
    [string] $ComposeFile = 'infra/docker-compose.yml'
)

$ErrorActionPreference = 'Stop'

function Invoke-Compose {
    param([string[]] $Arguments)

    & docker-compose @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw ('Falha ao executar docker-compose {0}' -f ($Arguments -join ' '))
    }
}

function Test-CommandAvailable {
    param([string] $Name)

    return $null -ne (Get-Command $Name -ErrorAction SilentlyContinue)
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Resolve-Path (Join-Path $scriptRoot '..')
$composePath = Join-Path $projectRoot $ComposeFile
$runtimePath = Join-Path $projectRoot 'runtime'
$envPath = Join-Path $runtimePath '.env'
$envExamplePath = Join-Path $runtimePath '.env.example'
$smokeScriptPath = Join-Path $projectRoot 'scripts\Test-GromWebSmoke.ps1'
$escalaMensalScriptPath = Join-Path $projectRoot 'scripts\Test-GromEscalaMensal.ps1'
$escalaLifecycleScriptPath = Join-Path $projectRoot 'scripts\Test-GromEscalaLifecycle.ps1'
$escalaPlantaoConsistencyScriptPath = Join-Path $projectRoot 'scripts\Test-GromEscalaPlantaoConsistency.ps1'
$produtividadeConsolidacaoScriptPath = Join-Path $projectRoot 'scripts\Test-GromProdutividadeConsolidacao.ps1'
$loadTestScriptPath = Join-Path $projectRoot 'scripts\load-test.ps1'

if ($EscalaMensalYear -le 0) {
    $EscalaMensalYear = (Get-Date).Year
}
if ($EscalaMensalMonth -le 0) {
    $EscalaMensalMonth = (Get-Date).Month
}
if ($EscalaLifecycleYear -le 0) {
    $EscalaLifecycleYear = $EscalaMensalYear
}
if ($EscalaLifecycleMonth -le 0) {
    if ($EscalaMensalMonth -eq 12) {
        $EscalaLifecycleMonth = 1
        $EscalaLifecycleYear = $EscalaMensalYear + 1
    } else {
        $EscalaLifecycleMonth = $EscalaMensalMonth + 1
    }
}

if (-not (Test-CommandAvailable -Name 'docker-compose')) {
    throw 'docker-compose nao foi encontrado no PATH. Instale Docker Desktop com suporte ao Compose para usar este fluxo.'
}

if (-not (Test-Path -LiteralPath $composePath)) {
    throw ('Arquivo de compose nao encontrado: {0}' -f $composePath)
}

if (-not (Test-Path -LiteralPath $envPath)) {
    if (-not (Test-Path -LiteralPath $envExamplePath)) {
        throw 'Nenhum .env base encontrado em runtime/.'
    }

    Copy-Item -LiteralPath $envExamplePath -Destination $envPath -Force
}

Push-Location $projectRoot

try {
    if ($Down) {
        Invoke-Compose -Arguments @('-f', $composePath, 'down', '--remove-orphans')
        return
    }

    $upArgs = @('-f', $composePath, 'up', '-d')
    if ($Build) {
        $upArgs += '--build'
    }

    Invoke-Compose -Arguments $upArgs

    Invoke-Compose -Arguments @('-f', $composePath, 'exec', '-T', 'app', 'php', 'artisan', 'key:generate', '--force', '--no-interaction')
    Invoke-Compose -Arguments @('-f', $composePath, 'exec', '-T', 'app', 'php', 'artisan', 'optimize:clear', '--no-interaction')

    $migrateCommand = if ($Fresh) { 'migrate:fresh' } else { 'migrate' }
    $migrateArgs = @('-f', $composePath, 'exec', '-T', 'app', 'php', 'artisan', $migrateCommand, '--force', '--no-interaction')
    if (-not $NoSeed) {
        $migrateArgs += '--seed'
    }

    Invoke-Compose -Arguments $migrateArgs

    if ($SmokeTest) {
        & powershell -ExecutionPolicy Bypass -File $smokeScriptPath -BaseUrl 'http://127.0.0.1:8080'

        if ($LASTEXITCODE -ne 0) {
            throw 'A stack subiu, mas o smoke test HTTP falhou.'
        }
    }

    if ($EscalaMensalTest) {
        & powershell -ExecutionPolicy Bypass -File $escalaMensalScriptPath `
            -BaseUrl 'http://127.0.0.1:8080' `
            -Year $EscalaMensalYear `
            -Month $EscalaMensalMonth

        if ($LASTEXITCODE -ne 0) {
            throw 'A stack subiu, mas o teste de escala mensal falhou.'
        }
    }

    if ($EscalaLifecycleTest) {
        & powershell -ExecutionPolicy Bypass -File $escalaLifecycleScriptPath `
            -BaseUrl 'http://127.0.0.1:8080' `
            -Year $EscalaLifecycleYear `
            -Month $EscalaLifecycleMonth

        if ($LASTEXITCODE -ne 0) {
            throw 'A stack subiu, mas o teste de ciclo de vida da escala falhou.'
        }
    }

    if ($EscalaPlantaoConsistencyTest) {
        & powershell -ExecutionPolicy Bypass -File $escalaPlantaoConsistencyScriptPath `
            -BaseUrl 'http://127.0.0.1:8080' `
            -Year $EscalaMensalYear `
            -Month $EscalaMensalMonth

        if ($LASTEXITCODE -ne 0) {
            throw 'A stack subiu, mas o teste de consistencia de plantao externo falhou.'
        }
    }
ProdutividadeConsolidacaoTest) {
        & powershell -ExecutionPolicy Bypass -File $produtividadeConsolidacaoScriptPath `
            -BaseUrl 'http://127.0.0.1:8080' `
            -Year $EscalaMensalYear `
            -Month $EscalaMensalMonth

        if ($LASTEXITCODE -ne 0) {
            throw 'A stack subiu, mas o teste de consolidacao de produtividade falhou.'
        }
    }

    if ($
    if ($LoadTest) {
        $loadTestArgs = @(
            '-ExecutionPolicy', 'Bypass',
            '-File', $loadTestScriptPath,
            '-BaseUrl', 'http://127.0.0.1:8080',
            '-Username', 'gestor.demo',
            '-DurationSec', $LoadTestDurationSec,
            '-LoginConcurrency', $LoadTestLoginConcurrency,
            '-AppRps', $LoadTestAppRps,
            '-PdfRps', $LoadTestPdfRps,
            '-SessionPoolSize', $LoadTestSessionPoolSize,
            '-MaxParallelism', $LoadTestMaxParallelism
        )

        if ($LoadTestFailOnThreshold) {
            $loadTestArgs += '-FailOnThreshold'
        }

        & powershell @loadTestArgs

        if ($LASTEXITCODE -ne 0) {
            throw 'A stack subiu, mas o load test falhou.'
        }
    }('Produtividade....: opcional via -ProdutividadeConsolidacaoTest ({0}/{1:00})' -f $EscalaMensalYear, $EscalaMensalMonth)
    Write-Host 

    Write-Host ''
    Write-Host 'Stack Docker local preparada com sucesso.' -ForegroundColor Green
    Write-Host 'URL..............: http://127.0.0.1:8080/login'
    Write-Host ('Compose file.....: {0}' -f $composePath)
    Write-Host 'Aplicacao........: docker-compose -f infra/docker-compose.yml ps'
    Write-Host 'Smoke test.......: opcional via -SmokeTest'
    Write-Host ('Escala mensal....: opcional via -EscalaMensalTest ({0}/{1:00})' -f $EscalaMensalYear, $EscalaMensalMonth)
    Write-Host ('Lifecycle escala.: opcional via -EscalaLifecycleTest ({0}/{1:00})' -f $EscalaLifecycleYear, $EscalaLifecycleMonth)
    Write-Host ('Plantao ext......: opcional via -EscalaPlantaoConsistencyTest ({0}/{1:00})' -f $EscalaMensalYear, $EscalaMensalMonth)
    Write-Host 'Load test........: opcional via -LoadTest'
    Write-Host 'Parar stack......: powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebDocker.ps1 -Down'
} finally {
    Pop-Location
}