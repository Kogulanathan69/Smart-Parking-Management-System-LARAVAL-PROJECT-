@echo off
REM Usage: start-camera-agent.bat path\to\camera.env   (one .env per camera: code, token, optional source)
cd /d "%~dp0\.."
call .venv\Scripts\activate.bat || (echo Virtual environment missing - see README.md & exit /b 1)
if not "%~1"=="" for /f "usebackq eol=# tokens=1,* delims==" %%A in ("%~1") do set "%%A=%%B"
python -m anpr_service.agent
