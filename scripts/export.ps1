<#
.SYNOPSIS
	Build the static site into dist/.

.DESCRIPTION
	Empties dist/ from the host, then runs the exporter inside the container.

	dist/ is a bind mount. On Windows a recursive delete performed inside the
	container only sees files written during the current run, so an earlier
	export can never be fully removed from there. Deleting from the host sees
	the whole tree, so the wipe lives here.

.PARAMETER SkipQa
	Do not run the regression suite afterwards.

.EXAMPLE
	.\scripts\export.ps1
	.\scripts\export.ps1 -SkipQa
#>
[CmdletBinding()]
param(
    [switch]$SkipQa
)

# Compose writes progress to stderr, which PowerShell turns into an error record
# when the preference is Stop. Exit codes are checked explicitly instead.
$ErrorActionPreference = 'Continue'
Set-Location (Split-Path -Parent $PSScriptRoot)

$dist = Join-Path (Get-Location) 'dist'

if (Test-Path -LiteralPath $dist) {
    Write-Host 'clearing dist/ ...' -ForegroundColor DarkGray
    Get-ChildItem -LiteralPath $dist -Force | Remove-Item -Recurse -Force -ErrorAction Stop
} else {
    New-Item -ItemType Directory -Path $dist -ErrorAction Stop | Out-Null
}

Write-Host 'exporting ...' -ForegroundColor Cyan
docker compose run --rm wpcli wp eval-file /seed/export.php --path=/var/www/html --allow-root

if ($LASTEXITCODE -ne 0) {
    throw "export failed with exit code $LASTEXITCODE"
}

$files = Get-ChildItem -LiteralPath $dist -Recurse -File
$bytes = ($files | Measure-Object -Property Length -Sum).Sum

Write-Host ('export complete: {0} files, {1:N2} MB' -f $files.Count, ($bytes / 1MB)) -ForegroundColor Green

if (-not $SkipQa) {
    Write-Host ''
    Write-Host 'running theme QA ...' -ForegroundColor Cyan
    docker compose run --rm wpcli wp eval-file /seed/qa.php --path=/var/www/html --allow-root

    if ($LASTEXITCODE -ne 0) {
        throw 'theme QA failed'
    }

    Write-Host ''
    Write-Host 'running dist QA ...' -ForegroundColor Cyan
    docker compose run --rm wpcli wp eval-file /seed/qa-dist.php --path=/var/www/html --allow-root
    exit $LASTEXITCODE
}