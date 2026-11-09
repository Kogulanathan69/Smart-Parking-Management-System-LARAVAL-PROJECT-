@echo off
REM Starts the local ANPR recognition service (loopback only). Run from the anpr-service folder.
cd /d "%~dp0\.."
call .venv\Scripts\activate.bat || (echo Virtual environment missing - see README.md & exit /b 1)
python scripts\provision_models.py verify || (echo Model files failed verification & exit /b 1)
python -m anpr_service.app
