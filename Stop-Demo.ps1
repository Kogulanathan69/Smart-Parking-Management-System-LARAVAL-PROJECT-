[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'scripts\demo\Demo-Common.ps1')
$root = Get-DemoRoot
$statePath = Join-Path (Get-DemoRuntimePath $root) 'processes.json'

try {
    if (-not (Test-Path -LiteralPath $statePath -PathType Leaf)) {
        Write-Host 'No ParkFlow demo process state exists. Nothing was stopped.'
        exit 0
    }
    $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
    if ($state.root -cne $root) { throw 'Demo process state belongs to a different project path. Refusing to stop anything.' }

    foreach ($entry in $state.processes) {
        $process = Get-Process -Id ([int]$entry.pid) -ErrorAction SilentlyContinue
        if (-not $process) { continue }
        $recorded = [DateTime]::Parse($entry.started_at).ToUniversalTime()
        $actual = $process.StartTime.ToUniversalTime()
        if ([Math]::Abs(($actual - $recorded).TotalSeconds) -gt 2) {
            Write-Warning "PID $($entry.pid) was reused; it was not stopped."
            continue
        }
        Stop-Process -Id $process.Id -Force
        Write-Host "Stopped ParkFlow $($entry.name) process PID $($entry.pid)."
    }
    Remove-Item -LiteralPath $statePath -Force
} catch {
    Write-Host ('STOP: ' + $_.Exception.Message) -ForegroundColor Red
    exit 1
}
