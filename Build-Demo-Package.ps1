[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$Destination,
    [switch]$ExcludeVendor
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path $PSScriptRoot).Path
$destinationPath = [IO.Path]::GetFullPath($Destination)
if ($destinationPath.TrimEnd('\') -eq $root.TrimEnd('\') -or $destinationPath.StartsWith($root.TrimEnd('\') + '\', [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Destination must be outside the source project.'
}
New-Item -ItemType Directory -Force -Path $destinationPath | Out-Null

$excludedDirectories = @('.git','node_modules','demo-runtime','.codex','.idea','.vscode','anpr-service\.venv','storage\logs','storage\framework','tests\e2e\output')
if ($ExcludeVendor) { $excludedDirectories += 'vendor' }
$arguments = @($root, $destinationPath, '/E', '/R:1', '/W:1', '/NFL', '/NDL', '/NJH', '/NJS', '/NP', '/XD') + $excludedDirectories + @('/XF','.env','.env.*','*.log','*.bak','*.lock','phpunit.xml','.phpunit.result.cache')
& robocopy.exe @arguments | Out-Null
if ($LASTEXITCODE -ge 8) { throw "Package copy failed with robocopy exit code $LASTEXITCODE." }

$forbidden = @(Get-ChildItem -LiteralPath $destinationPath -Force -File -Filter '.env*' -ErrorAction SilentlyContinue | Where-Object { $_.Name -ne '.env.example' })
if ($forbidden.Count -gt 0) { throw 'Package validation found a forbidden environment file.' }
foreach ($required in @('Setup-Demo.ps1','Start-Demo.ps1','Stop-Demo.ps1','Verify-Demo.ps1','public\build\manifest.json','anpr-service\models\manifest.json')) {
    if (-not (Test-Path -LiteralPath (Join-Path $destinationPath $required))) { throw "Package is missing required file: $required" }
}
Write-Host "Portable demo files prepared at $destinationPath" -ForegroundColor Green
Write-Host 'No database operation was performed. Validate this package on the destination PC before presenting it.'
