param(
    [string] $BaseUrl = 'http://127.0.0.1:8080',
    [string] $Username = 'gestor.demo',
    [string] $Password,
    [int] $Year = (Get-Date).Year,
    [int] $Month = (Get-Date).Month
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

function New-AbsoluteUri {
    param(
        [string] $Base,
        [string] $RelativeOrAbsolute
    )

    return [System.Uri]::new([System.Uri]::new($Base), $RelativeOrAbsolute).AbsoluteUri
}

function Get-CsrfToken {
    param([string] $Html)

    $match = [regex]::Match($Html, 'name="_token"\s+value="([^"]+)"')
    if ($match.Success) {
        return $match.Groups[1].Value
    }

    $match = [regex]::Match($Html, 'value="([^"]+)"\s+name="_token"')
    if ($match.Success) {
        return $match.Groups[1].Value
    }

    throw 'Nao foi possivel localizar o token CSRF na pagina de login.'
}

function New-FormUrlEncodedContent {
    param([hashtable] $Data)

    $pairs = New-Object 'System.Collections.Generic.List[System.Collections.Generic.KeyValuePair[string,string]]'
    foreach ($key in $Data.Keys) {
        $pairs.Add([System.Collections.Generic.KeyValuePair[string,string]]::new([string] $key, [string] $Data[$key]))
    }

    return [System.Net.Http.FormUrlEncodedContent]::new($pairs)
}

function New-HttpSession {
    param([string] $Base)

    $handler = [System.Net.Http.HttpClientHandler]::new()
    $handler.CookieContainer = [System.Net.CookieContainer]::new()
    $handler.AllowAutoRedirect = $false
    $handler.AutomaticDecompression = [System.Net.DecompressionMethods]::GZip -bor [System.Net.DecompressionMethods]::Deflate

    $client = [System.Net.Http.HttpClient]::new($handler)
    $client.BaseAddress = [System.Uri]::new($Base)
    $client.Timeout = [TimeSpan]::FromSeconds(60)
    $client.DefaultRequestHeaders.UserAgent.ParseAdd('GROM-SmokeTest/1.0')

    return [pscustomobject]@{
        Client = $client
        Handler = $handler
    }
}

function Remove-HttpSession {
    param($Session)

    if ($Session.Client) {
        $Session.Client.Dispose()
    }

    if ($Session.Handler) {
        $Session.Handler.Dispose()
    }
}

function Invoke-GetCheck {
    param(
        $Session,
        [string] $Name,
        [string] $Url,
        [string] $ExpectedContentTypePrefix,
        [string] $BodyMarker
    )

    $response = $null
    try {
        $response = $Session.Client.GetAsync($Url).GetAwaiter().GetResult()
        $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
        $contentType = if ($response.Content.Headers.ContentType) { $response.Content.Headers.ContentType.MediaType } else { $null }

        $ok = $response.IsSuccessStatusCode
        if ($ExpectedContentTypePrefix) {
            $ok = $ok -and $contentType -and $contentType.StartsWith($ExpectedContentTypePrefix)
        }
        if ($BodyMarker) {
            $ok = $ok -and ($body -match $BodyMarker)
        }

        return [pscustomobject]@{
            name = $Name
            statusCode = [int] $response.StatusCode
            contentType = $contentType
            ok = $ok
            error = $null
        }
    } catch {
        return [pscustomobject]@{
            name = $Name
            statusCode = 0
            contentType = $null
            ok = $false
            error = $_.Exception.Message
        }
    } finally {
        if ($response) {
            $response.Dispose()
        }
    }
}

function Invoke-LoginCheck {
    param(
        $Session,
        [string] $Base,
        [string] $Login,
        [string] $Secret
    )

    $loginUrl = New-AbsoluteUri -Base $Base -RelativeOrAbsolute '/login'
    $loginGet = $Session.Client.GetAsync($loginUrl).GetAwaiter().GetResult()
    $loginHtml = $loginGet.Content.ReadAsStringAsync().GetAwaiter().GetResult()

    if (-not $loginGet.IsSuccessStatusCode -or $loginHtml -notmatch 'Acesso\s+Grom\.Seg' -or $loginHtml -notmatch 'name="login"') {
        throw 'A pagina de login nao retornou os marcadores esperados.'
    }

    $token = Get-CsrfToken -Html $loginHtml
    $content = New-FormUrlEncodedContent -Data @{
        _token = $token
        login = $Login
        password = $Secret
        redirect_to = 'dashboard'
    }

    $post = $Session.Client.PostAsync($loginUrl, $content).GetAwaiter().GetResult()
    $postBody = $post.Content.ReadAsStringAsync().GetAwaiter().GetResult()

    if (([int] $post.StatusCode) -lt 300 -or ([int] $post.StatusCode) -ge 400) {
        throw ('Falha no POST /login. HTTP {0}. Trecho: {1}' -f [int] $post.StatusCode, ($postBody.Substring(0, [Math]::Min(200, $postBody.Length))))
    }

    $location = if ($post.Headers.Location) { $post.Headers.Location.OriginalString } else { '/dashboard' }
    $followUrl = New-AbsoluteUri -Base $Base -RelativeOrAbsolute $location
    $follow = $Session.Client.GetAsync($followUrl).GetAwaiter().GetResult()
    $followBody = $follow.Content.ReadAsStringAsync().GetAwaiter().GetResult()

    if (-not $follow.IsSuccessStatusCode) {
        throw ('Destino pos-login retornou HTTP {0}.' -f [int] $follow.StatusCode)
    }

    if ($follow.RequestMessage.RequestUri.AbsolutePath -eq '/password/change' -or $followBody -match 'primeiro acesso') {
        throw 'O usuario autenticado caiu em troca obrigatoria de senha; use uma credencial de demo sem troca pendente.'
    }

    return [pscustomobject]@{
        name = 'login'
        statusCode = [int] $follow.StatusCode
        contentType = if ($follow.Content.Headers.ContentType) { $follow.Content.Headers.ContentType.MediaType } else { $null }
        ok = $true
        error = $null
    }
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

$resolvedBaseUrl = $BaseUrl.TrimEnd('/') + '/'
$pdfPath = '/relatorios/produtividade/a4/pdf?year={0}&month={1}' -f $Year, $Month
$htmlPath = '/relatorios/produtividade/a4?year={0}&month={1}' -f $Year, $Month

$session = New-HttpSession -Base $resolvedBaseUrl

try {
    $results = @()
    $results += Invoke-GetCheck -Session $session -Name 'login_page' -Url (New-AbsoluteUri -Base $resolvedBaseUrl -RelativeOrAbsolute '/login') -ExpectedContentTypePrefix 'text/html' -BodyMarker 'Acesso\s+Grom\.Seg'
    $results += Invoke-GetCheck -Session $session -Name 'acesso_teste' -Url (New-AbsoluteUri -Base $resolvedBaseUrl -RelativeOrAbsolute '/acesso-teste') -ExpectedContentTypePrefix 'text/html' -BodyMarker 'gestor\.demo|operador\.demo|admin'
    $results += Invoke-LoginCheck -Session $session -Base $resolvedBaseUrl -Login $Username -Secret $Password
    $results += Invoke-GetCheck -Session $session -Name 'dashboard' -Url (New-AbsoluteUri -Base $resolvedBaseUrl -RelativeOrAbsolute '/dashboard') -ExpectedContentTypePrefix 'text/html' -BodyMarker 'Dashboard|Painel'
    $results += Invoke-GetCheck -Session $session -Name 'funcionarios' -Url (New-AbsoluteUri -Base $resolvedBaseUrl -RelativeOrAbsolute '/funcionarios') -ExpectedContentTypePrefix 'text/html' -BodyMarker 'Funcion|RH|Servidor'
    $results += Invoke-GetCheck -Session $session -Name 'escalas' -Url (New-AbsoluteUri -Base $resolvedBaseUrl -RelativeOrAbsolute '/escalas') -ExpectedContentTypePrefix 'text/html' -BodyMarker 'Escala|Plant'
    $results += Invoke-GetCheck -Session $session -Name 'relatorio_a4_html' -Url (New-AbsoluteUri -Base $resolvedBaseUrl -RelativeOrAbsolute $htmlPath) -ExpectedContentTypePrefix 'text/html' -BodyMarker 'Produtividade|Relat'
    $results += Invoke-GetCheck -Session $session -Name 'relatorio_a4_pdf' -Url (New-AbsoluteUri -Base $resolvedBaseUrl -RelativeOrAbsolute $pdfPath) -ExpectedContentTypePrefix 'application/pdf' -BodyMarker $null

    $results | Format-Table name, statusCode, contentType, ok, error -AutoSize

    if (@($results | Where-Object { -not $_.ok }).Count -gt 0) {
        throw 'Uma ou mais verificacoes de smoke test falharam.'
    }

    Write-Host ''
    Write-Host 'Smoke test HTTP completo: OK' -ForegroundColor Green
} finally {
    Remove-HttpSession -Session $session
}