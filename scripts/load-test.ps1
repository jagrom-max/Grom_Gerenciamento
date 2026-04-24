param(
    [string] $BaseUrl = 'http://127.0.0.1:8088',
    [string] $Username,
    [string] $Password,
    [int] $DurationSec = 30,
    [int] $LoginConcurrency = 20,
    [int] $AppRps = 50,
    [int] $PdfRps = 10,
    [int] $SessionPoolSize = 8,
    [int] $MaxParallelism = 24,
    [string] $OutputPath,
    [switch] $DryRun,
    [switch] $FailOnThreshold
)

$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName 'System.Net.Http'

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

function Get-MedianValue {
    param([double[]] $Values)

    if (-not $Values -or $Values.Count -eq 0) {
        return 0
    }

    $sorted = $Values | Sort-Object
    $mid = [int] [math]::Floor($sorted.Count / 2)

    if ($sorted.Count % 2 -eq 0) {
        return [math]::Round((($sorted[$mid - 1] + $sorted[$mid]) / 2), 2)
    }

    return [math]::Round($sorted[$mid], 2)
}

function Get-PercentileValue {
    param(
        [double[]] $Values,
        [double] $Percentile
    )

    if (-not $Values -or $Values.Count -eq 0) {
        return 0
    }

    $sorted = $Values | Sort-Object
    $index = [math]::Ceiling(($sorted.Count * $Percentile) / 100) - 1
    $index = [math]::Max(0, [math]::Min($index, $sorted.Count - 1))

    return [math]::Round($sorted[$index], 2)
}

function New-FormUrlEncodedContent {
    param([hashtable] $Data)

    $pairs = New-Object 'System.Collections.Generic.List[System.Collections.Generic.KeyValuePair[string,string]]'

    foreach ($key in $Data.Keys) {
        $pairs.Add([System.Collections.Generic.KeyValuePair[string,string]]::new([string] $key, [string] $Data[$key]))
    }

    return [System.Net.Http.FormUrlEncodedContent]::new($pairs)
}

function Get-CsrfToken {
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

    throw 'Nao foi possivel localizar o token CSRF na tela de login.'
}

function New-AbsoluteUri {
    param(
        [string] $Base,
        [string] $RelativeOrAbsolute
    )

    if ([string]::IsNullOrWhiteSpace($RelativeOrAbsolute)) {
        return $Base
    }

    return [System.Uri]::new([System.Uri]::new($Base), $RelativeOrAbsolute).AbsoluteUri
}

function New-HttpSession {
    param([string] $Base)

    $cookies = [System.Net.CookieContainer]::new()
    $handler = [System.Net.Http.HttpClientHandler]::new()
    $handler.CookieContainer = $cookies
    $handler.AllowAutoRedirect = $false
    $handler.AutomaticDecompression = [System.Net.DecompressionMethods]::GZip -bor [System.Net.DecompressionMethods]::Deflate

    $client = [System.Net.Http.HttpClient]::new($handler)
    $client.Timeout = [TimeSpan]::FromSeconds(90)
    $client.DefaultRequestHeaders.UserAgent.ParseAdd('GROM-LoadTest/1.0')
    $client.DefaultRequestHeaders.Accept.ParseAdd('*/*')
    $client.BaseAddress = [System.Uri]::new($Base)

    return [pscustomobject]@{
        Client = $client
        Handler = $handler
    }
}

function Remove-HttpSession {
    param($Session)

    if ($null -ne $Session.Client) {
        $Session.Client.Dispose()
    }

    if ($null -ne $Session.Handler) {
        $Session.Handler.Dispose()
    }
}

function Invoke-LoginFlow {
    param(
        $Session,
        [string] $Base,
        [string] $Login,
        [string] $Secret
    )

    $loginPageUrl = (New-AbsoluteUri -Base $Base -RelativeOrAbsolute '/login')
    $loginGet = $Session.Client.GetAsync($loginPageUrl).GetAwaiter().GetResult()
    $loginHtml = $loginGet.Content.ReadAsStringAsync().GetAwaiter().GetResult()

    if (-not $loginGet.IsSuccessStatusCode) {
        throw ('Falha ao abrir /login. HTTP {0}.' -f [int] $loginGet.StatusCode)
    }

    $token = Get-CsrfToken -Html $loginHtml
    $content = New-FormUrlEncodedContent -Data @{
        _token = $token
        login = $Login
        password = $Secret
        redirect_to = 'cartorio'
    }

    $post = $Session.Client.PostAsync((New-AbsoluteUri -Base $Base -RelativeOrAbsolute '/login'), $content).GetAwaiter().GetResult()

    if (([int] $post.StatusCode) -lt 300 -or ([int] $post.StatusCode) -ge 400) {
        $body = $post.Content.ReadAsStringAsync().GetAwaiter().GetResult()
        throw ('Falha no POST /login. HTTP {0}. Trecho: {1}' -f [int] $post.StatusCode, ($body.Substring(0, [math]::Min(180, $body.Length))))
    }

    $location = $post.Headers.Location
    $redirectUrl = if ($location) { (New-AbsoluteUri -Base $Base -RelativeOrAbsolute $location.OriginalString) } else { (New-AbsoluteUri -Base $Base -RelativeOrAbsolute '/dashboard') }
    $follow = $Session.Client.GetAsync($redirectUrl).GetAwaiter().GetResult()

    if (-not $follow.IsSuccessStatusCode) {
        throw ('Login completou com redirecionamento, mas o destino retornou HTTP {0}.' -f [int] $follow.StatusCode)
    }

    return $true
}

function Invoke-HttpProbe {
    param(
        $Session,
        [string] $Url,
        [string] $ExpectedContentTypePrefix
    )

    $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()
    $response = $null

    try {
        $response = $Session.Client.GetAsync($Url).GetAwaiter().GetResult()
        $contentType = $null
        if ($response.Content.Headers.ContentType) {
            $contentType = $response.Content.Headers.ContentType.MediaType
        }

        $bytes = $response.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult()
        $stopwatch.Stop()

        $ok = $response.IsSuccessStatusCode
        if ($ExpectedContentTypePrefix -and $contentType) {
            $ok = $ok -and $contentType.StartsWith($ExpectedContentTypePrefix)
        }

        return [pscustomobject]@{
            ok = $ok
            statusCode = [int] $response.StatusCode
            latencyMs = [math]::Round($stopwatch.Elapsed.TotalMilliseconds, 2)
            bytes = $bytes.Length
            contentType = $contentType
            error = $null
        }
    } catch {
        $stopwatch.Stop()

        return [pscustomobject]@{
            ok = $false
            statusCode = 0
            latencyMs = [math]::Round($stopwatch.Elapsed.TotalMilliseconds, 2)
            bytes = 0
            contentType = $null
            error = $_.Exception.Message
        }
    } finally {
        if ($null -ne $response) {
            $response.Dispose()
        }
    }
}

function Get-ScenarioSummary {
    param(
        [string] $Name,
        [object[]] $Results,
        [double] $ElapsedSec,
        [double] $TargetP95Ms = 500
    )

    $latencies = @($Results | ForEach-Object { [double] $_.latencyMs })
    $total = $Results.Count
    $success = @($Results | Where-Object { $_.ok }).Count
    $errors = $total - $success
    $http500 = @($Results | Where-Object { $_.statusCode -ge 500 }).Count
    $avg = if ($total -gt 0) { [math]::Round((($latencies | Measure-Object -Average).Average), 2) } else { 0 }
    $max = if ($total -gt 0) { [math]::Round((($latencies | Measure-Object -Maximum).Maximum), 2) } else { 0 }
    $throughput = if ($ElapsedSec -gt 0) { [math]::Round(($total / $ElapsedSec), 2) } else { 0 }

    return [pscustomobject]@{
        name = $Name
        total = $total
        success = $success
        errors = $errors
        http500 = $http500
        elapsedSec = [math]::Round($ElapsedSec, 2)
        throughputRps = $throughput
        avgMs = $avg
        medianMs = (Get-MedianValue -Values $latencies)
        p95Ms = (Get-PercentileValue -Values $latencies -Percentile 95)
        maxMs = $max
        thresholdOk = ($http500 -eq 0 -and (Get-PercentileValue -Values $latencies -Percentile 95) -le $TargetP95Ms)
    }
}

function Invoke-ParallelBurst {
    param(
        [string] $Name,
        [int] $Count,
        [scriptblock] $Action,
        [int] $Parallelism
    )

    $results = New-Object 'System.Collections.Generic.List[object]'
    $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()

    for ($i = 0; $i -lt $Count; $i++) {
        $requestIndex = $i
        try {
            $results.Add((& $Action $requestIndex))
        } catch {
            $results.Add([pscustomobject]@{
                ok = $false
                statusCode = 0
                latencyMs = 0
                bytes = 0
                contentType = $null
                error = $_.Exception.Message
            })
        }
    }

    $stopwatch.Stop()

    return [pscustomobject]@{
        name = $Name
        elapsedSec = $stopwatch.Elapsed.TotalSeconds
        results = @($results.ToArray())
    }
}

function Invoke-RateScenario {
    param(
        [string] $Name,
        [int] $RequestsPerSecond,
        [int] $Duration,
        [scriptblock] $Action,
        [int] $Parallelism
    )

    $results = New-Object 'System.Collections.Generic.List[object]'
    $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()
    $totalRequests = $RequestsPerSecond * $Duration

    for ($i = 0; $i -lt $totalRequests; $i++) {
        $requestIndex = $i
        $targetMs = [int] [math]::Floor(($requestIndex * 1000.0) / $RequestsPerSecond)

        while ($stopwatch.ElapsedMilliseconds -lt $targetMs) {
            Start-Sleep -Milliseconds 5
        }

        try {
            $results.Add((& $Action $requestIndex))
        } catch {
            $results.Add([pscustomobject]@{
                ok = $false
                statusCode = 0
                latencyMs = 0
                bytes = 0
                contentType = $null
                error = $_.Exception.Message
            })
        }
    }

    $stopwatch.Stop()

    return [pscustomobject]@{
        name = $Name
        elapsedSec = $stopwatch.Elapsed.TotalSeconds
        results = @($results.ToArray())
    }
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Resolve-Path (Join-Path $scriptRoot '..')
$runtimePath = Join-Path $projectRoot 'runtime'
$envPath = Join-Path $runtimePath '.env'

$resolvedBaseUrl = $BaseUrl.TrimEnd('/')

if (-not $Username) {
    $Username = Get-EnvValue -Path $envPath -Key 'GROM_BOOTSTRAP_ADMIN_USERNAME'
}

if (-not $Password) {
    $Password = Get-EnvValue -Path $envPath -Key 'GROM_BOOTSTRAP_ADMIN_PASSWORD'
}

if (-not $Username) {
    $Username = 'admin'
}

if (-not $Password) {
    $Password = 'GromPilot#2026'
}

if (-not $OutputPath) {
    $timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $OutputPath = Join-Path $scriptRoot ('load-test-report-{0}.json' -f $timestamp)
}

$plan = [pscustomobject]@{
    baseUrl = $resolvedBaseUrl
    username = $Username
    durationSec = $DurationSec
    loginConcurrency = $LoginConcurrency
    appRps = $AppRps
    pdfRps = $PdfRps
    sessionPoolSize = $SessionPoolSize
    maxParallelism = $MaxParallelism
    outputPath = $OutputPath
    scenarios = @(
        '/login (burst simultaneo)',
        '/dashboard',
        '/funcionarios',
        '/relatorios/produtividade/a4/pdf?year=2026&month=3'
    )
}

Write-Host ''
Write-Host 'Plano de carga' -ForegroundColor Cyan
$plan | Format-List

if ($DryRun) {
    return
}

$probeSession = New-HttpSession -Base $resolvedBaseUrl

try {
    $probe = $probeSession.Client.GetAsync((New-AbsoluteUri -Base $resolvedBaseUrl -RelativeOrAbsolute '/login')).GetAwaiter().GetResult()
    if (-not $probe.IsSuccessStatusCode) {
        throw ('A URL base nao respondeu /login com HTTP 200. Recebido: {0}' -f [int] $probe.StatusCode)
    }
} finally {
    Remove-HttpSession -Session $probeSession
}

$sessionPool = @()

try {
    Write-Host ''
    Write-Host 'Preparando sessoes autenticadas...' -ForegroundColor Cyan

    for ($i = 0; $i -lt $SessionPoolSize; $i++) {
        $session = New-HttpSession -Base $resolvedBaseUrl
        Invoke-LoginFlow -Session $session -Base $resolvedBaseUrl -Login $Username -Secret $Password | Out-Null
        $sessionPool += $session
    }

    Write-Host ('Sessoes autenticadas: {0}' -f $sessionPool.Count) -ForegroundColor Green

    $sessionCursor = 0
    $loginScenario = Invoke-ParallelBurst -Name 'login_burst' -Count $LoginConcurrency -Parallelism ([math]::Min($LoginConcurrency, $MaxParallelism)) -Action {
        param($requestIndex)
        $session = New-HttpSession -Base $resolvedBaseUrl

        try {
            $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()
            Invoke-LoginFlow -Session $session -Base $resolvedBaseUrl -Login $Username -Secret $Password | Out-Null
            $stopwatch.Stop()

            return [pscustomobject]@{
                ok = $true
                statusCode = 200
                latencyMs = [math]::Round($stopwatch.Elapsed.TotalMilliseconds, 2)
                bytes = 0
                contentType = 'text/html'
                error = $null
            }
        } catch {
            return [pscustomobject]@{
                ok = $false
                statusCode = 0
                latencyMs = 0
                bytes = 0
                contentType = $null
                error = $_.Exception.Message
            }
        } finally {
            Remove-HttpSession -Session $session
        }
    }

    $dashboardScenario = Invoke-RateScenario -Name 'dashboard' -RequestsPerSecond $AppRps -Duration $DurationSec -Parallelism $MaxParallelism -Action {
        param($requestIndex)
        $index = ([System.Threading.Interlocked]::Increment([ref] $sessionCursor) - 1) % $sessionPool.Count
        Invoke-HttpProbe -Session $sessionPool[$index] -Url (New-AbsoluteUri -Base $resolvedBaseUrl -RelativeOrAbsolute '/dashboard') -ExpectedContentTypePrefix 'text/html'
    }

    $funcionariosScenario = Invoke-RateScenario -Name 'funcionarios' -RequestsPerSecond $AppRps -Duration $DurationSec -Parallelism $MaxParallelism -Action {
        param($requestIndex)
        $index = ([System.Threading.Interlocked]::Increment([ref] $sessionCursor) - 1) % $sessionPool.Count
        Invoke-HttpProbe -Session $sessionPool[$index] -Url (New-AbsoluteUri -Base $resolvedBaseUrl -RelativeOrAbsolute '/funcionarios') -ExpectedContentTypePrefix 'text/html'
    }

    $pdfParallelism = [math]::Min($MaxParallelism, [math]::Max(4, $PdfRps))
    $pdfScenario = Invoke-RateScenario -Name 'relatorio_pdf' -RequestsPerSecond $PdfRps -Duration $DurationSec -Parallelism $pdfParallelism -Action {
        param($requestIndex)
        $index = ([System.Threading.Interlocked]::Increment([ref] $sessionCursor) - 1) % $sessionPool.Count
        Invoke-HttpProbe -Session $sessionPool[$index] -Url (New-AbsoluteUri -Base $resolvedBaseUrl -RelativeOrAbsolute '/relatorios/produtividade/a4/pdf?year=2026&month=3') -ExpectedContentTypePrefix 'application/pdf'
    }

    $scenarioSummaries = @(
        (Get-ScenarioSummary -Name 'login_burst' -Results $loginScenario.results -ElapsedSec $loginScenario.elapsedSec),
        (Get-ScenarioSummary -Name 'dashboard' -Results $dashboardScenario.results -ElapsedSec $dashboardScenario.elapsedSec),
        (Get-ScenarioSummary -Name 'funcionarios' -Results $funcionariosScenario.results -ElapsedSec $funcionariosScenario.elapsedSec),
        (Get-ScenarioSummary -Name 'relatorio_pdf' -Results $pdfScenario.results -ElapsedSec $pdfScenario.elapsedSec)
    )

    Write-Host ''
    Write-Host 'Resumo das cargas' -ForegroundColor Cyan
    $scenarioSummaries | Format-Table name, total, success, errors, http500, throughputRps, avgMs, p95Ms, maxMs, thresholdOk -AutoSize

    $report = [pscustomobject]@{
        generatedAt = (Get-Date).ToString('s')
        settings = $plan
        thresholds = [pscustomobject]@{
            p95Ms = 500
            http500 = 0
        }
        scenarios = $scenarioSummaries
    }

    $report | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $OutputPath -Encoding UTF8
    Write-Host ''
    Write-Host ('Relatorio salvo em: {0}' -f $OutputPath) -ForegroundColor Green

    if ($FailOnThreshold -and (@($scenarioSummaries | Where-Object { -not $_.thresholdOk }).Count -gt 0)) {
        exit 1
    }
} finally {
    foreach ($session in $sessionPool) {
        Remove-HttpSession -Session $session
    }
}