param(
    [string] $BaseUrl = 'http://127.0.0.1:8088',
    [string] $Username = 'gestor.demo',
    [string] $Password,
    [int] $Year = (Get-Date).Year,
    [int] $Month = (Get-Date).Month
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

function Get-SelectOptions {
    param(
        [string] $Html,
        [string] $SelectName
    )

    $selectPattern = '<select[^>]*name\s*=\s*["''][^"'']*' + [regex]::Escape($SelectName) + '[^"'']*["''][^>]*>(?<body>[\s\S]*?)</select>'
    $selectMatch = [regex]::Match($Html, $selectPattern, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)

    if (-not $selectMatch.Success) {
        return @()
    }

    $body = $selectMatch.Groups['body'].Value
    $optionMatches = [regex]::Matches($body, '<option[^>]*value\s*=\s*["'']([^"'']*)["''][^>]*>([\s\S]*?)</option>', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)

    $options = @()
    foreach ($optionMatch in $optionMatches) {
        $value = $optionMatch.Groups[1].Value.Trim()
        $textRaw = $optionMatch.Groups[2].Value
        $textNoTags = [regex]::Replace($textRaw, '<[^>]+>', '')
        $text = [System.Net.WebUtility]::HtmlDecode($textNoTags).Trim()

        $options += [pscustomobject]@{
            value = $value
            text = $text
        }
    }

    return $options
}

function Get-FirstWeekdayOfMonth {
    param(
        [int] $Ano,
        [int] $Mes
    )

    $day = Get-Date -Year $Ano -Month $Mes -Day 1
    while ($day.DayOfWeek -in @([System.DayOfWeek]::Saturday, [System.DayOfWeek]::Sunday)) {
        $day = $day.AddDays(1)
    }

    return $day.ToString('yyyy-MM-dd')
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
$monthPath = '/escalas?ano={0}&mes={1}' -f $Year, $Month
$printPath = '/escalas/imprimir?ano={0}&mes={1}&preview=1' -f $Year, $Month
$plantaoRelatorioPath = '/escalas/plantoes/relatorio?year={0}&month={1}' -f $Year, $Month
$assignDate = Get-FirstWeekdayOfMonth -Ano $Year -Mes $Month
$stamp = (Get-Date).ToString('yyMMddHHmmss')
$siglaOriginal = ('TST{0}' -f $stamp)
$siglaAtualizada = ('TSN{0}' -f $stamp)
$nomePlantao = ('Plantao Teste Consistencia {0}' -f $stamp)
$oldSiglaToken = ('({0})' -f $siglaOriginal)
$newSiglaToken = ('({0})' -f $siglaAtualizada)

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

$monthPage = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $monthPath) -WebSession $session -UseBasicParsing
$monthToken = Get-HtmlCsrfToken -Html $monthPage.Content

# Garante que a escala do mes exista para suportar a consolidacao textual por dia.
$null = Invoke-WebRequest `
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

$monthPage = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $monthPath) -WebSession $session -UseBasicParsing
$monthToken = Get-HtmlCsrfToken -Html $monthPage.Content

$relatorioPage = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $plantaoRelatorioPath) -WebSession $session -UseBasicParsing
$funcOptions = Get-SelectOptions -Html $relatorioPage.Content -SelectName 'funcionario_id'
$funcOption = $funcOptions | Where-Object { -not [string]::IsNullOrWhiteSpace($_.value) } | Select-Object -First 1
if (-not $funcOption) {
    throw 'Nao foi possivel localizar funcionario elegivel para o teste de plantao externo.'
}

$createPlantaoResponse = Invoke-WebRequest `
    -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path '/escalas/plantoes-externos') `
    -Method Post `
    -WebSession $session `
    -UseBasicParsing `
    -Body @{
        _token = $monthToken
        nome = $nomePlantao
        sigla = $siglaOriginal
        unidade = 'QA'
        regra = 'MESMO_DIA'
        observacao = 'Teste automatizado de consistencia plantao_externo.'
    } `
    -MaximumRedirection 10 `
    -ErrorAction Stop

$monthPage = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $monthPath) -WebSession $session -UseBasicParsing
$monthToken = Get-HtmlCsrfToken -Html $monthPage.Content

$relatorioPage = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $plantaoRelatorioPath) -WebSession $session -UseBasicParsing
$plantaoOptions = Get-SelectOptions -Html $relatorioPage.Content -SelectName 'plantao_id'
$plantaoOption = $plantaoOptions | Where-Object {
    $_.value -and $_.text -match [regex]::Escape($siglaOriginal) -and $_.text -match [regex]::Escape($nomePlantao)
} | Select-Object -First 1

if (-not $plantaoOption) {
    throw 'Nao foi possivel localizar o plantao externo criado para o teste.'
}

$assignResponse = Invoke-WebRequest `
    -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path '/escalas/plantoes-funcionarios') `
    -Method Post `
    -WebSession $session `
    -UseBasicParsing `
    -Body @{
        _token = $monthToken
        funcionario_id = $funcOption.value
        plantao_externo_id = $plantaoOption.value
        data = $assignDate
    } `
    -MaximumRedirection 10 `
    -ErrorAction Stop

$printBeforeUpdate = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $printPath) -WebSession $session -UseBasicParsing
$printBeforeContent = $printBeforeUpdate.Content
$containsOldBefore = $printBeforeContent -match [regex]::Escape($oldSiglaToken)

if (-not $containsOldBefore) {
    $monthStart = Get-Date -Year $Year -Month $Month -Day 1

    for ($offset = 1; $offset -lt 28 -and -not $containsOldBefore; $offset++) {
        $candidate = $monthStart.AddDays($offset)
        if ($candidate.Month -ne $Month) {
            break
        }

        if ($candidate.DayOfWeek -in @([System.DayOfWeek]::Saturday, [System.DayOfWeek]::Sunday)) {
            continue
        }

        $candidateDate = $candidate.ToString('yyyy-MM-dd')

        $assignResponse = Invoke-WebRequest `
            -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path '/escalas/plantoes-funcionarios') `
            -Method Post `
            -WebSession $session `
            -UseBasicParsing `
            -Body @{
                _token = $monthToken
                funcionario_id = $funcOption.value
                plantao_externo_id = $plantaoOption.value
                data = $candidateDate
            } `
            -MaximumRedirection 10 `
            -ErrorAction SilentlyContinue

        $printBeforeUpdate = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $printPath) -WebSession $session -UseBasicParsing
        $printBeforeContent = $printBeforeUpdate.Content
        $containsOldBefore = $printBeforeContent -match [regex]::Escape($oldSiglaToken)

        if ($containsOldBefore) {
            $assignDate = $candidateDate
            break
        }
    }
}

$updatePlantaoResponse = Invoke-WebRequest `
    -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path ('/escalas/plantoes-externos/{0}' -f $plantaoOption.value)) `
    -Method Post `
    -WebSession $session `
    -UseBasicParsing `
    -Body @{
        _token = $monthToken
        _method = 'PUT'
        nome = $nomePlantao
        sigla = $siglaAtualizada
        unidade = 'QA'
        regra = 'MESMO_DIA'
        observacao = 'Teste automatizado de consistencia plantao_externo (atualizado).'
    } `
    -MaximumRedirection 10 `
    -ErrorAction Stop

$printAfterUpdate = Invoke-WebRequest -Uri (Get-AbsoluteUrl -Base $resolvedBaseUrl -Path $printPath) -WebSession $session -UseBasicParsing
$printAfterContent = $printAfterUpdate.Content

$containsOldAfter = $printAfterContent -match [regex]::Escape($oldSiglaToken)
$containsNewAfter = $printAfterContent -match [regex]::Escape($newSiglaToken)

$result = [pscustomobject]@{
    createPlantaoStatus = [int] $createPlantaoResponse.StatusCode
    assignPlantaoStatus = [int] $assignResponse.StatusCode
    updatePlantaoStatus = [int] $updatePlantaoResponse.StatusCode
    assignDate = $assignDate
    funcionario = $funcOption.text
    siglaOriginal = $siglaOriginal
    siglaAtualizada = $siglaAtualizada
    printHasOldMarkerBeforeUpdate = $containsOldBefore
    printHasOldMarkerAfterUpdate = $containsOldAfter
    printHasNewMarkerAfterUpdate = $containsNewAfter
}

$result | Format-List

$ok = $true
$ok = $ok -and $result.createPlantaoStatus -eq 200
$ok = $ok -and $result.assignPlantaoStatus -eq 200
$ok = $ok -and $result.updatePlantaoStatus -eq 200
$ok = $ok -and $result.printHasOldMarkerBeforeUpdate
$ok = $ok -and (-not $result.printHasOldMarkerAfterUpdate)
$ok = $ok -and $result.printHasNewMarkerAfterUpdate

if (-not $ok) {
    throw 'A validacao de consistencia dos plantoes externos falhou.'
}

Write-Host ''
Write-Host ('Consistencia de plantao externo validada: {0}/{1:00}' -f $Year, $Month) -ForegroundColor Green
