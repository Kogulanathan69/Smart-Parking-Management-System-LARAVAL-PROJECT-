[CmdletBinding()]
param(
    [switch]$ApproveDatabaseProvisioning,
    [switch]$RebuildFrontend,
    [switch]$DownloadModels
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'scripts\demo\Demo-Common.ps1')
$root = Get-DemoRoot
$runtime = Get-DemoRuntimePath $root
$config = Join-Path $runtime 'demo.env'
$template = Join-Path $root 'demo\Demo-Config.example.env'

try {
    Set-Location -LiteralPath $root
    New-Item -ItemType Directory -Force -Path $runtime | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $runtime 'logs') | Out-Null
    foreach ($path in @('storage\framework\cache\data','storage\framework\sessions','storage\framework\views','storage\logs','bootstrap\cache')) {
        New-Item -ItemType Directory -Force -Path (Join-Path $root $path) | Out-Null
    }
    $temp = Set-DemoPhpTemp $runtime

    if (-not (Test-Path -LiteralPath $config)) {
        Copy-Item -LiteralPath $template -Destination $config
        Protect-DemoConfig -Path $config
        Write-Host "Created $config" -ForegroundColor Yellow
        Write-Host 'Replace every CHANGE_ME value, then run Setup-Demo.ps1 again. No database changes were made.'
        exit 2
    }

    Protect-DemoConfig -Path $config
    Import-DemoConfig -Root $root | Out-Null
    $php = Resolve-DemoCommand $env:DEMO_PHP_PATH 'PHP'
    $python = Resolve-DemoCommand $env:DEMO_PYTHON_PATH 'Python'

    Write-Host 'Checking PHP and Laravel dependencies...'
    & $php '-d' "sys_temp_dir=$temp" '-d' "upload_tmp_dir=$temp" '-v' | Select-Object -First 1
    & $php '-r' "exit(extension_loaded('pdo_mysql') ? 0 : 1);"
    if ($LASTEXITCODE -ne 0) { throw 'PHP PDO MySQL extension is required.' }
    if (-not (Test-Path -LiteralPath (Join-Path $root 'vendor\autoload.php'))) {
        $composer = Resolve-DemoCommand $env:DEMO_COMPOSER_PATH 'Composer'
        $composerPhar = if ([IO.Path]::GetExtension($composer) -in @('.bat','.cmd')) { Join-Path (Split-Path $composer) 'composer.phar' } else { $null }
        if ($composerPhar -and (Test-Path -LiteralPath $composerPhar)) {
            Invoke-DemoPhp -Php $php -Temp $temp -Arguments @($composerPhar, 'install', '--no-dev', '--optimize-autoloader', '--no-interaction')
        } else {
            & $composer install --no-dev --optimize-autoloader --no-interaction
            if ($LASTEXITCODE -ne 0) { throw 'Composer install failed.' }
        }
    }

    $venvPython = Join-Path $root 'anpr-service\.venv\Scripts\python.exe'
    if (-not (Test-Path -LiteralPath $venvPython)) {
        Write-Host 'Creating ANPR virtual environment...'
        & $python -m venv (Join-Path $root 'anpr-service\.venv')
        if ($LASTEXITCODE -ne 0) { throw 'Python virtual environment creation failed.' }
        & $venvPython -m pip install --disable-pip-version-check -r (Join-Path $root 'anpr-service\requirements.txt')
        if ($LASTEXITCODE -ne 0) { throw 'ANPR dependency installation failed.' }
    }
    $modelAction = if ($DownloadModels) { 'download' } else { 'verify' }
    & $venvPython (Join-Path $root 'anpr-service\scripts\provision_models.py') $modelAction
    if ($LASTEXITCODE -ne 0) { throw 'ANPR model verification failed.' }

    if ($RebuildFrontend -or -not (Test-Path -LiteralPath (Join-Path $root 'public\build\manifest.json'))) {
        $npm = Resolve-DemoCommand 'npm.cmd' 'npm'
        Push-Location $root
        try {
            if (-not (Test-Path -LiteralPath (Join-Path $root 'node_modules'))) { & $npm ci --ignore-scripts }
            if ($LASTEXITCODE -ne 0) { throw 'npm dependency installation failed.' }
            & $npm run build
            if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }
        } finally { Pop-Location }
    }

    if (-not $ApproveDatabaseProvisioning) {
        Write-Host 'Dependencies and static assets are prepared.' -ForegroundColor Green
        Write-Host 'Database provisioning was NOT requested and was not performed.' -ForegroundColor Yellow
        Write-Host 'After approval, rerun with -ApproveDatabaseProvisioning.'
        exit 0
    }

    $confirmation = Read-Host "Persistent write approval required. Type CREATE $($env:DB_DATABASE)"
    if ($confirmation -cne "CREATE $($env:DB_DATABASE)") { throw 'Database approval phrase did not match. No database command was run.' }
    $adminUser = Read-Host 'MySQL DBA username for one-time provisioning'
    $secure = Read-Host 'MySQL DBA password (hidden)' -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try {
        $env:DEMO_DB_ADMIN_USERNAME = $adminUser
        $env:DEMO_DB_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
        $env:DEMO_PROVISION_APPROVAL = $confirmation
        Invoke-DemoPhp -Php $php -Temp $temp -Arguments @('scripts/demo/Provision-DemoDatabase.php')
    } finally {
        if ($bstr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
        Remove-Item Env:DEMO_DB_ADMIN_USERNAME,Env:DEMO_DB_ADMIN_PASSWORD,Env:DEMO_PROVISION_APPROVAL -ErrorAction SilentlyContinue
    }

    $identity = & $php '-d' "sys_temp_dir=$temp" '-d' "upload_tmp_dir=$temp" 'scripts/demo/Verify-DemoRuntime.php'
    if ($LASTEXITCODE -ne 0) { throw 'Demo database identity guard failed before migration.' }
    $identityObject = $identity | ConvertFrom-Json
    if ($identityObject.database -cne $env:DB_DATABASE -or [int]$identityObject.tables -ne 0) {
        throw 'New demo database is not empty or its identity differs. Migrations were not run.'
    }

    Write-Host 'Demo database identity verified. Applying migrations only to the newly created demo database...'
    Invoke-DemoPhp -Php $php -Temp $temp -Arguments @('artisan','migrate','--force')
    Invoke-DemoPhp -Php $php -Temp $temp -Arguments @('artisan','db:seed','--class=Database\Seeders\PortableDemoSeeder','--force')
    Invoke-DemoPhp -Php $php -Temp $temp -Arguments @('scripts/demo/Verify-DemoRuntime.php')
    Write-Host 'One-time demo setup completed.' -ForegroundColor Green
} catch {
    Write-Host ('STOP: ' + $_.Exception.Message) -ForegroundColor Red
    exit 1
} finally {
    Clear-DemoSecrets
}
