<#
.SYNOPSIS
	Wrapper untuk WP-CLI di dalam container.

.EXAMPLE
	.\scripts\wp.ps1 option get siteurl
	.\scripts\wp.ps1 theme activate lazymedia
#>
[CmdletBinding()]
param(
    [Parameter(Position = 0, ValueFromRemainingArguments = $true)]
    [string[]]$WpArgs
)

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

if (-not $WpArgs -or $WpArgs.Count -eq 0) {
    throw 'Usage: .\scripts\wp.ps1 <wp-cli command> [args]'
}

docker compose run --rm wpcli wp @WpArgs --path=/var/www/html --allow-root
exit $LASTEXITCODE