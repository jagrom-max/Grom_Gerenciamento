param(
    [string] $BaseUrl = 'http://127.0.0.1:8088',
    [string] $Username = 'gestor.demo',
    [string] $Password,
    [int] $Year = 2026,
    [int] $Month = 6,
    [string] $CloseObservation = 'Fechamento validado automaticamente pelo teste de ciclo de vida.'
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

function Get-AbsoluteUrl {
    param(
        [string] $Base,
        [string] $Path
    )

    return [System.Uri]::new([System.Uri]::new($Base), $Path).AbsoluteUri
}

function Get-HtmlCsrfToken {
    param([string] $Html)

    $patterns = @(
        'name="_token"\s+value="([^"]+)"',
        'value="([^"]+)"\s+name="_token"'
    )

    foreach ($pattern in $patterns) {
        $match = [regex]::Match($Html, $pattern)
        if ($match.Success) {
            return $match.Groups[1].Value
        }
    }

    throw 'Nao foi possivel localizar o token CSRF no HTML.'
}

function Get-EscalaState {
    param([string] $Html)

    $versionMatch = [regex]::Match($Html, 'v(\d+)')
    $version = if ($versionMatch.Success) { [int] $versionMatch.Groups[1].Value } else { 0 }

    $bannerProvisoria = ($Html -match 'Escala\s+PROVIS')
    $bannerDefinitiva = ($Html -match 'Escala\s+DEFIN')

    return [pscustomobject]@{
        hasEscala = ($Html -match 'Escala Mensal')
        hasVersion = $version -gt 0
        version = $version
        isProvisoria = $bannerProvisoria
        isDefinitiva = $bannerDefinitiva
        hasNovaVersaoButton = ($Html -match 'Nova Vers[aã]o')
        hasFecharButton = ($Html -match 'Gravar como Definitiva')
        hasCloseObservation = ($Html -match [regex]::Escape($CloseObservation))
    }
}

function Invoke-Login {
    param(
        [string] $Base,
        [string] $Login,
        [string] $Secret,
        [Microsoft.PowerShell.Commands.WebRequestSession] $Session
    )

    $loginPage = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $Base -Path '/login') -WebSession $Session -UseBasicParsing
    $token = Get-HtmlCsrfToken -Html $loginPage.Content

    $null = Invoke-WebRequest `
        -Uri (Get-AbsoluteUrl -Base $Base -Path '/login') `
        -Method Post `
        -WebSession $Session `
        -UseBasicParsing `
        -Body @{
            _token = $token
            login = $Login
            password = $Secret
            redirect_to = 'dashboard'
        } `
        -MaximumRedirection 10 `
        -ErrorAction SilentlyContinue
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Resolve-Path (Join-Path $scriptRoot '..')
$envPath = Join-Path $projectRoot 'runtime\.env'

if (-not $Password) {
    $Password = Get-EnvValue -Path $envPath -Key 'GROM_BOOTSTRAP_ADMIN_PASSWORD'
}

if (-not $Password) {
    $Password = 'GromPilot#2026'
}

$resolvedBaseUrl = $BaseUrl.TrimEnd('/')
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

Invoke-Login -Base $resolvedBaseUrl -Login $Username -Secret $Password -Session $session

$monthPath = '/escalas?ano={0}&mes={1}' -f $Year, $Month
$monthPage = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $monthPath) -WebSession $session -UseBasicParsing
$monthToken = Get-HtmlCsrfToken -Html $monthPage.Content

$generateResponse = Invoke-WebRequest `
    -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path '/escalas/gerar') `
    -Method Post `
    -WebSession $session `
    -UseBasicParsing `
    -Body @{
        _token = $monthToken
        ano = [string] $Year
        mes = [string] $Month
    } `
    -MaximumRedirection 10 `
    -ErrorAction SilentlyContinue

$afterGenerate = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $monthPath) -WebSession $session -UseBasicParsing
$stateAfterGenerate = Get-EscalaState -Html $afterGenerate.Content

if (-not $stateAfterGenerate.hasEscala -or -not $stateAfterGenerate.hasVersion -or -not $stateAfterGenerate.isProvisoria) {
    throw 'A escala nao ficou em estado provisoria apos a geracao.'
}

$closeToken = Get-HtmlCsrfToken -Html $afterGenerate.Content

$closeResponse = Invoke-WebRequest `
    -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path '/escalas/fechar') `
    -Method Post `
    -WebSession $session `
    -UseBasicParsing `
    -Body @{
        _token = $closeToken
        ano = [string] $Year
        mes = [string] $Month
        obs = $CloseObservation
    } `
    -MaximumRedirection 10 `
    -ErrorAction Stop

$afterClose = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $monthPath) -WebSession $session -UseBasicParsing
$stateAfterClose = Get-EscalaState -Html $afterClose.Content

if (-not $stateAfterClose.isDefinitiva -or $stateAfterClose.version -ne $stateAfterGenerate.version) {
    throw 'A escala nao ficou definitiva apos o fechamento.'
}

$newVersionToken = Get-HtmlCsrfToken -Html $afterClose.Content

$newVersionResponse = Invoke-WebRequest `
    -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path '/escalas/nova-versao') `
    -Method Post `
    -WebSession $session `
    -UseBasicParsing `
    -Body @{
        _token = $newVersionToken
        ano = [string] $Year
        mes = [string] $Month
    } `
    -MaximumRedirection 10 `
    -ErrorAction Stop

$afterNewVersion = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $monthPath) -WebSession $session -UseBasicParsing
$stateAfterNewVersion = Get-EscalaState -Html $afterNewVersion.Content

if (-not $stateAfterNewVersion.isProvisoria) {
    throw 'A nova versao nao ficou em estado provisoria.'
}

if ($stateAfterNewVersion.version -ne ($stateAfterClose.version + 1)) {
    throw 'A nova versao nao incrementou corretamente o numero da versao.'
}

[pscustomobject]@{
    generateStatus = [int] $generateResponse.StatusCode
    closeStatus = [int] $closeResponse.StatusCode
    newVersionStatus = [int] $newVersionResponse.StatusCode
    versionAfterGenerate = $stateAfterGenerate.version
    versionAfterClose = $stateAfterClose.version
    versionAfterNewVersion = $stateAfterNewVersion.version
    afterGenerateProvisoria = $stateAfterGenerate.isProvisoria
    afterCloseDefinitiva = $stateAfterClose.isDefinitiva
    afterCloseHasObservation = $stateAfterClose.hasCloseObservation
    afterNewVersionProvisoria = $stateAfterNewVersion.isProvisoria
} | Format-List

Write-Host ''
Write-Host ('Ciclo de vida da escala validado: {0}/{1:00}' -f $Year, $Month) -ForegroundColor Green