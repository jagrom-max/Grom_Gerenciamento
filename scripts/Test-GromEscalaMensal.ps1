param(
    [string] $BaseUrl = 'http://127.0.0.1:8088',
    [string] $Username = 'gestor.demo',
    [string] $Password,
    [int] $Year = (Get-Date).Year,
    [int] $Month = (Get-Date).Month,
    [switch] $RequireGeneration
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

function Get-AbsoluteUrl {
    param(
        [string] $Base,
        [string] $Path
    )

    return [System.Uri]::new([System.Uri]::new($Base), $Path).AbsoluteUri
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
$monthLabel = [Globalization.CultureInfo]::GetCultureInfo('pt-BR').TextInfo.ToTitleCase(([datetime]::new($Year, $Month, 1)).ToString('MMMM', [Globalization.CultureInfo]::GetCultureInfo('pt-BR')))

$loginPage = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path '/login') -SessionVariable session -UseBasicParsing
$loginToken = Get-HtmlCsrfToken -Html $loginPage.Content

$null = Invoke-WebRequest `
    -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path '/login') `
    -Method Post `
    -WebSession $session `
    -UseBasicParsing `
    -Body @{
        _token = $loginToken
        login = $Username
        password = $Password
        redirect_to = 'dashboard'
    } `
    -MaximumRedirection 10 `
    -ErrorAction SilentlyContinue

$escalaUrl = '/escalas?ano={0}&mes={1}' -f $Year, $Month
$escalaPageBefore = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $escalaUrl) -WebSession $session -UseBasicParsing
$escalaToken = Get-HtmlCsrfToken -Html $escalaPageBefore.Content

$generationResponse = $null
$generationStatus = 0

try {
    $generationResponse = Invoke-WebRequest `
        -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path '/escalas/gerar') `
        -Method Post `
        -WebSession $session `
        -UseBasicParsing `
        -Body @{
            _token = $escalaToken
            ano = [string] $Year
            mes = [string] $Month
        } `
        -MaximumRedirection 10 `
        -ErrorAction Stop

    $generationStatus = [int] $generationResponse.StatusCode
} catch {
    if ($_.Exception.Response) {
        $generationStatus = [int] $_.Exception.Response.StatusCode
    } else {
        throw
    }
}

$escalaPageAfter = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $escalaUrl) -WebSession $session -UseBasicParsing
$printPage = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path ('/escalas/imprimir?ano={0}&mes={1}&preview=1' -f $Year, $Month)) -WebSession $session -UseBasicParsing

$escalaContent = $escalaPageAfter.Content
$printContent = $printPage.Content

$result = [pscustomobject]@{
    generationStatus = $generationStatus
    generationAccepted = ($generationStatus -eq 200)
    monthPageStatus = [int] $escalaPageAfter.StatusCode
    monthPageHasEscala = ($escalaContent -match 'Escala Mensal')
    monthPageHasVersion = ($escalaContent -match 'Vers[aã]o')
    monthPageHasMonth = ($escalaContent -match $monthLabel)
    printStatus = [int] $printPage.StatusCode
    printHasA4 = ($printContent -match 'size:\s*A4')
    printHasOnePageMarker = ($printContent -match '>1/1<')
    printHasMonth = ($printContent -match ([regex]::Escape($monthLabel) + '\s*/\s*' + $Year))
    printHasFixedFooter = ($printContent -match 'position:\s*fixed\s*!important')
    printHasBreakProtection = ($printContent -match 'page-break-inside:\s*avoid')
}

$result | Format-List

$ok = $true
$ok = $ok -and $result.monthPageStatus -eq 200
$ok = $ok -and $result.monthPageHasEscala
$ok = $ok -and $result.monthPageHasVersion
$ok = $ok -and $result.printStatus -eq 200
$ok = $ok -and $result.printHasA4
$ok = $ok -and $result.printHasOnePageMarker
$ok = $ok -and $result.printHasMonth
$ok = $ok -and $result.printHasFixedFooter
$ok = $ok -and $result.printHasBreakProtection

if ($RequireGeneration) {
    $ok = $ok -and $result.generationAccepted
}

if (-not $ok) {
    throw 'A validacao da escala mensal falhou.'
}

Write-Host ''
Write-Host ('Escala mensal validada: {0}/{1:00}' -f $Year, $Month) -ForegroundColor Green