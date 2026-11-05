# User journeys

How each role uses ParkFlow, step by step, with the backend action behind every step. ✅E2E marks steps driven by `tests/e2e/journeys.mjs`.

## 1. Sign in (all roles) ✅E2E
1. `/login` → email + password (+ "Keep me signed in"). Five failed attempts per minute → *Too many requests*.
2. Inactive or suspended accounts get a generic refusal; a correct 2-step account continues to `/two-factor-challenge`.
3. The server sends each role to its own dashboard (`/super-admin|admin|staff|auditor|customer/dashboard`). The role cannot be chosen by the client.
4. Session ends → any API call returns 401 → the browser goes to `/login?expired=1` ("Your session ended"). An expired CSRF token shows the *Session expired* page (419).

## 2. Staff — ANPR Check-In ✅E2E
| Step | What staff see | Backend |
|---|---|---|
| Capture | Tabs: **Upload / Test capture** (JPG/PNG ≤ 4 MB, optional camera/lane, progress bar), **Camera readings** (unused readings pushed by the camera agent, refreshed every 5 s), **Type plate manually** (reason required) | `POST /staff/anpr/recognize`, `GET /staff/anpr/events?direction=entry&unused=1`, `POST /staff/anpr/manual-review` |
| Verify | Plate crop, raw text, normalised plate, format, recognition + verification status, **OCR confidence** and **detector confidence** shown separately, other candidates, duplicate/used warnings | `GET /staff/anpr/events/{uuid}` (image via the audited image route) |
| Decide | **Confirm** (only when there is a readable plate), **Correct** (plate + reason) or **Reject** (reason). Explains that the decision is audited | `POST /staff/anpr/events/{uuid}/verify` |
| Details | Registered vs new vehicle, province-prefix suggestions (never auto-matched), "already parked" block, guaranteed-parking reservation pre-fill, category → allowed fuel types, zones with live free counts for that category, packages allowed in the zone, slot = auto-assigned by the server, optional customer name/phone | `POST /staff/anpr/entry/preview`, `GET /staff/availability?vehicle_category_id=` |
| Confirm | Final review + "I have checked the plate" box → **Confirm Check-In** (double-submit guarded) | `POST /staff/anpr/entry/confirm` (sends `confirm_plate`) |
| Done | Session reference, slot, package end, "Entry gate: not controlled", optional internal ticket print, *Next vehicle* | — |

Errors handled: engine unavailable (503 → manual identification linked to the failed reading), duplicate reading, reading already used, vehicle already parked, no free slot, verification required / plate mismatch (reloads the reading).

## 3. Staff — ANPR Check-Out with payment ✅E2E
1. Capture and verify exactly as Check-In (`direction=exit`). A fourth tab, **Scan internal ticket**, is a backup using `POST /staff/exits/preview|settle`.
2. **Bill & payment**: the server matches the session by the **exact** verified plate (`POST /staff/anpr/exit/preview`). No match → "Correct the plate"; no-plate manual readings → search parked vehicles and pick the session.
3. The bill (lines, overtime with minutes/rate/cap, penalties, tax, already paid, balance, overpaid) is the server quote, shown in LKR.
4. Safety parking: if `requires_exit_inspection`, the exit inspection form appears and settlement stays disabled until it is recorded (`POST /staff/sessions/{uuid}/inspections`).
5. Payment method list = backend payment methods. Cash: amount received + change hint. Card: reference required. Online: recorded as **pending**.
6. **Settle** → confirmation dialog → `POST /staff/anpr/exit/settle` with one **idempotency key per attempt** (reused on retry after a timeout; replaced after a 409 `idempotency_conflict`).
7. Result: *Exit authorised by the server* + receipt (print), payment status, change given, gate result:
   * driver `log` → **Gate Authorized — simulated (no gate hardware connected)**;
   * `failed` → **Gate Failed** + *Retry gate* (`POST /staff/sessions/{uuid}/gate/exit`);
   * gate-release mode → *Confirm vehicle has left* (`POST /staff/sessions/{uuid}/confirm-left`).
8. Online payment → `202 payment_pending`: the gate stays closed; *Payment received* / *Payment failed* (`POST /staff/payments/{id}/confirm|fail`).

## 4. Staff — other tasks
* **Parked vehicles** → session detail: live bill, payments (confirm/fail pending), penalties (apply; admins waive), inspections, gate history, prepayment, print/reissue ticket (lost ticket), report incident.
* **ANPR review queue**: pending/unused readings with filters; opens the reading and continues Check-In/Out.
* **Expiring & overstay**, **Availability**, **Vehicle register** (admins can edit category/fuel/owner), **Reservations** (renew; admins cancel with optional refund), **Safety tasks** (record with optional photo evidence), **Report incident** (with photo), **My shifts** (start / end shift).

## 5. Admin / Super Admin ✅E2E (system status, penalty type, settings dialog)
* Dashboard: live KPIs, occupancy, camera/engine status.
* **Users** (create with role limits, status, role), **Zones & slots** (capacity per category, slot map with status changes; occupied slots are read-only), **Vehicle categories**, **Packages & rates** (versioned rates; publish with confirmation), **Price calculator** (server quote), **Penalty types**, **Payment methods**, **Shifts & roster** (assign, approve leave, coverage, attendance, templates), **Cameras** (register → device token shown once; edit, disable, rotate token), **Refunds** (approve/reject/mark paid), **Incidents**, **Customer support**, **Settings** (typed editors, warnings on sensitive keys, history), **System status** (simulated vs connected integrations), **Roles & permissions** (read-only).
* Reports and the super admin's audit pages as below.

## 6. Auditor (read-only) ✅E2E
* Dashboard (7-day financial summary, occupancy), **Reports** (financial, payments ledger, session ledger, calendar, occupancy, inspections, staff performance, ANPR & cameras), **Audit trail**, **ANPR corrections**, **Recognition history**, **Roles & permissions**.
* Print and *Download CSV (this page)* only. No operational buttons; operational write APIs return 403 (checked by E2E). Auditors may record **audit findings** (their own notes).

## 7. Customer ✅E2E (parking info, add vehicle, blocked from staff pages)
* Register at `/register` → customer dashboard.
* **Active parking** (remaining time / overstay from the server), **My vehicles** (add / edit / remove-unlink), **Parking info & prices** (free spaces and published prices), **Reservations** (view), **History** → session bill, **Receipts** (printable / save as PDF), **Credit balance**, **Support** (send request, read reply), **Notifications**, **My account** (profile, password, 2-step).
* No online payment, booking or cancellation: the UI says so instead of offering it.
