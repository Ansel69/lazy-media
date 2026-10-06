<#
.SYNOPSIS
	Export, QA, commit, and push in one command.

.DESCRIPTION
	Runs scripts/export.ps1 (which wipes dist/, exports, and runs both QA
	suites), then commits every change and pushes to origin so Vercel picks
	the new build up. When nothing changed after the export, it stops without
	creating an empty commit.

.PARAMETER Message
	Commit message, e.g. "Tambah artikel Review Genshin Impact".

.PARAMETER SkipPush
	Commit locally without pushing to origin.

.EXAMPLE
	.\scripts\publish.ps1 -Message "Tambah artikel ZeldaTotK"
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Message,

    [switch]$SkipPush
)

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

& (Join-Path $PSScriptRoot 'export.ps1')

if ($LASTEXITCODE -ne 0) {
    throw "publish aborted: export or QA failed (exit $LASTEXITCODE)"
}

git add -A

$pending = @(git status --porcelain)

if ($pending.Count -eq 0) {
    Write-Host 'nothing to publish: dist/ is unchanged' -ForegroundColor Yellow
    exit 0
}

git commit -m $Message
if ($LASTEXITCODE -ne 0) {
    throw 'publish aborted: git commit failed'
}

if ($SkipPush) {
    Write-Host 'committed locally (SkipPush set)' -ForegroundColor Yellow
    exit 0
}

git push
if ($LASTEXITCODE -ne 0) {
    throw 'publish aborted: git push failed'
}

Write-Host "published: $Message" -ForegroundColor Green
