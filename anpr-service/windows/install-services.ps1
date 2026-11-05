# Registers the ANPR service and one camera agent as Windows services using NSSM (https://nssm.cc, copy nssm.exe
# to C:\smart-parking\tools beforehand — no Internet needed on the parking PC). Run in an elevated PowerShell.
param(
  [string]$Root = "C:\smart-parking\anpr-service",
  [string]$Nssm = "C:\smart-parking\tools\nssm.exe",
  [string[]]$CameraEnvFiles = @()
)
$py = Join-Path $Root ".venv\Scripts\python.exe"
& $Nssm install SmartParkingANPR $py "-m" "anpr_service.app"
& $Nssm set SmartParkingANPR AppDirectory $Root
& $Nssm set SmartParkingANPR AppStdout (Join-Path $Root "logs\service.log")
& $Nssm set SmartParkingANPR AppStderr (Join-Path $Root "logs\service.log")
& $Nssm set SmartParkingANPR AppRotateFiles 1
& $Nssm set SmartParkingANPR AppRotateBytes 10485760
& $Nssm set SmartParkingANPR Start SERVICE_AUTO_START

foreach ($envFile in $CameraEnvFiles) {
  $code = (Get-Content $envFile | Where-Object { $_ -match '^ANPR_CAMERA_CODE=' }) -replace '^ANPR_CAMERA_CODE=', ''
  $name = "SmartParkingCam-$code"
  & $Nssm install $name $py "-m" "anpr_service.agent"
  & $Nssm set $name AppDirectory $Root
  $vars = Get-Content $envFile | Where-Object { $_ -match '^[A-Z_]+=' }
  & $Nssm set $name AppEnvironmentExtra $vars
  & $Nssm set $name AppStdout (Join-Path $Root "logs\$code.log")
  & $Nssm set $name AppStderr (Join-Path $Root "logs\$code.log")
  & $Nssm set $name AppRotateFiles 1
  & $Nssm set $name DependOnService SmartParkingANPR
  & $Nssm set $name Start SERVICE_AUTO_START
}
New-Item -ItemType Directory -Force -Path (Join-Path $Root "logs") | Out-Null
Write-Host "Installed. Restrict $Root\.env and camera .env files to Administrators (they hold tokens)."
