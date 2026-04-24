#!/usr/bin/env powershell
<#
.SYNOPSIS
Teste end-to-end do modulo de boletins consolidado: upload unico, filtros, relatorio e exportacao CSV.
#>

param(
    [string] $BaseUrl = 'http://127.0.0.1:8088',
    [int] $Year = 2026,
    [int] $Month = 4
)

$ErrorActionPreference = 'Stop'

function Get-EnvValue {
    param([string] $Key, [string] $Default = '')
    $envFile = Join-Path (Split-Path $PSScriptRoot -Parent) 'runtime/.env'
    if (-not (Test-Path $envFile)) { return $Default }
    $line = Get-Content $envFile | Where-Object { $_ -match "^$Key=" } | Select-Object -First 1
    if (-not $line) { return $Default }
    return $line.Substring($Key.Length + 1)
}

function Get-HtmlCsrfToken {
    param([string] $Html)

    $patterns = @(
        'name="csrf-token"\s+content="([^"]+)"',
        "name='csrf-token'\\s+content='([^']+)'",
        '_token"\s*:\s*"([^"]+)"',
        "_token'\\s*:\\s*'([^']+)'",
        '<input[^>]*name="_token"[^>]*value="([^"]+)"',
        "<input[^>]*name='_token'[^>]*value='([^']+)'"
    )

    foreach ($pattern in $patterns) {
        $match = [regex]::Match($Html, $pattern, 'IgnoreCase')
        if ($match.Success) { return $match.Groups[1].Value }
    }

    throw 'CSRF token nao encontrado no HTML.'
}

function Get-CartorioOptions {
    param([string] $Html)

    $selectMatch = [regex]::Match($Html, '(?is)<select[^>]*name\s*=\s*"cartorio_id"[^>]*>(.*?)</select>')
    if (-not $selectMatch.Success) {
        $selectMatch = [regex]::Match($Html, "(?is)<select[^>]*name\\s*=\\s*'cartorio_id'[^>]*>(.*?)</select>")
    }
    if (-not $selectMatch.Success) {
        throw 'Select cartorio_id nao encontrado.'
    }

    $inner = $selectMatch.Groups[1].Value
    $options = @()
    $optMatches = [regex]::Matches($inner, '(?is)<option[^>]*value\s*=\s*"([^"]+)"[^>]*>(.*?)</option>')
    if ($optMatches.Count -eq 0) {
        $optMatches = [regex]::Matches($inner, "(?is)<option[^>]*value\\s*=\\s*'([^']+)'[^>]*>(.*?)</option>")
    }

    foreach ($match in $optMatches) {
        $value = $match.Groups[1].Value.Trim()
        $label = [System.Net.WebUtility]::HtmlDecode($match.Groups[2].Value)
        $label = [regex]::Replace($label, '<[^>]+>', '')
        $label = $label.Trim()
        if ($value -ne '') {
            $options += [pscustomobject]@{ value = $value; label = $label }
        }
    }

    return $options
}

function New-FormBody {
    param([hashtable] $Data)

    $pairs = @()
    foreach ($key in $Data.Keys) {
        $k = [uri]::EscapeDataString([string] $key)
        $v = [uri]::EscapeDataString([string] $Data[$key])
        $pairs += "$k=$v"
    }
    return ($pairs -join '&')
}

function Get-AbsoluteUrl {
    param([string] $Base, [string] $Path)
    if ($Path.StartsWith('http')) { return $Path }
    if ($Path.StartsWith('/')) { return ($Base.TrimEnd('/') + $Path) }
    return ($Base.TrimEnd('/') + '/' + $Path.TrimStart('/'))
}

function Invoke-Request {
    param(
        [string] $Method,
        [string] $Uri,
        [string] $Body = $null,
        [string] $ContentType = $null
    )

    $params = @{
        Method = $Method
        Uri = (Get-AbsoluteUrl $BaseUrl $Uri)
        WebSession = $global:WebSession
        UseBasicParsing = $true
        ErrorAction = 'Stop'
    }

    if (-not [string]::IsNullOrEmpty($Body)) {
        $params['Body'] = $Body
    }
    if (-not [string]::IsNullOrWhiteSpace($ContentType)) {
        $params['ContentType'] = $ContentType
    }

    return Invoke-WebRequest @params
}

Write-Host 'Teste de Boletins Consolidados' -ForegroundColor Cyan
Write-Host "BaseUrl=$BaseUrl Year=$Year Month=$Month" -ForegroundColor Cyan

$global:WebSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$adminPassword = Get-EnvValue -Key 'ADMIN_PASSWORD' -Default '03031981Gr**'

$loginPage = Invoke-Request -Method 'GET' -Uri '/login'
$csrf = Get-HtmlCsrfToken -Html $loginPage.Content
$loginBody = New-FormBody -Data @{
    login = 'gestor.demo'
    password = $adminPassword
    _token = $csrf
}
$loginRes = Invoke-Request -Method 'POST' -Uri '/login' -Body $loginBody -ContentType 'application/x-www-form-urlencoded'
if ($loginRes.StatusCode -notin @(200, 302)) {
    throw "Falha no login. Status=$($loginRes.StatusCode)"
}
Write-Host 'OK: login' -ForegroundColor Green

$boletinsPage = Invoke-Request -Method 'GET' -Uri '/produtividade/boletins'
if ($boletinsPage.StatusCode -ne 200) {
    throw "Falha ao abrir /produtividade/boletins. Status=$($boletinsPage.StatusCode)"
}
$cartorios = Get-CartorioOptions -Html $boletinsPage.Content
if ($cartorios.Count -eq 0) {
    throw 'Nenhum cartorio disponivel para teste.'
}
$cartorio = $cartorios[0]
Write-Host ("OK: dashboard boletins, cartorio=" + $cartorio.label) -ForegroundColor Green

$stamp = Get-Date -Format 'yyyyMMddHHmmss'
$csvFile = Join-Path $env:TEMP ("boletins-teste-$stamp.csv")
$csv = @"
SOURCEPROCESSKEY;SPJREF;NATUREZAS;DATAFATO;ANO;MES;NUMIP;NUMCNJ;FLAGRANTE;LAVRADOUNIDADE;CARTORIOLABEL;MPU
SK-BO-$stamp-001;223456789;AMEACA;2026-04-11;2026;4;;CNJ-BO-001;NAO;DDM;Cartorio Teste;MPU-001
SK-BO-$stamp-002;223456790;LESAO CORPORAL;2026-04-12;2026;4;IP-BO-002;CNJ-BO-002;SIM;DDM;Cartorio Teste;MPU-002
SK-BO-$stamp-003;223456791;ESTELIONATO;2026-04-13;2026;4;;;NAO;OUTRAS_UNIDADES;Cartorio Teste;
SK-BO-$stamp-004;223456792;FURTO;2026-04-14;2026;4;;CNJ-BO-004;SIM;OUTRAS_UNIDADES;Cartorio Teste;
"@
Set-Content -Path $csvFile -Value $csv -Encoding UTF8

$uploadPage = Invoke-Request -Method 'GET' -Uri '/produtividade/boletins'
$uploadToken = Get-HtmlCsrfToken -Html $uploadPage.Content
$boundary = '------------------------' + [guid]::NewGuid().ToString('N')
$nl = "`r`n"
$fileText = [System.Text.Encoding]::UTF8.GetString([System.IO.File]::ReadAllBytes($csvFile))

$multipart = "--$boundary$nl"
$multipart += "Content-Disposition: form-data; name=`"_token`"$nl$nl$uploadToken$nl"
$multipart += "--$boundary$nl"
$multipart += "Content-Disposition: form-data; name=`"cartorio_id`"$nl$nl$($cartorio.value)$nl"
$multipart += "--$boundary$nl"
$multipart += "Content-Disposition: form-data; name=`"year`"$nl$nl$Year$nl"
$multipart += "--$boundary$nl"
$multipart += "Content-Disposition: form-data; name=`"month`"$nl$nl$Month$nl"
$multipart += "--$boundary$nl"
$multipart += "Content-Disposition: form-data; name=`"source_file`"; filename=`"$(Split-Path $csvFile -Leaf)`"$nl"
$multipart += "Content-Type: text/csv$nl$nl"
$multipart += $fileText + $nl
$multipart += "--$boundary--$nl"

$upload = Invoke-Request -Method 'POST' -Uri '/produtividade/boletins/importar' -Body $multipart -ContentType "multipart/form-data; boundary=$boundary"
if ($upload.StatusCode -notin @(200, 302)) {
    throw "Falha no upload de boletins. Status=$($upload.StatusCode)"
}
Write-Host 'OK: upload unico em boletins' -ForegroundColor Green

$listUri = ('/produtividade/boletins?year={0}&month={1}&cartorio_id={2}&has_mpu=1&without_ip=1' -f $Year, $Month, $cartorio.value)
$listRes = Invoke-Request -Method 'GET' -Uri $listUri
if ($listRes.StatusCode -ne 200) {
    throw "Falha na listagem filtrada de boletins. Status=$($listRes.StatusCode)"
}
$listLooksValid = ($listRes.Content -match 'Com MPU|Sem IP|Boletins de ocorrencia|Flagrante|Nao-flagrante')
Write-Host ("OK: listagem filtrada de boletins, marcador=" + $listLooksValid) -ForegroundColor Green

$relUri = ('/produtividade/boletins/relatorio?year={0}&month={1}&cartorio_id={2}&has_mpu=1&without_ip=1' -f $Year, $Month, $cartorio.value)
$relRes = Invoke-Request -Method 'GET' -Uri $relUri
if ($relRes.StatusCode -ne 200) {
    throw "Falha no relatorio filtrado de boletins. Status=$($relRes.StatusCode)"
}
$relLooksValid = ($relRes.Content -match 'Relatorio de Boletins de Ocorrencia|Com MPU|Sem IP|Total BOs')
Write-Host ("OK: relatorio filtrado de boletins, marcador=" + $relLooksValid) -ForegroundColor Green

$expUri = ('/produtividade/boletins/exportar?year={0}&month={1}&cartorio_id={2}&has_mpu=1&without_ip=1' -f $Year, $Month, $cartorio.value)
$expRes = Invoke-Request -Method 'GET' -Uri $expUri
if ($expRes.StatusCode -ne 200) {
    throw "Falha na exportacao CSV de boletins. Status=$($expRes.StatusCode)"
}
$isCsv = ($expRes.Headers['Content-Type'] -match 'text/csv')
$hasDelimiter = ($expRes.Content -match ';')
$hasExpectedColumns = ($expRes.Content -match 'tipo;lavrado_unidade;mpu_numero;mpu_decisao;despacho_fundamentado;encaminhado_outra_unidade;encaminhado_para_unidade;num_ip')
Write-Host ("OK: exportacao CSV de boletins, csv=" + $isCsv + ', delimitador=' + $hasDelimiter + ', colunas=' + $hasExpectedColumns) -ForegroundColor Green

Remove-Item -Path $csvFile -Force -ErrorAction SilentlyContinue

$result = [ordered]@{
    uploadStatus = $upload.StatusCode
    listagemStatus = $listRes.StatusCode
    relatorioStatus = $relRes.StatusCode
    exportStatus = $expRes.StatusCode
    listagemLooksValid = $listLooksValid
    relatorioLooksValid = $relLooksValid
    exportIsCsv = $isCsv
    exportHasDelimiter = $hasDelimiter
    exportHasExpectedColumns = $hasExpectedColumns
    cartorioTestado = $cartorio.label
    periodo = ('{0}/{1}' -f $Year, $Month)
}

Write-Host ''
Write-Host 'Resumo boletins:' -ForegroundColor Cyan
$result | Format-List

if (
    $upload.StatusCode -in @(200, 302) -and
    $listRes.StatusCode -eq 200 -and
    $relRes.StatusCode -eq 200 -and
    $expRes.StatusCode -eq 200
) {
    Write-Host 'VALIDACAO GERAL: APROVADA' -ForegroundColor Green
    exit 0
}

Write-Host 'VALIDACAO GERAL: FALHOU' -ForegroundColor Red
exit 1