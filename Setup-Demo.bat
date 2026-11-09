@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Setup-Demo.ps1" %*
exit /b %ERRORLEVEL%
