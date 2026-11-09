Set-StrictMode -Version 2.0

function Get-DemoRoot {
    return (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
}

function Get-DemoRuntimePath {
    param([string]$Root)
    return (Join-Path $Root 'demo-runtime')
}

function Protect-DemoConfig {
    param([string]$Path)
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    & icacls.exe $Path '/inheritance:r' '/grant:r' "${identity}:(F)" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not restrict demo configuration permissions to the current Windows account.' }
}

function Import-DemoConfig {
    param([string]$Root, [switch]$AllowPlaceholders)

    $projectEnvFiles = @(Get-ChildItem -LiteralPath $Root -File -Filter '.env*' -ErrorAction SilentlyContinue | Where-Object { $_.Name -ne '.env.example' })
    if ($projectEnvFiles.Count -gt 0) {
        throw 'Portable demo refuses project-level .env files. Package without .env* and use only demo-runtime/demo.env.'
    }
    if (Test-Path -LiteralPath (Join-Path $Root 'bootstrap\cache\config.php')) {
        throw 'Portable demo refuses a cached Laravel configuration because it may contain another database or secret.'
    }

    $configPath = Join-Path (Get-DemoRuntimePath $Root) 'demo.env'
    if (-not (Test-Path -LiteralPath $configPath -PathType Leaf)) {
        throw "Demo configuration is missing: $configPath. Run Setup-Demo.ps1 first."
    }

    foreach ($rawLine in Get-Content -LiteralPath $configPath) {
        $line = $rawLine.Trim()
        if (-not $line -or $line.StartsWith('#')) { continue }
        $separator = $line.IndexOf('=')
        if ($separator -lt 1) { throw "Invalid demo configuration line (expected KEY=VALUE)." }
        $name = $line.Substring(0, $separator).Trim()
        $value = $line.Substring($separator + 1)
        if ($name -notmatch '^[A-Z][A-Z0-9_]*$') { throw "Invalid demo configuration key: $name" }
        [Environment]::SetEnvironmentVariable($name, $value, 'Process')
    }

    if (-not $AllowPlaceholders) {
        $required = @('APP_KEY','DB_DATABASE','DB_USERNAME','DB_PASSWORD','PARKING_ANPR_SERVICE_TOKEN','ANPR_SERVICE_TOKEN','SEED_DEMO_PASSWORD')
        foreach ($name in $required) {
            $value = [Environment]::GetEnvironmentVariable($name, 'Process')
            if ([string]::IsNullOrWhiteSpace($value) -or $value -match '^CHANGE_ME') {
                throw "Demo configuration value $name has not been set."
            }
        }
        if ($env:PARKING_ANPR_SERVICE_TOKEN -cne $env:ANPR_SERVICE_TOKEN) {
            throw 'PARKING_ANPR_SERVICE_TOKEN and ANPR_SERVICE_TOKEN must match.'
        }
        if ($env:DB_DATABASE -notmatch '^parkflow_demo_[A-Za-z0-9_]+$') {
            throw 'DB_DATABASE must use the parkflow_demo_ prefix.'
        }
        if ($env:DB_DATABASE -in @('smart_parking','smart_parking_restore_test_20261108')) {
            throw 'Protected database name refused.'
        }
    }

    return $configPath
}

function Resolve-DemoCommand {
    param([string]$Configured, [string]$Label)
    if ([string]::IsNullOrWhiteSpace($Configured)) { throw "$Label path is empty." }
    if (Test-Path -LiteralPath $Configured -PathType Leaf) { return (Resolve-Path -LiteralPath $Configured).Path }
    $command = Get-Command $Configured -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $command) { throw "$Label was not found: $Configured" }
    return $command.Source
}

function Assert-DemoPortFree {
    param([int]$Port, [string]$Label)
    $listener = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
    if ($listener) { throw "$Label port $Port is already in use; no demo process was started." }
}

function Set-DemoPhpTemp {
    param([string]$Runtime)
    $temp = Join-Path $Runtime 'tmp'
    New-Item -ItemType Directory -Force -Path $temp | Out-Null
    $env:TEMP = $temp
    $env:TMP = $temp
    return $temp
}

function Invoke-DemoPhp {
    param([string]$Php, [string]$Temp, [Parameter(ValueFromRemainingArguments=$true)][string[]]$Arguments)
    & $Php '-d' "sys_temp_dir=$Temp" '-d' "upload_tmp_dir=$Temp" @Arguments
    if ($LASTEXITCODE -ne 0) { throw "PHP command failed with exit code $LASTEXITCODE." }
}

function Clear-DemoSecrets {
    foreach ($name in @('DB_PASSWORD','SEED_DEMO_PASSWORD','PARKING_ANPR_SERVICE_TOKEN','ANPR_SERVICE_TOKEN','DEMO_DB_ADMIN_PASSWORD','DEMO_PROVISION_APPROVAL')) {
        Remove-Item -Path ("Env:" + $name) -ErrorAction SilentlyContinue
    }
}
