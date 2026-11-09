# ParkFlow Smart Parking Management System

ParkFlow is a Laravel, MySQL and Python ONNX parking-management application with staff check-in/check-out, ANPR review, parking billing, receipts, slot management and privacy-limited Smart QR mobile tickets.

## Choose one Windows installation method

The two methods are intentionally separate and must not be mixed in one checkout:

### Method A — Guided Portable Demo (recommended for first-time Windows users)

This method uses only the ignored `demo-runtime/demo.env`. It deliberately rejects project-level `.env*` files and cached Laravel configuration so another installation's credentials cannot leak into the demo.

```powershell
git clone --branch codex/smart-qr-ticket https://github.com/Kogulanathan69/Smart-Parking-Management-System-LARAVAL-PROJECT-.git ParkFlow
cd ParkFlow
.\Setup-Demo.bat
# Edit demo-runtime\demo.env and replace every CHANGE_ME value.
.\Setup-Demo.ps1 -DownloadModels
# Only after explicit approval to create the new parkflow_demo_* database:
.\Setup-Demo.ps1 -ApproveDatabaseProvisioning
.\Start-Demo.bat
.\Verify-Demo.ps1
```

Stop only the processes recorded by the demo launcher:

```powershell
.\Stop-Demo.bat
```

The complete approval, MySQL, mobile QR and HTTPS workflow is in [docs/PORTABLE_DEMO_WINDOWS.md](docs/PORTABLE_DEMO_WINDOWS.md).

### Method B — Standard Laravel installation (operators familiar with Laravel)

This method uses the root `.env` and normal Laravel commands. Do not run the Portable Demo launchers in this checkout: they correctly refuse root `.env*` files.

Create a fresh schema and schema-scoped MySQL account first. Never point these commands at an existing database. Then:

```powershell
git clone --branch codex/smart-qr-ticket https://github.com/Kogulanathan69/Smart-Parking-Management-System-LARAVAL-PROJECT-.git ParkFlow
cd ParkFlow
composer install
Copy-Item .env.example .env
# Fill only this machine's DB_*, APP_URL and PARKING_* values in .env.
php artisan key:generate
npm ci
npm run build
python -m venv anpr-service\.venv
anpr-service\.venv\Scripts\python.exe -m pip install -r anpr-service\requirements.txt
anpr-service\.venv\Scripts\python.exe anpr-service\scripts\provision_models.py download
php artisan migrate --force
php artisan db:seed --class=Database\Seeders\PortableDemoSeeder --force
php artisan serve --no-reload --host=127.0.0.1 --port=8000
```

Before either database command, independently verify that `SELECT DATABASE()` returns the newly created `parkflow_demo_*` database. The portable workflow performs that guard automatically; Method B makes it the operator's responsibility.

Smart QR architecture and deployment requirements are documented in [docs/SMART_QR_TICKET_IMPLEMENTATION.md](docs/SMART_QR_TICKET_IMPLEMENTATION.md).

## Security defaults

- `.env`, database exports, logs, virtual environments, runtime files and model binaries are excluded from Git.
- Public Smart QR tokens are separate from privileged checkout credentials.
- Gate and payment integrations default to safe simulated drivers.
- Staff verification remains mandatory for ANPR results.
- ONNX model files are downloaded from pinned sources and verified by SHA-256; review their weight licences before commercial use.
