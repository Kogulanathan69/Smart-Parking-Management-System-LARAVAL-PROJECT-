# ANPR offline deployment (Windows, single PC or LAN)

The parking system, including ANPR, runs with **no Internet connection**. The Internet is needed only once, on any
machine, to download the Python wheels and the two ONNX model files. They are carried to the parking PC on a USB drive.

> Status: the software pipeline is implemented and tested with synthetic images and simulated cameras.
> **It has not been tested with real IP cameras, real gate hardware, or real Sri Lankan plates.** Recognition accuracy
> is unknown until it is measured on site with `evaluate.py` (step 7). Readings are always verified by staff.

## 1. Topology

```
Single PC (booth)                                  LAN (server PC + booth PCs + IP cameras)
┌───────────────────────────────┐                  ┌──────────── server PC ─────────────┐
│ Laravel (Herd/Apache/Nginx)   │                  │ Laravel + MySQL                     │
│ MySQL                         │                  │ ANPR service  127.0.0.1:8765        │
│ ANPR service  127.0.0.1:8765  │                  │ camera agent ×N ──RTSP──► cameras   │
│ camera agent ──USB/RTSP──► cam│                  └────────────▲────────────────────────┘
└───────────────────────────────┘                     booth PCs (browser only, HTTPS)
```

* The **recognition service** always listens on loopback. Only processes on the same PC can reach it.
* **Camera agents** normally run on the server PC as well. Each one pulls its camera's RTSP stream over the LAN and pushes readings to Laravel with that camera's own device token.
* Booth PCs need only a browser. Staff verify readings in the UI.
* Put the cameras on their own VLAN or switch if possible. Allow the camera agent PC to reach them on RTSP (554) only.

## 2. One-time preparation (on a machine WITH Internet)

Use the same Python minor version and architecture as the parking PC (Windows x64, Python 3.12 or 3.13).

```powershell
cd anpr-service
py -3.13 -m venv .venv
.venv\Scripts\pip download -r requirements.txt -d wheelhouse --only-binary=:all: --platform win_amd64 --python-version 3.13
.venv\Scripts\pip install -r requirements.txt
.venv\Scripts\python scripts\provision_models.py download      # fetches and verifies the pinned SHA-256 hashes
```

Copy these to the USB drive: the `anpr-service` folder, including `wheelhouse\` and `models\` with its `manifest.json`. Also copy the Python installer, and `nssm.exe` if you want Windows services.

## 3. Install on the parking PC (offline)

```powershell
cd C:\smart-parking\anpr-service
py -3.13 -m venv .venv
.venv\Scripts\pip install --no-index --find-links wheelhouse -r requirements.txt
.venv\Scripts\python scripts\provision_models.py verify        # must print "all 3 model files verified"
copy .env.example .env                                          # then edit it
```

Generate the shared service token:

```powershell
.venv\Scripts\python -c "import secrets;print(secrets.token_urlsafe(48))"
```

Put the **same** value in `anpr-service\.env` (`ANPR_SERVICE_TOKEN`) and in Laravel's `.env` (`PARKING_ANPR_SERVICE_TOKEN`).

## 4. Laravel configuration (`.env`, not committed)

| Variable | Example | Notes |
|---|---|---|
| `PARKING_ANPR_ENGINE` | `http_local` | `disabled` (default) keeps ANPR off. Manual entry and exit still work |
| `PARKING_ANPR_SERVICE_URL` | `http://127.0.0.1:8765` | Loopback only |
| `PARKING_ANPR_SERVICE_TOKEN` | *(48+ random chars)* | Same as `ANPR_SERVICE_TOKEN` |
| `PARKING_ANPR_CAMERA_NETWORKS` | `192.168.10.0/24` | CIDRs that camera stream URLs may point to. Default is all RFC1918 ranges; narrow it to the camera VLAN |
| `PARKING_ANPR_CAMERA_HOSTNAMES` | `cam-in-1.parking.local` | Optional hostname allow-list |
| `PARKING_ANPR_IMAGE_DISK` | `local` | Private disk (`storage/app/private`). Never use the public disk |

Then run, **after a database backup and with approval**:

```powershell
php artisan migrate        # adds the anpr_* and gate_commands tables (additive; no existing table is changed)
php artisan config:cache
```

The Laravel scheduler must run every minute (Task Scheduler → `php artisan schedule:run`). It runs `parking:anpr-health-check` every minute and `parking:purge-anpr-images` daily at 02:30.

## 5. Register cameras

In **Admin → ANPR cameras** (`POST /api/v1/admin/anpr/cameras`), create one camera per lane, for example `CAM-IN-1` (entry) and `CAM-OUT-1` (exit). Give the RTSP URL **without** credentials and put the username and password in their own fields; they are stored encrypted. Copy the **device token** shown once into that camera's agent env file, for example `C:\smart-parking\cameras\CAM-IN-1.env`:

```
ANPR_CAMERA_CODE=CAM-IN-1
ANPR_CAMERA_TOKEN=anpr_…
ANPR_BACKEND_URL=http://127.0.0.1
ANPR_SERVICE_TOKEN=…
# ANPR_SOURCE=usb:0         (USB webcam instead of the configured RTSP stream)
```

Restrict these files to Administrators: `icacls CAM-IN-1.env /inheritance:r /grant:r Administrators:F`. If a token leaks, use **rotate-token**; the old token stops working immediately.

Check a camera without starting it:

```powershell
.venv\Scripts\python -m anpr_service.agent --check
```

## 6. Start / stop

| Task | Command |
|---|---|
| Start service (console) | `windows\start-anpr-service.bat` |
| Start one camera (console) | `windows\start-camera-agent.bat C:\smart-parking\cameras\CAM-IN-1.env` |
| Install as Windows services | elevated PowerShell: `windows\install-services.ps1 -CameraEnvFiles C:\smart-parking\cameras\CAM-IN-1.env,C:\smart-parking\cameras\CAM-OUT-1.env` |
| Stop / start services | `nssm stop SmartParkingCam-CAM-IN-1`, `nssm restart SmartParkingANPR` |
| Startup order | MySQL → web server → `SmartParkingANPR` → camera agents (the agents depend on the service) |
| Shutdown | Stop the camera agents first, then the service. Unsent readings stay in the spool |

**Logs.**

* `anpr-service\logs\service.log` and `logs\<camera>.log` (rotated at 10 MB). These never contain images, plate crops or tokens. Plate text is not logged by the service.
* Laravel: `storage\logs\laravel.log`.
* Camera health history: `GET /api/v1/reports/anpr/camera-health`.

## 7. Measure accuracy before go-live (required)

Collect at least several hundred labelled images per lane on site: day, night, rain, motorbikes, two-line plates, and dirty or tilted plates. Write `labels.csv` with the columns `filename,plate`, then run:

```powershell
.venv\Scripts\python -m anpr_service.evaluate --images D:\eval\images --labels D:\eval\labels.csv --threshold 0.90
```

Use `false_match_at_threshold_pct` and `accepted_precision_pct` to choose `anpr.min_confidence_percent` and the verification mode. Keep `verification_mode = always` unless the client explicitly approves `below_threshold` based on measured results. The bundled OCR model is a *global* model, and **Sri Lanka is not among its listed training regions**, so fine-tuning on local plates may be needed.

## 8. Failure behaviour

| Failure | What happens |
|---|---|
| ANPR service down | Agents send error heartbeats. Staff recognise requests return `503 engine_error`. Staff use **manual review** with reason `engine_unavailable` |
| Camera offline | Marked offline after `anpr.camera_offline_after_seconds`. The agent reconnects with backoff. Staff use manual entry/exit with reason `camera_offline` |
| Laravel or LAN down | The agent spools readings to disk (`ANPR_SPOOL_DIR`) and replays them with the same idempotency keys, so no duplicates. Entries older than about 20 h are discarded |
| Gate controller error | Settlement still completes. The failed gate command is recorded, and staff retry with `POST /staff/sessions/{uuid}/gate/exit` |
| Wrong reading | Staff **correct** it (reason required). The correction is kept in the immutable review trail and the audit report |

## 9. Backup and privacy

* MySQL backups: as for the main system. The `anpr_events` table contains plate numbers, which are personal data.
* Images live under `storage\app\private\anpr\…`. Include them in backups only if the client's retention policy requires it.
* Retention: `anpr.snapshot_retention_days` (default 30, **pending client decision**). Purged images keep their metadata row with `retention_status = purged`.
* Full frames are not stored unless `anpr.store_full_frame = true` (**pending client decision**).
* The spool folder holds plate crops while the backend is unreachable. Keep it on the protected local disk.
