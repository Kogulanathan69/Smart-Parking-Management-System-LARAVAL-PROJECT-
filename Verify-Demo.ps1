[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'scripts\demo\Demo-Common.ps1')
$root = Get-DemoRoot
$runtime = Get-DemoRuntimePath $root

try {
    Set-Location -LiteralPath $root
    Import-DemoConfig -Root $root | Out-Null
    $temp = Set-DemoPhpTemp $runtime
    $php = Resolve-DemoCommand $env:DEMO_PHP_PATH 'PHP'
    $python = Join-Path $root 'anpr-service\.venv\Scripts\python.exe'

    Write-Host 'Static files:'
    foreach ($file in @('artisan','vendor\autoload.php','public\build\manifest.json','database\migrations\2026_11_09_000001_create_public_ticket_tokens_table.php')) {
        if (-not (Test-Path -LiteralPath (Join-Path $root $file) -PathType Leaf)) { throw "Missing required file: $file" }
    }
    Write-Host '  PASS application, dependencies, assets, and Smart QR migration are present.' -ForegroundColor Green

    if (-not (Test-Path -LiteralPath $python)) { throw 'ANPR virtual environment is missing.' }
    & $python -c "import fastapi,uvicorn,cv2,onnxruntime,numpy; print('  PASS ANPR Python imports')"
    if ($LASTEXITCODE -ne 0) { throw 'ANPR Python import verification failed.' }
    & $python (Join-Path $root 'anpr-service\scripts\provision_models.py') verify
    if ($LASTEXITCODE -ne 0) { throw 'ANPR model checksum verification failed.' }

    Write-Host 'Database identity (read only):'
    & $php '-d' "sys_temp_dir=$temp" '-d' "upload_tmp_dir=$temp" 'scripts/demo/Verify-DemoRuntime.php'
    if ($LASTEXITCODE -ne 0) { throw 'Demo database identity verification failed.' }

    $webPort = [int]$env:DEMO_LARAVEL_PORT
    $anprPort = [int]$env:DEMO_ANPR_PORT
    try {
        $web = Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$webPort/up" -TimeoutSec 3
        Write-Host "  PASS Laravel health HTTP $($web.StatusCode)" -ForegroundColor Green
    } catch { Write-Host '  NOT RUN Laravel service is not currently reachable.' -ForegroundColor Yellow }
    try {
        $live = Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$anprPort/v1/live" -TimeoutSec 3
        $headers = @{ Authorization = 'Bearer ' + $env:ANPR_SERVICE_TOKEN }
        $health = Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$anprPort/v1/health" -Headers $headers -TimeoutSec 10
        Write-Host "  PASS ANPR live/engine health HTTP $($live.StatusCode)/$($health.StatusCode)" -ForegroundColor Green
    } catch { Write-Host '  NOT RUN ANPR service is not currently reachable or engine health is degraded.' -ForegroundColor Yellow }

    $publicUri = [Uri]$env:PARKING_PUBLIC_TICKET_URL
    Write-Host "Mobile QR configured origin: $($publicUri.Scheme)://$($publicUri.Authority)"
    if ($publicUri.Host -in @('127.0.0.1','localhost')) {
        Write-Host '  LOCAL ONLY: change the host to a trusted LAN hostname/address for phone testing.' -ForegroundColor Yellow
    } else {
        Write-Host '  LAN CONFIGURED: verify reachability from the phone manually; this script changes no firewall rules.' -ForegroundColor Yellow
    }
    Write-Host 'Demo verification completed without database writes.' -ForegroundColor Green
} catch {
    Write-Host ('STOP: ' + $_.Exception.Message) -ForegroundColor Red
    exit 1
} finally {
    Clear-DemoSecrets
}
