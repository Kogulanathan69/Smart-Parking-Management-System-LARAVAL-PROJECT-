# ParkFlow portable Windows demo

## Choose this method exclusively

This is the recommended first installation method on Windows. It uses `demo-runtime/demo.env`, not Laravel's root `.env`.

- Start from a clean clone with no project-level `.env*` file.
- Do not copy `.env.example` to `.env` when using this method.
- Do not run the standard Laravel installation commands from the README in the same checkout.
- If you already chose the root `.env` method, continue with standard Laravel tooling and do not use these portable launchers.

The separation is a security control: portable launchers reject root `.env*` files and `bootstrap/cache/config.php` rather than risk inheriting another database or secret.

## Status and safety boundary

The scripts in this repository prepare a repeatable Windows demo, but they have not yet been tested on a second PC. They never target `smart_parking` or `smart_parking_restore_test_20261108`; database names must begin with `parkflow_demo_` and are checked again using `SELECT DATABASE()` before Laravel migrations can run.

`Setup-Demo.ps1` performs no persistent database operation unless it is invoked with `-ApproveDatabaseProvisioning` and the operator types the exact requested database name. It does not automatically roll back or delete a partially provisioned database.

## Dependency inventory

Required on the destination PC:

- Windows 10/11 and PowerShell 5.1 or later.
- PHP 8.3 or later with PDO MySQL, OpenSSL, Mbstring, XML, Fileinfo and the normal Laravel extensions.
- MySQL 8.x. The one-time operator needs a DBA-authorized account; normal startup uses only `parkflow_demo_app` (or the configured demo account).
- Python 3.13 x64. Setup creates a local virtual environment and installs the pinned `anpr-service/requirements.txt` dependencies.
- Composer only when `vendor/` is not included in the copied package.
- Node/npm only when `public/build/manifest.json` is absent or the frontend is deliberately rebuilt.

Included in the Git repository:

- Laravel source, Smart QR migration, frontend source, and browser ticket printing. Setup builds production assets when `public/build` is absent.
- Pinned Python dependency lists.
- A checksum/source manifest for the YOLOv9 detector and OCR model. ONNX binaries are deliberately not committed.
- Safe simulated gate (`log`) and payment (`none`) drivers.

The detector-weight licence must be reviewed before commercial use. The OCR model is global and is not documented as Sri Lanka-specific, so staff confirmation remains mandatory.

## Package preparation

Use the non-destructive packaging helper from the source checkout:

```powershell
.\Build-Demo-Package.ps1 -Destination C:\ParkFlow-Demo-Package
```

It copies rather than moves files, never mirrors/deletes the destination, excludes local secrets and runtime state, and validates required assets. By default it includes `vendor/`; use `-ExcludeVendor` only when Composer will be available during destination setup.

Copy the repository without these local or sensitive items:

- `.git/`, `.env*`, `demo-runtime/`, logs, caches, screenshots and test output.
- Existing database exports or backups.
- `node_modules/` and `anpr-service/.venv/` unless the destination has the identical architecture and path requirements. Recreating the venv is safer.

Include `public/build/`, `anpr-service/models/`, and optionally `vendor/`. Never copy the current project `.env`. Verify the copied model files with `Verify-Demo.ps1` after setup.

The launchers fail closed if any project-level `.env*` file or `bootstrap/cache/config.php` is present. Runtime configuration must come only from the ignored `demo-runtime/demo.env`, preventing accidental inheritance of another installation's credentials or database.

## One-time setup

1. Copy the clean package to the destination PC.
2. Run `Setup-Demo.bat` once. It creates `demo-runtime/demo.env` and stops without touching MySQL.
3. Edit that local file and replace every `CHANGE_ME` value. Use unique random values. Keep `DB_DATABASE` under the `parkflow_demo_` prefix. The Laravel and Python ANPR tokens must match.
4. Run `Setup-Demo.bat` again. This verifies dependencies, creates the Python venv if required, verifies model hashes, and confirms compiled assets. No database is created. A clean Git clone intentionally has no model binaries; use `Setup-Demo.ps1 -DownloadModels` on an Internet-connected preparation PC to download the pinned files and verify their SHA-256 hashes.
5. After the operator explicitly approves a new persistent demo database, run an elevated or DBA-authorized terminal:

   ```powershell
   .\Setup-Demo.ps1 -ApproveDatabaseProvisioning
   ```

6. Type the exact approval phrase and enter the DBA credential interactively. The password is not printed or passed in command-line arguments. The script creates only the named demo database and a schema-scoped demo account, verifies that the database is empty, then runs migrations and `PortableDemoSeeder` against that database.

Synthetic accounts use `*@smart-parking.test` and the locally configured `SEED_DEMO_PASSWORD`. Seeded plates are `WP DEMO-1001` and `WP DEMO-2002`. They are fabricated demo records.

Setup restricts `demo-runtime/demo.env` to the current Windows account using NTFS ACLs. Run Start/Stop as that same account and do not place the runtime folder on a shared or non-NTFS volume.

If provisioning fails after creating some objects, stop and inspect them manually. The script intentionally performs no destructive cleanup.

## Starting and stopping

Run:

```powershell
.\Start-Demo.ps1
.\Verify-Demo.ps1
.\Stop-Demo.ps1
```

Equivalent `.bat` wrappers are provided for Setup, Start and Stop. Start performs a read-only database identity check, verifies ports, starts ANPR and Laravel with `artisan serve --no-reload`, waits for health endpoints and opens the local login page. Process IDs and creation times are stored in `demo-runtime/processes.json`.

Stop acts only on those recorded PIDs and verifies creation times to avoid killing a reused or unrelated PHP/Python process. It never stops MySQL and never enumerates and kills processes by executable name.

Normal startup never asks for or uses a MySQL root credential.

## Mobile QR on a trusted LAN

The default configuration is loopback-only and cannot be reached by a phone. LAN exposure requires an operator/network-owner decision:

1. Give the demo PC a stable private LAN address or trusted local DNS name.
2. Set `DEMO_LARAVEL_HOST=0.0.0.0` and `DEMO_ALLOW_LAN_BIND=true`.
3. Set `APP_URL` and `PARKING_PUBLIC_TICKET_URL` to the phone-reachable origin and port.
4. For a short controlled HTTP demo, retain `PARKING_PUBLIC_TICKET_ALLOW_HTTP=true`. Do not use this setting on an untrusted network.
5. The scripts do not create Windows Firewall rules. If a firewall change is required, obtain explicit operator approval and restrict it to the private profile and chosen port.
6. From the phone, open the configured origin before issuing tickets. If the login page is unreachable, do not issue QR URLs yet.

### Recommended HTTPS test approach

Use a locally managed reverse proxy such as Caddy with an internal CA or a certificate generated by `mkcert`. Bind Laravel to the private interface behind the proxy, configure the public ticket URL to the HTTPS origin, and install/trust only the local CA certificate on the specific demo phone. Protect the CA private key and remove the trust after the demonstration.

Do not use a public tunnel, automatically expose the PC to the Internet, disable TLS validation, or silently install a root certificate. Proxy configuration, certificate generation, phone trust and firewall changes remain explicit manual operator actions.

## Demonstration flow

1. Run `Start-Demo` and `Verify-Demo`.
2. Sign in using the synthetic staff account.
3. Upload a test vehicle image to ANPR, review the detected plate, and explicitly confirm it.
4. Check in the vehicle and print/scan the generated Smart QR URL.
5. On the phone, confirm plate, zone, slot, package, live duration and estimated bill. Confirm no name, email or phone is shown.
6. At staff checkout, scan either the URL QR or its retained legacy `SPT1` credential, review the bill and settle using the synthetic cash flow.
7. Confirm receipt creation, slot release, final mobile-ticket state and rejection of a reissued/revoked old URL.
8. Stop the demo with `Stop-Demo`.

No real gate is operated, no external payment service is contacted, and the Python service does not authorize an exit.

## Readiness matrix

| Component | Status | Evidence / limitation |
|---|---|---|
| Smart QR application and security behavior | TESTED | SQLite feature and regression suite: 129 tests, 743 assertions in the prior verification phase. |
| Frontend production build | TESTED | Vite production build completed in the prior phase. |
| Local ANPR Python imports and model presence | TESTED | Current PC imports succeeded; checksum verification is repeated by setup. |
| Portable setup/start/stop/verify scripts | IMPLEMENTED | Syntax and non-destructive checks validated locally; persistent provisioning not executed. |
| Fresh MySQL demo provisioning | NOT TESTED | Requires explicit operator approval. |
| Full MySQL integration journey | NOT TESTED | Requires the approved disposable demo database. |
| Second Windows PC portability | NOT TESTED | Must be validated on the target machine. |
| Phone LAN and HTTPS reachability | NOT TESTED | Requires network, firewall and certificate decisions by the operator. |

## Required approvals

- Creation of the new `parkflow_demo_*` database and schema-scoped MySQL account.
- Running Laravel migrations and `PortableDemoSeeder` on that exact database.
- Any LAN binding or Windows Firewall change.
- Local certificate-authority installation/trust on the PC or phone.
- Use of real camera feeds or non-synthetic vehicle images.

No approval is implied for either protected MySQL database.
