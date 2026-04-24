param(
	[switch] $PersistPhpPath,
	[switch] $InstallDocker,
	[switch] $SkipDocker,
	[switch] $UseSudo
)

$ErrorActionPreference = 'Stop'

function Write-Section {
	param([string] $Text)

	Write-Host ''
	Write-Host ('=== {0} ===' -f $Text) -ForegroundColor Cyan
}

function Add-PathIfMissing {
	param(
		[string] $CurrentPath,
		[string] $Value
	)

	$parts = @($CurrentPath -split ';' | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
	if ($parts -contains $Value) {
		return $CurrentPath
	}

	if ([string]::IsNullOrWhiteSpace($CurrentPath)) {
		return $Value
	}

	return ($CurrentPath.TrimEnd(';') + ';' + $Value)
}

function Ensure-CurrentSessionPhpPath {
	param([string] $PhpBinDir)

	if (-not (Test-Path -LiteralPath $PhpBinDir)) {
		throw ('Diretorio PHP local nao encontrado: {0}' -f $PhpBinDir)
	}

	$env:Path = Add-PathIfMissing -CurrentPath $env:Path -Value $PhpBinDir
}

function Ensure-UserPhpPath {
	param([string] $PhpBinDir)

	$userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
	$newUserPath = Add-PathIfMissing -CurrentPath $userPath -Value $PhpBinDir

	if ($newUserPath -ne $userPath) {
		[Environment]::SetEnvironmentVariable('Path', $newUserPath, 'User')
		Write-Host 'PATH de usuario atualizado para incluir PHP local.' -ForegroundColor Green
	} else {
		Write-Host 'PATH de usuario ja contem PHP local.' -ForegroundColor Yellow
	}
}

function Install-DockerDesktop {
	param([switch] $UseSudo)

	if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
		throw 'winget nao encontrado. Nao foi possivel automatizar instalacao do Docker Desktop.'
	}

	if (Get-Command docker -ErrorAction SilentlyContinue) {
		Write-Host 'Docker ja esta disponivel no PATH.' -ForegroundColor Green
		return
	}

	$installArgs = @(
		'install',
		'--id', 'Docker.DockerDesktop',
		'--exact',
		'--accept-package-agreements',
		'--accept-source-agreements',
		'--silent'
	)

	if ($UseSudo -and (Get-Command sudo -ErrorAction SilentlyContinue)) {
		& sudo winget @installArgs
	} else {
		& winget @installArgs
	}

	if ($LASTEXITCODE -ne 0) {
		throw 'Falha na instalacao do Docker Desktop via winget.'
	}

	Write-Host 'Instalacao do Docker Desktop concluida. Reinicie a sessao do terminal.' -ForegroundColor Green
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Resolve-Path (Join-Path $scriptRoot '..')
$phpBinDir = Join-Path $projectRoot '_toolchain\bin'
$phpCmd = Join-Path $phpBinDir 'php.cmd'

Write-Section 'PHP local'

Ensure-CurrentSessionPhpPath -PhpBinDir $phpBinDir

if ($PersistPhpPath) {
	Ensure-UserPhpPath -PhpBinDir $phpBinDir
}

if (-not (Get-Command php -ErrorAction SilentlyContinue) -and (Test-Path -LiteralPath $phpCmd)) {
	Set-Alias -Name php -Value $phpCmd -Scope Global
}

if (Test-Path -LiteralPath $phpCmd) {
	& $phpCmd -v
}

Write-Section 'Docker'

if ($SkipDocker) {
	Write-Host 'Etapa Docker ignorada por parametro.' -ForegroundColor Yellow
} else {
	try {
		if ($InstallDocker) {
			Install-DockerDesktop -UseSudo:$UseSudo
		}

		if (Get-Command docker -ErrorAction SilentlyContinue) {
			& docker version
			& docker compose version
			Write-Host 'Docker/Compose disponiveis.' -ForegroundColor Green
		} else {
			Write-Warning 'Docker ainda indisponivel. Execute novamente com -InstallDocker ou instale manualmente o Docker Desktop.'
		}
	} catch {
		Write-Warning $_.Exception.Message
	}
}

Write-Section 'Proximo passo local'
Write-Host 'Subir piloto sem Docker:' -ForegroundColor Cyan
Write-Host '  .\executar_piloto_local.cmd -SmokeTest'
Write-Host 'Subir stack com Docker (quando disponivel):' -ForegroundColor Cyan
Write-Host '  powershell -ExecutionPolicy Bypass -File .\scripts\Start-GromWebDocker.ps1 -Build -SmokeTest'
