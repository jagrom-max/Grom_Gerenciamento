#!/usr/bin/env powershell
<#
.SYNOPSIS
Teste end-to-end de produtividade: upload, consolidacao, estatisticas e relatorios.
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

    foreach ($p in $patterns) {
        $m = [regex]::Match($Html, $p, 'IgnoreCase')
        if ($m.Success) { return $m.Groups[1].Value }
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

    foreach ($m in $optMatches) {
        $value = $m.Groups[1].Value.Trim()
        $label = [System.Net.WebUtility]::HtmlDecode($m.Groups[2].Value)
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

Write-Host 'Teste de Consolidacao de Produtividade' -ForegroundColor Cyan
Write-Host "BaseUrl=$BaseUrl Year=$Year Month=$Month" -ForegroundColor Cyan

$global:WebSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$adminPassword = Get-EnvValue -Key 'ADMIN_PASSWORD' -Default '03031981Gr**'

# 1) Login
$loginPage = Invoke-Request -Method 'GET' -Uri '/login'
$csrf = Get-HtmlCsrfToken -Html $loginPage.Content
$loginBody = New-FormBody -Data @{
    login = 'gestor.demo'
    password = $adminPassword
    _token = $csrf
}
$loginRes = Invoke-Request -Method 'POST' -Uri '/login' -Body $loginBody -ContentType 'application/x-www-form-urlencoded'
if ($loginRes.StatusCode -ne 302 -and $loginRes.StatusCode -ne 200) {
    throw "Falha no login. Status=$($loginRes.StatusCode)"
}
if ($loginRes.Content -match 'Credenciais invalidas|Muitas tentativas|name="login"') {
    throw 'Falha no login: credencial invalida ou tentativa bloqueada.'
}
Write-Host 'OK: login' -ForegroundColor Green

# 2) Dashboard flagrantes
$flagrantes = Invoke-Request -Method 'GET' -Uri '/produtividade/flagrantes'
if ($flagrantes.StatusCode -ne 200) {
    throw "Falha ao abrir /produtividade/flagrantes. Status=$($flagrantes.StatusCode)"
}
$cartorios = Get-CartorioOptions -Html $flagrantes.Content
if ($cartorios.Count -eq 0) {
    throw 'Nenhum cartorio disponivel para teste.'
}
$cartorio = $cartorios[0]
Write-Host ("OK: dashboard flagrantes, cartorio=" + $cartorio.label) -ForegroundColor Green

# 3) Criar CSV de teste
$stamp = Get-Date -Format 'yyyyMMddHHmmss'
$csvFile = Join-Path $env:TEMP ("flagrantes-teste-$stamp.csv")
$csv = @"
SOURCEPROCESSKEY;SPJREF;NATUREZAS;DATAFATO;ANO;MES;NUMIP;NUMCNJ;FLAGRANTE;LAVRADOUNIDADE;CARTORIOLABEL
SK-TST-$stamp-001;123456789;DUPLA LABORAL;2026-04-15;2026;4;IP-2026-001;CNJ-2026-001;SIM;DDM;Cartorio Teste
SK-TST-$stamp-002;123456790;RESCISAO;2026-04-18;2026;4;IP-2026-002;;SIM;OUTRAS_UNIDADES;Cartorio Teste
SK-TST-$stamp-003;123456791;HOMOLOGACAO;2026-04-20;2026;4;;CNJ-2026-002;SIM;DDM;Cartorio Teste
SK-TST-$stamp-004;123456792;DUPLA LABORAL;2026-04-22;2026;4;;;SIM;OUTRAS_UNIDADES;Cartorio Teste
"@
Set-Content -Path $csvFile -Value $csv -Encoding UTF8

# 4) Upload importar
$flagrantesPage = Invoke-Request -Method 'GET' -Uri '/produtividade/flagrantes'
$uploadToken = Get-HtmlCsrfToken -Html $flagrantesPage.Content

$boundary = '------------------------' + [guid]::NewGuid().ToString('N')
$nl = "`r`n"
$fileBytes = [System.IO.File]::ReadAllBytes($csvFile)
$fileText = [System.Text.Encoding]::UTF8.GetString($fileBytes)

$multipart = "--$boundary$nl"
$multipart += "Content-Disposition: form-data; name=`"_token`"$nl$nl$uploadToken$nl"
$multipart += "--$boundary$nl"
$multipart += "Content-Disposition: form-data; name=`"cartorio_id`"$nl$nl$($cartorio.value)$nl"
$multipart += "--$boundary$nl"
$multipart += "Content-Disposition: form-data; name=`"source_file`"; filename=`"$(Split-Path $csvFile -Leaf)`"$nl"
$multipart += "Content-Type: text/csv$nl$nl"
$multipart += $fileText + $nl
$multipart += "--$boundary--$nl"

$upload = Invoke-Request -Method 'POST' -Uri '/produtividade/flagrantes/importar' -Body $multipart -ContentType "multipart/form-data; boundary=$boundary"
if ($upload.StatusCode -ne 302 -and $upload.StatusCode -ne 200) {
    throw "Falha no upload/importar. Status=$($upload.StatusCode)"
}
Write-Host 'OK: upload/importar' -ForegroundColor Green

# 5) Stats por periodo
$statsUri = ('/produtividade/estatisticas?year={0}&month={1}&cartorio_id={2}' -f $Year, $Month, $cartorio.value)
$stats = Invoke-Request -Method 'GET' -Uri $statsUri
if ($stats.StatusCode -ne 200) {
    throw "Falha em estatisticas. Status=$($stats.StatusCode)"
}
$statsHasFlagrantes = ($stats.Content -match 'flagrantes')
Write-Host ("OK: estatisticas periodicas, encontrou marcador=" + $statsHasFlagrantes) -ForegroundColor Green

# 6) Relatorio de flagrantes por periodo
$relUri = ('/produtividade/flagrantes/relatorio?year={0}&month={1}&cartorio_id={2}' -f $Year, $Month, $cartorio.value)
$rel = Invoke-Request -Method 'GET' -Uri $relUri
if ($rel.StatusCode -ne 200) {
    throw "Falha no relatorio de flagrantes. Status=$($rel.StatusCode)"
}
# Marcadores semanticos presentes na view relatorio.blade.php
$relHasTable = ($rel.Content -match 'flagrantes consolidados|Lavrados DDM|Outras unidades|Relat.rio de Flagrantes')
Write-Host ("OK: relatorio de flagrantes, marcador semantico=" + $relHasTable) -ForegroundColor Green

# 7) Exportacao CSV
$expUri = ('/produtividade/estatisticas/exportar?year={0}&month={1}&cartorio_id={2}' -f $Year, $Month, $cartorio.value)
$exp = Invoke-Request -Method 'GET' -Uri $expUri
if ($exp.StatusCode -ne 200) {
    throw "Falha na exportacao CSV. Status=$($exp.StatusCode)"
}
$isCsv = ($exp.Headers['Content-Type'] -match 'text/csv')
$hasCsvSep = ($exp.Content -match ';')
Write-Host ("OK: exportacao CSV, contentTypeCsv=" + $isCsv + ", delimitador=" + $hasCsvSep) -ForegroundColor Green

# 8) Analise: MPU por periodo
$analiseUri = ('/analise/estatisticas?year={0}&month={1}' -f $Year, $Month)
$analise = Invoke-Request -Method 'GET' -Uri $analiseUri
if ($analise.StatusCode -ne 200) {
    throw "Falha em /analise/estatisticas. Status=$($analise.StatusCode)"
}
# View ou JSON deve conter indicadores de MPU e IP
$analiseHasMpu = ($analise.Content -match 'mpu|MPU|com_mpu|totalComMpu')
$analiseHasIp  = ($analise.Content -match 'com_ip|totalComIp|num_ip|Com IP')
Write-Host ("OK: analise estatisticas MPU, mpu=" + $analiseHasMpu + " ip=" + $analiseHasIp) -ForegroundColor Green

# 9) Pesquisa nominal (alvo)
$searchUri = ('/analise/bos/pesquisar?q={0}' -f [uri]::EscapeDataString('joao'))
$search = Invoke-Request -Method 'GET' -Uri $searchUri
if ($search.StatusCode -ne 200) {
    throw "Falha na pesquisa nominal (alvo). Status=$($search.StatusCode)"
}
$searchWorks = ($search.Content -match 'Pesquisa|BO|SPJ|Resultado|Pesquisar')
# Verificar totais de MPU e IP na resposta de pesquisa de alvo
$searchHasMpuTotal = ($search.Content -match 'Com MPU|totalComMpu|com_mpu|MPU')
Write-Host ("OK: pesquisa de alvo, resposta valida=" + $searchWorks + ', mpu_presente=' + $searchHasMpuTotal) -ForegroundColor Green

Remove-Item -Path $csvFile -Force -ErrorAction SilentlyContinue

$result = [ordered]@{
    uploadImportStatus             = $upload.StatusCode
    statsStatus                    = $stats.StatusCode
    relatorioStatus                = $rel.StatusCode
    exportStatus                   = $exp.StatusCode
    analiseEstatisticasStatus      = $analise.StatusCode
    pesquisaAlvoStatus             = $search.StatusCode
    statsHasFlagrantes             = $statsHasFlagrantes
    relatorioTemMarcadorSemantico  = $relHasTable
    exportIsCsv                    = $isCsv
    exportHasDelimiter             = $hasCsvSep
    analiseTemMpu                  = $analiseHasMpu
    analiseTemIp                   = $analiseHasIp
    pesquisaAlvoResponseLooksValid = $searchWorks
    pesquisaAlvoTemMpu             = $searchHasMpuTotal
    cartorioTestado                = $cartorio.label
    periodo                        = ('{0}/{1}' -f $Year, $Month)
}

Write-Host ''
Write-Host 'Resumo consolidacao:' -ForegroundColor Cyan
$result | Format-List

if (
    $upload.StatusCode -in @(200,302) -and
    $stats.StatusCode -eq 200 -and
    $rel.StatusCode -eq 200 -and
    $exp.StatusCode -eq 200 -and
    $analise.StatusCode -eq 200 -and
    $search.StatusCode -eq 200
) {
    Write-Host 'VALIDACAO GERAL: APROVADA' -ForegroundColor Green
    exit 0
}

Write-Host 'VALIDACAO GERAL: FALHOU' -ForegroundColor Red
exit 1
