# Frontend runbook

## What the frontend is
Server-rendered **Blade** page shells + **Alpine.js 3** components + **Tailwind CSS 4** (single token file), built by **Vite 8**. Every screen reads and writes through the existing `/api/v1` JSON API with the same Fortify session cookie and CSRF token, so all business rules (pricing, settlement, idempotency, gate, verification) stay on the server.
*Livewire was not added*: the backend already exposes a complete, tested JSON API meant for the browser (see `docs/API.md`), and Livewire components would have duplicated controller logic.

New packages (dev, bundled into `public/build`; nothing loads from the Internet at runtime): `alpinejs@3.17.5`, `@alpinejs/focus@3.17.5`. The web-font download in `vite.config.js` was removed (system fonts) so the UI works offline.

## Build and run (Windows / Herd)
```powershell
cd C:\Users\kogul\Herd\smart-parking-anpr        # the worktree holding feature/parkflow-frontend
npm ci                                            # once (needs the npm registry or an offline npm cache)
npm run build                                     # writes public/build (git-ignored)
php artisan migrate                               # ONLY after a DB backup and approval (no new migrations in this branch)
```
Open `https://smart-parking-anpr.test` (Herd) and sign in. For development with hot reload: `npm run dev`.

Offline parking PC: run `npm ci && npm run build` on a machine with Internet, then copy the whole project including `public/build`. The PC itself needs no Node.js to serve the UI.

## Accounts
* Customers self-register at `/register`.
* Super admin: `php artisan parking:create-super-admin --name="…" --email=…`.
* Other staff/admin/auditor accounts: **Management › Users** (role limits enforced by the server).
* Demo accounts exist only when `DemoUserSeeder` runs in `local`/`testing` (`*@smart-parking.test`, password from `SEED_DEMO_PASSWORD`). Any data you create with them is **demonstration data**.

## Configuration that changes what the UI shows
| Setting / env | Effect in the UI |
|---|---|
| `PARKING_ANPR_ENGINE` (`disabled` / `http_local`) | Header chip "ANPR engine online/offline"; upload recognition returns *Engine unavailable* and staff use manual identification |
| `PARKING_GATE_DRIVER` (`log`) | Gate results are labelled **simulated (no gate hardware)** everywhere |
| `PARKING_PAYMENT_GATEWAY` (`none`) | Online payments are recorded as pending and confirmed manually by staff |
| `exit.release_slot_on` = `gate` | Check-Out shows *Confirm vehicle has left* |
| `anpr.verification_mode` | `always` (default): every reading must be confirmed/corrected before use |
| `safety.require_exit_inspection` | Exit inspection form blocks settlement for safety parking |

## Tests
```bash
vendor/bin/pest                                   # PHP: API + page tests (FrontendPagesTest uses withoutVite)
npm run build                                     # asset build must succeed
cd anpr-service && python -m pytest -q            # Python ANPR service
# browser journeys against a disposable, seeded DB and a running server:
E2E_BASE_URL=http://127.0.0.1:8000 E2E_PASSWORD='<SEED_DEMO_PASSWORD>' node tests/e2e/journeys.mjs
```
The E2E runner needs Playwright with Chromium (`npm i -g playwright && npx playwright install chromium` on a dev machine). It is not part of `package.json` so production installs stay small.

## Troubleshooting
| Symptom | Cause / fix |
|---|---|
| Page shows unstyled HTML | `npm run build` not run, or `public/build` not copied |
| "Session expired" (419) after a long idle | CSRF token rotated — reload the page |
| Redirected to sign-in mid-task | Session ended (401); sign in again — nothing was submitted |
| "Too many requests" on sign-in | 5 attempts/minute per email+IP; wait a minute |
| Check-In upload says engine unavailable | Start the ANPR service (`anpr-service/windows/start-anpr-service.bat`) or use *Type plate manually* |
| Camera tab empty | No camera agent is running/pushing readings; use upload or manual entry |
| Gate never opens physically | Expected: no gate hardware driver exists yet (simulated); staff open the barrier by hand |
