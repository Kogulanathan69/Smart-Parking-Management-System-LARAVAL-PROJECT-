[CmdletBinding()]
param([switch]$NoBrowser)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'scripts\demo\Demo-Common.ps1')
$root = Get-DemoRoot
$runtime = Get-DemoRuntimePath $root
$statePath = Join-Path $runtime 'processes.json'
$started = @()

try {
    Set-Location -LiteralPath $root
    Import-DemoConfig -Root $root | Out-Null
    $temp = Set-DemoPhpTemp $runtime
    $php = Resolve-DemoCommand $env:DEMO_PHP_PATH 'PHP'
    $python = Join-Path $root 'anpr-service\.venv\Scripts\python.exe'
    if (-not (Test-Path -LiteralPath $python)) { throw 'ANPR virtual environment is missing. Run Setup-Demo.ps1.' }
    if (-not (Test-Path -LiteralPath (Join-Path $root 'public\build\manifest.json'))) { throw 'Compiled frontend is missing. Run Setup-Demo.ps1.' }
    if (Test-Path -LiteralPath $statePath) { throw 'A demo state file already exists. Run Stop-Demo.ps1 or investigate it before starting.' }

    $webPort = [int]$env:DEMO_LARAVEL_PORT
    $anprPort = [int]$env:DEMO_ANPR_PORT
    Assert-DemoPortFree $webPort 'Laravel'
    Assert-DemoPortFree $anprPort 'ANPR'

    $webHost = $env:DEMO_LARAVEL_HOST
    $lan = $env:DEMO_ALLOW_LAN_BIND -match '^(1|true|yes|on)$'
    if ($webHost -notin @('127.0.0.1','localhost') -and -not $lan) {
        throw 'LAN binding requires DEMO_ALLOW_LAN_BIND=true after operator network approval.'
    }
    $publicUri = [Uri]$env:PARKING_PUBLIC_TICKET_URL
    if (-not $publicUri.IsAbsoluteUri) { throw 'PARKING_PUBLIC_TICKET_URL must be an absolute URL.' }
    if ($lan -and $publicUri.Host -in @('127.0.0.1','localhost')) {
        throw 'LAN mode requires PARKING_PUBLIC_TICKET_URL to use the PC LAN hostname or address, not localhost.'
    }
    if ($publicUri.Scheme -eq 'http' -and $env:PARKING_PUBLIC_TICKET_ALLOW_HTTP -notmatch '^(1|true|yes|on)$') {
        throw 'HTTP public ticket URLs require the explicit demo-only HTTP opt-in.'
    }

    & $php '-d' "sys_temp_dir=$temp" '-d' "upload_tmp_dir=$temp" 'scripts/demo/Verify-DemoRuntime.php' | Out-Host
    if ($LASTEXITCODE -ne 0) { throw 'Demo database verification failed; nothing was started.' }
    Remove-Item Env:SEED_DEMO_PASSWORD -ErrorAction SilentlyContinue

    $logs = Join-Path $runtime 'logs'
    New-Item -ItemType Directory -Force -Path $logs | Out-Null
    $anpr = Start-Process -FilePath $python -ArgumentList @('-m','anpr_service.app') -WorkingDirectory (Join-Path $root 'anpr-service') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $logs 'anpr.out.log') -RedirectStandardError (Join-Path $logs 'anpr.err.log')
    $started += $anpr
    $laravel = Start-Process -FilePath $php -ArgumentList @('-d',"sys_temp_dir=$temp",'-d',"upload_tmp_dir=$temp",'artisan','serve','--no-reload',"--host=$webHost","--port=$webPort") -WorkingDirectory $root -WindowStyle Hidden -PassThru -RedirectStandardOutput 'NUL' -RedirectStandardError (Join-Path $logs 'laravel.err.log')
    $started += $laravel

    $state = [ordered]@{
        root = $root
        created_at = [DateTime]::UtcNow.ToString('o')
        processes = @(
            [ordered]@{ name='anpr'; pid=$anpr.Id; started_at=$anpr.StartTime.ToUniversalTime().ToString('o'); executable=$python },
            [ordered]@{ name='laravel'; pid=$laravel.Id; started_at=$laravel.StartTime.ToUniversalTime().ToString('o'); executable=$php }
        )
    }
    $state | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $statePath -Encoding UTF8

    $deadline = (Get-Date).AddSeconds(30)
    do {
        Start-Sleep -Milliseconds 500
        $anprOk = $false; $webOk = $false
        try { $anprOk = (Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$anprPort/v1/live" -TimeoutSec 2).StatusCode -eq 200 } catch {}
        try { $webOk = (Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$webPort/up" -TimeoutSec 2).StatusCode -eq 200 } catch {}
    } until (($anprOk -and $webOk) -or (Get-Date) -ge $deadline)
    if (-not ($anprOk -and $webOk)) { throw 'Services did not become healthy within 30 seconds. Run Stop-Demo.ps1 and inspect demo-runtime/logs.' }

    Write-Host "ParkFlow demo started: http://127.0.0.1:$webPort/login" -ForegroundColor Green
    Write-Host "Public ticket base URL: $($env:PARKING_PUBLIC_TICKET_URL)"
    if ($lan) { Write-Host 'LAN binding is active. No Windows Firewall rule was created by ParkFlow.' -ForegroundColor Yellow }
    if (-not $NoBrowser) { Start-Process "http://127.0.0.1:$webPort/login" | Out-Null }
} catch {
    Write-Host ('STOP: ' + $_.Exception.Message) -ForegroundColor Red
    if ($started.Count -gt 0) {
        foreach ($process in $started) { Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue }
        Remove-Item -LiteralPath $statePath -Force -ErrorAction SilentlyContinue
    }
    exit 1
} finally {
    Clear-DemoSecrets
}
