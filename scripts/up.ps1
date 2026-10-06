[CmdletBinding()]
param(
    [switch]$Down,
    [switch]$Fresh,
    [switch]$Logs
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot\..
$envFile = Join-Path $PSScriptRoot '..\.env'

Get-Content $envFile | ForEach-Object {
    if ($_ -match '^\s*([^#][^=]*)=(.*)$') {
        Set-Item -Path "Env:$($matches[1].Trim())" -Value $matches[2].Trim()
    }
}

if ($Down) {
    docker compose down
    exit 0
}

if ($Fresh) {
    Write-Host 'Removing containers and database volume...' -ForegroundColor Yellow
    docker compose down -v
}

docker compose up -d
docker compose ps

if ($Logs) {
    docker compose logs -f
}