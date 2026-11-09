# Test results — `feature/parkflow-frontend`

Run in the cloud build environment on 2026-11-02 (PHP 8.5.0, MySQL 8.0, Node 22, Chromium via Playwright, Python 3.13). Numbers are copied from the actual runs; nothing is estimated.

| Suite | Command | Result |
|---|---|---|
| Baseline before frontend work (`8c64ede`) | `vendor/bin/pest` | 261 passed |
| PHP, SQLite in-memory | `vendor/bin/pest` | **276 passed, 0 failed (2,378 assertions)** |
| PHP, MySQL 8.0 (scratch DB) | `DB_CONNECTION=mysql … vendor/bin/pest` | **276 passed (2,378 assertions)** |
| MySQL multi-process races | `vendor/bin/pest tests/Concurrency` | **12 passed (54 assertions)** |
| Python ANPR service | `python -m pytest -q` | **46 passed, 1 skipped** (real-model smoke test runs separately) |
| Python real-model smoke test | `ANPR_TEST_MODELS_DIR=… pytest tests/test_onnx_smoke.py` | 1 passed |
| Asset build | `npm run build` | passed (CSS 66.7 kB / 10.7 kB gz, JS 137.1 kB / 44.1 kB gz) |
| Lint / format | `pint --dirty`, `php -l`, `pyflakes` | clean |
| Browser E2E, ANPR engine **online** (real local ONNX service, fresh DB) | `node tests/e2e/journeys.mjs` | **9/9 journeys passed** |
| Browser E2E, ANPR engine **disabled** | same | **9/9 passed** (Check-In uses manual identification; the "unreadable photo" journey is a no-op when the engine is off) |

New tests added on this branch: `tests/Feature/FrontendPagesTest.php` (10 tests, 709 assertions: every page × every role, guest redirects, sidebar links, menu isolation, HTML/JSON dashboards, no secrets in HTML, Fortify screens, customer-only registration, error pages, role matrix), `tests/Feature/FrontendSupportEndpointsTest.php` (4 tests), 1 new test in `AnprTest.php` (detector confidence), 1 new test in `anpr-service/tests/test_agent.py`, and the E2E suite `tests/e2e/journeys.mjs`.

## E2E journeys (what they actually do)
1. Each of the five roles signs in and lands on its own dashboard with no console errors; a wrong password is refused.
2. Staff Check-In: upload a **synthetic** plate image → real recognition → Confirm → category/fuel/zone/package → confirm → session created (manual identification when the engine is off).
3. Staff Check-Out of that vehicle: manual plate → exact session match → server bill → cash → confirm dialog → "Exit authorised by the server", receipt, gate labelled *simulated*.
4. Unreadable photo: Confirm is not offered; Reject with a reason works.
5. Admin: System status labels the gate as simulated; creates a penalty type priced LKR 250.50 and sees it formatted correctly.
6. Auditor: five report/audit pages show no Settle / Check-In / Confirm / Retry / Approve / Waive / Mark paid buttons; `POST /api/v1/staff/entries` from the auditor's session → 403.
7. Customer: parking info shows LKR prices; adds a vehicle; `/ops/check-in` → 403.
8. Responsive: staff dashboard, Check-In, Check-Out, parked vehicles, review queue at 320, 375, 768, 1366, 1920 px — no horizontal overflow; mobile menu opens.
9. Keyboard: first Tab reaches "Skip to content"; Escape closes a dialog.

## Additional scripted checks (run, not committed as tests)
* Crawl of every page for every role at 1366 px: HTTP 200, no console errors, no failed API calls (5xx/4xx).
* Crawl of every page at 320 px: no horizontal overflow (after fixes: grid `min-width:0`, `.table-wrap` positioning, two unwrapped tables).
* Accessibility scan of 25 screens: every visible input labelled, every button/link named, images have alt text, a heading in `main`. This is a scripted check, **not** a full WCAG audit with assistive technology.

## Defects found and fixed during testing
* Object spread froze Alpine getters (shared ANPR flow, `crudPage`) → descriptor-preserving `mixin()`.
* Nested `x-for` templates rendered nothing (customer price list) → flattened rows.
* KPI component wrote an unquoted `x-text` attribute → fixed quoting.
* Horizontal overflow at 320 px (grid items, absolutely positioned `sr-only` labels inside scroll boxes, two tables) → fixed.
* Focus ring colour (#14B8A6) was 2.5:1 on white → switched to #0F766E (5.5:1).
* Detector confidence was discarded by the backend → carried through as display-only data (G-16).

## Not tested
* Real IP/USB cameras, the camera agent against real streams, real gate/barrier hardware, card terminals or payment gateways (none are integrated).
* Recognition accuracy on real Sri Lankan plates (only synthetic images were used).
* Windows/Herd deployment of this branch, Safari/Firefox, screen readers (NVDA/JAWS/VoiceOver), and the 2-step verification flow end-to-end in a browser (its JSON endpoints are Fortify's own).
* Load/performance testing.
