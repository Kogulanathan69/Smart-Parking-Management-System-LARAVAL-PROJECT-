# Smart Parking Backend: Setup, Integration and Status

## 1. Environment setup (Windows + Laravel Herd)

1. Use PHP 8.4 or newer (Herd: PHP 8.5) and MySQL 8.4. The locked dependencies need PHP ≥ 8.4.
2. `composer install`
3. `copy .env.example .env`, then set `DB_DATABASE=smart_parking`, `DB_USERNAME` and `DB_PASSWORD`. **Never commit `.env`.**
4. `php artisan key:generate`. Skip this if `.env` already has an `APP_KEY`. Changing the key makes stored QR tokens and encrypted sessions unreadable.
5. `php artisan migrate`. Every new migration is additive. The users migration uses `hasColumn` guards, so existing data is kept.
6. Seed:
   * every environment: `php artisan db:seed --class=ReferenceDataSeeder` (vehicle types, zone types, payment methods, shifts; safe to re-run)
   * local only: `php artisan db:seed` also seeds the example zones A–E, packages, **example (not approved) prices** and demo users `admin|staff|auditor|customer@smart-parking.test`. Their password comes from `SEED_DEMO_PASSWORD`, or a random one is printed.
7. Create the first Super Admin: `php artisan parking:create-super-admin`. The password is prompted (min 12 characters, mixed case, number and symbol) and never passed as an argument. At most 2 active Super Admins are allowed.
8. Scheduler (safety inspections hourly, expiry reminders every 5 minutes): run `php artisan schedule:work` locally, or a cron job for `schedule:run` in production.
9. Tests: `php artisan test`. The MySQL race tests are separate: point `DB_*` at a **throwaway** database, then run `php artisan test tests/Concurrency`.

Production checklist: `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, a real mail driver for password resets, and a queue worker if notifications are later queued.

## 2. Architecture

```
routes/web.php       Fortify auth (auto) + role dashboards (DashboardController)
routes/api.php       /api/v1 JSON API, web session + CSRF, auth, active, role:…
app/Http/Requests    Form Request validation (one per write endpoint)
app/Http/Controllers/Api/{Customer,Staff,Admin,Report}   thin controllers
app/Services         business logic (EntryService, ExitService, PricingService, SlotAllocator,
                     PaymentService, QrTicketService, SafetyService, EvidenceService,
                     ShiftService, ZoneService, VehicleService, UserAccountService,
                     ReportService, SettingsService, AuditLogger)
app/Contracts        hardware/external boundaries (PlateRecognizer, TicketPrinter,
                     GateController, PaymentGateway); simulated drivers in Services/Integrations
app/Policies         record-level authorisation (IDOR protection)
app/Http/Resources   JSON shapes
config/parking.php   business configuration + defaults for runtime settings
```

**Authorisation** has 3 layers. (1) `role:` middleware, which runs *before* route-model binding. (2) Policies for record ownership: customers only ever see their own vehicles, sessions, receipts, complaints and evidence. (3) `UserAccountService` for the account-management matrix:

| Actor | Can create | Can suspend or change |
|---|---|---|
| Super Admin | admin, staff, auditor, customer | everyone except themselves. Another Super Admin can be suspended, but never the last active one. |
| Admin | staff, customer | staff, customer |
| Others | – | – |

Super Admins can only be created through the CLI. Account limits are 2 Super Admins and 2 Admins (`config/parking.php`).

**Double-allocation protection** has 3 levels: `SELECT … FOR UPDATE` on the slot, a compare-and-set update (`status` must still be `available`), and unique indexes on `parking_sessions.open_slot_key` and `open_vehicle_key`. The last two are filled only while a session is open. This was verified with real concurrent processes on MySQL: 10 racers for 3 slots gave exactly 3 entries, and 6 racers for 1 plate gave exactly 1.

**Financial safety:** money is stored as integers. Exit settlement runs in one transaction with a row lock on the session. Payments have a unique `idempotency_key`. A used QR ticket can't be used again. A pending or failed payment never authorises the gate.

**No retroactive pricing:** rates are append-only versions (`package_rates.version`, `effective_from/to`). At entry the rate values **and** the pricing policy are copied into `parking_sessions.pricing_snapshot`, and exit charges are always calculated from that snapshot.

## 3. Pricing rules as implemented

| Package | Billing |
|---|---|
| Short-time (`hourly`) | per started block (default 60 min), minimum 1 block. Optional cap per 24 h (`overtime_daily_cap_minor`). |
| Daily / Weekly / Monthly (`fixed`) | Fixed price for 1440 / 10080 / 43200 minutes (weekly = 7 days, monthly = 30 days) |
| Night (`night`) | Fixed price until the next `pricing.night_end_time` (default 06:00 Asia/Colombo) |
| Safety (`addon`) | Added automatically in safety-type zones: price × started days, plus safety overtime per block |

Overtime: grace defaults to 15 minutes (set per rate). After grace, overtime is charged per started block × `overtime_hourly_minor`. Each overtime day is capped at `overtime_daily_cap_minor` (set it to the daily rate, BRD §6), and later days are charged separately. Early exit (weekly/monthly with `allows_early_exit_credit`): price × used days / package days, rounded half-up, with a minimum number of days. If prepaid, the unused amount becomes credit or a refund.

## 4. Runtime settings (`PUT /api/v1/admin/settings/{key}`)

| Key | Default | Who | Status |
|---|---|---|---|
| `pricing.grace_minutes` | 15 | Admin, SA | BRD |
| `pricing.billing_block_minutes` | 60 | Admin, SA | **Pending decision** (rounding) |
| `pricing.overtime_starts_from` | `package_end` | Admin, SA | **Pending** (`grace_end` alternative) |
| `pricing.night_end_time` | `06:00` | Admin, SA | **Pending** |
| `pricing.early_exit_method` | `prorate_daily` | Admin, SA | **Pending** (`none` alternative) |
| `pricing.early_exit_minimum_days` | 1 | Admin, SA | **Pending** |
| `pricing.tax_basis_points` | 0 | Admin, SA | **Pending** (taxes) |
| `refund.default_resolution` | `credit` | Admin, SA | **Pending** |
| `refund.auto_approve_limit_minor` | 0 (all refunds need approval) | Admin, SA | **Pending** |
| `refund.credit_expiry_days` | null (no expiry) | Admin, SA | **Pending** |
| `safety.inspections_per_day` | 2 | Admin, SA | BRD |
| `safety.require_exit_inspection` | true | Admin, SA | BRD §8 "safety checks" |
| `exit.release_slot_on` | `settlement` | SA only | `gate` = slot freed only after `confirm-left` |
| `notifications.notify_admins_on_damage` | true | Admin, SA | BRD §9 |
| `attendance.late_after_minutes` | 10 | Admin, SA | **Pending** |
| `reminders.enabled` | true | Admin, SA | Requirement 9 |
| `reminders.before_expiry_minutes` | `[60]` (array, 1 to 5 values) | Admin, SA | **Pending** (thresholds) |
| `reminders.notify_on_expiry` | true | Admin, SA | Requirement 9 |

## 5. Frontend integration guide (Blade + Bootstrap + JS)

* Make sure the layout contains `<meta name="csrf-token" content="{{ csrf_token() }}">`. With `fetch`, send `X-CSRF-TOKEN` (from the meta tag) or `X-XSRF-TOKEN` (from the cookie), plus `Accept: application/json`, and `credentials: 'same-origin'`.
* Fortify views must be created at `resources/views/auth/{login,register,forgot-password,reset-password,confirm-password,two-factor-challenge,verify-email}.blade.php`. Until a view exists, its GET route returns `501` JSON. Forms post to `/login`, `/register` and so on. A non-JSON login is redirected to the role dashboard.
* The dashboard routes currently return JSON summaries. The frontend can render a Blade page and fetch `/…/dashboard` with `Accept: application/json`, or the routes can later be switched to return views that use the same data.
* Money: show `amount_minor / 100` with 2 decimals, or use the formatted string fields. Never do money arithmetic in floating point. Totals come from the server.
* Gate screen flow: lookup (`GET /staff/vehicles/lookup?plate=`), then availability (`GET /staff/availability?vehicle_category_id=`), then `POST /staff/entries`, then print `ticket.qr_svg`. Exit: scan into an input, `POST /staff/exits/preview`, show `quote.lines`, then `POST /staff/exits/settle` with a UUID `idempotency_key` created when the exit screen opens.
* Error display: show `message`. Branch on `error_code` (for example, on `no_slot_available` offer another zone from availability).

## 6. Module completion report

All modules below were verified with automated tests: **113 feature tests / 536 assertions passing on both SQLite and MySQL 8.0**, plus 2 multi-process MySQL race tests (passed 3 times in a row). Pint formatting and `php -l` are clean.

| # | Module | Status | Tests |
|---|---|---|---|
| 1 | Auth & authorisation (Fortify, customer-only signup, role redirects, suspension, rate limit, CLI Super Admin, account limits, last-Super-Admin guard) | Done | AuthenticationTest, RolePermissionTest |
| 2 | Database architecture (29 new tables, FKs, unique and lookup indexes, additive migrations, rollback verified on MySQL) | Done | all |
| 3 | Zones & slots (configurable zone types, category restrictions, capacity changes, live occupancy, slot states) | Done | ZoneSlotTest |
| 4 | Vehicles (plate normalisation, plate-less references, customer ownership, history) | Done | EntryTest, CustomerPortalTest |
| 5 | Packages & pricing (versioned rates, snapshot, grace, overtime cap, night, weekly/monthly, early exit, safety, preview) | Done. Business values pending. | PricingTest |
| 6 | Entry & QR tickets (allocation, duplicate prevention, hashed and encrypted tokens, print and reissue) | Done | EntryTest, Concurrency |
| 7 | Exit & payments (preview, settlement, idempotency, cash/card/online-pending, prepay, credit/refund, receipts, gate hook, release-on-gate) | Done | ExitPaymentTest |
| 8 | Safety parking (entry/routine/exit inspections, frequency, missed marking, incidents, damage alerts, private evidence) | Done | SafetyTest |
| 9 | Staff & shifts (shifts, assignments, cross-midnight, check-in/out, late/left-early, leave + replacement, coverage gaps, performance) | Done | ShiftAttendanceTest |
| 10 | Reports & dashboards (overview, occupancy, financial, staff performance, sessions, payments, inspections, 5 dashboards) | Done | several |
| 11 | Audit & notifications (immutable audit log with secrets redacted, auditor findings, database notifications with delivery log, complaints) | Done | AuditorAndAuditTest, CustomerPortalTest |
| 12 | Auditor read-only: every POST/PUT/PATCH/DELETE API route is checked automatically for 403 | Done | AuditorAndAuditTest |

### Known limitations / pending work

* **BRD §19 decisions** are behind the settings listed in §4. They need business approval, not code changes. Example prices and slot counts exist in the local seeder only.
* Partial payments are not supported. At exit the balance must be paid in one payment. Discounts and taxes are 0 by default.
* SMS/email channels are not configured, so notifications use the database channel only. Add `mail` or an SMS channel in each notification's `via()` once providers are chosen.
* Manual confirmation of "online" payments is **disabled** (PR #1) until a verified payment gateway exists, so an unverified payment can never be marked paid. A pending online payment can only be marked failed, and the exit is then settled another way.
* Not done: data retention / photo purge jobs (policy pending), complaint escalation rules (pending), customer credit **redemption** at exit (credits are recorded and shown, but not yet applied automatically).
* Auth pages (Blade) are owned by the frontend branch.

## 7. Hardware / external integrations still to build

| Interface | Current driver | Future work |
|---|---|---|
| `App\Contracts\PlateRecognizer` | `manual` (staff types the plate) | Python AI OCR service: send the camera frame, return `{plate, confidence}`. Staff still confirms. |
| `App\Contracts\TicketPrinter` | `browser` (`window.print()` of `qr_svg`) | Thermal/ESC-POS QR sticker printer |
| QR scanner | Keyboard-wedge scanner typing into the exit input | none needed (USB HID) |
| `App\Contracts\GateController` | `log` (simulated) | Gate relay/controller. Called only after confirmed settlement. Wire `confirm-left` to the gate loop sensor when `exit.release_slot_on = gate`. |
| `App\Contracts\PaymentGateway` | `none` (online = pending + manual confirm) | Card terminal / online gateway with webhook calling `PaymentService::resolvePending` + `ExitService::completeAfterPayment` |
| CCTV | – | Out of scope for the backend. Evidence upload already accepts photos. |
| SMS / email | – | Notification channels |

To add a driver, implement the interface, register it in `AppServiceProvider::DRIVERS`, and select it with `PARKING_*_DRIVER` in `.env`.


## 8. Release: vehicle fuel types, penalties, bills, reminders, calendar and filters

### Requirement coverage

| # | Requirement | Before | Now |
|---|---|---|---|
| 1 | Vehicle categories | Partial | **Done.** Admin create/update/activate/deactivate with conflict checks. Inactive categories are refused for new vehicles and entries. The category is recorded on each session. |
| 2 | Fuel type | Missing | **Done.** `vehicles.fuel_type`, separate from category and validated per category. Old records backfilled: non-motorised → `not_applicable`, others → `other`. |
| 3 | Vehicle CRUD | Partial | **Done.** Plate search, category/fuel filters, staff register, admin owner linking, history kept on unlink, category change blocked while parked |
| 4 | Entry / exit | Implemented | Fuel captured at entry. Lost-ticket path (penalty + reissue). Existing locks and idempotency unchanged. |
| 5 | Duration & final bill | Partial | **Done.** Bill endpoint plus a bill embedded in receipts, with every field the requirement lists |
| 6 | Payment methods | Mostly done | A repeated exit idempotency key with a different method is rejected. **ENP: not implemented, needs clarification.** |
| 7 | Penalties | Missing | **Done.** Catalogue, apply, admin waiver, duplicate guard, shown on bill and reports, audited, never automatic |
| 8 | Calendar history | Partial | **Done.** `/reports/calendar` plus `date`/`date_field` filters, in Sri Lanka days |
| 9 | Remaining time / reminders | Missing | **Done.** Session `time` status, `/staff/sessions/expiring`, idempotent in-app reminders. No SMS/WhatsApp. |
| 10 | Four filters | Partial | **Done** for category, fuel, status and date range, plus plate, zone, package and payment status, all combinable. The "primary four" are configurable pending confirmation. |
| 11 | Security / integrity | – | Unique keys for penalties and reminders. Customer filters are always scoped to the customer. Category/fuel history is fixed at entry. All new write routes are covered by the auditor-forbidden route sweep. |

### Database changes (additive, backward compatible)

| Migration | Change |
|---|---|
| `2026_11_02_000001` | `vehicle_categories.is_motorized` (bicycle = false). `vehicles.fuel_type` plus index `(fuel_type, vehicle_category_id)`, backfilled. `parking_sessions.vehicle_category_id` (FK) and `fuel_type`, backfilled from the vehicle, plus indexes `(vehicle_category_id, entry_at)`, `(fuel_type, entry_at)`, `(status, entry_at)`, `(status, package_ends_at)`. |
| `2026_11_02_000002` | `penalty_types` (unique `code`). `session_penalties` with the amount copied at application, applied/waived audit columns, and a unique `active_key` (`session:type` while applied, `NULL` once waived). |
| `2026_11_02_000003` | `session_reminders` with a unique key `(session, kind, threshold)` |

Rollback and re-apply were verified on MySQL 8.0 and SQLite. A run against legacy-shaped data (vehicles and sessions without the new columns) backfilled correctly.

### Open questions for the stakeholder

1. **ENP bill method.** Its meaning isn't confirmed (e-payment? an external provider? a bill format?). Nothing ENP-specific has been built.
2. **"Four filter functions".** The current proposal is vehicle category, fuel type, status and date range. Please confirm, or name the four. Changing them is a config edit only.
3. **Fuel type of old records.** Motorised vehicles were backfilled as `other`. Should staff be prompted to correct it at the next entry?
4. **Penalty amounts and rules.** The seeded amounts are examples. Should any penalty ever be applied automatically, for example after X hours of overtime? Currently none are.
5. **Reminder thresholds and channels.** The default is 60 minutes before expiry plus a notice at expiry. SMS/WhatsApp providers are still to be chosen.
6. **Tax on penalties.** Currently not taxed; tax is 0 overall until approved.

## 9. Weekly & monthly guaranteed parking

**Schema.** `parking_reservations` (existing migration `2026_11_01_074443`) plus the new forward-only migration `2026_11_03_000001_add_guaranteed_parking_support`:

* `parking_packages.guarantees_slot`, backfilled ON for fixed packages of 7 days or more
* `parking_sessions.parking_reservation_id`
* `reservation_periods`: one row per paid period, recording which session's bill carries it. This is how the base price is charged once per period and never per visit.

**Slot transitions.** Every transition uses a compare-and-set update inside a transaction:

| Event | Slot |
|---|---|
| Normal entry / exit | available → occupied → available |
| Guaranteed first entry | available → occupied |
| Temporary exit (after physical departure) | occupied → reserved |
| Re-entry | reserved → occupied |
| Expiry or cancellation while vacant | reserved → available |
| Expiry or cancellation while inside | stays occupied; released at departure |

Reserved slots held by an active reservation can't be changed by admins (`slot_reserved_for_customer`). Capacity can't be reduced below reserved plus occupied slots. A vehicle's category can't change while it holds a reservation.

**Concurrency.** Active ownership is guaranteed by the unique `active_vehicle_key` / `active_slot_key` (cleared when a reservation ends), together with the existing `open_*_key` indexes and the vehicle row lock.

* Reservation rows are locked by primary key only after they're known to exist. This avoids MySQL gap-lock deadlocks, which the race tests initially exposed.
* Verified with real parallel processes on MySQL 8.0, three times in a row: 8 first entries for 3 slots gave exactly 3 reservations, 5 parallel re-entries gave exactly 1, and expiry racing re-entry at the expiry instant never left a slot stranded.

**Zone layout.** Physical zones currently follow package types (Zone C weekly, Zone D monthly). No schema change is needed to lay zones out by vehicle category instead:

* Create category zones with the existing `zone_types`, attach the allowed vehicle categories, and allow the packages for those zone types through `package_zone_type`.
* Guaranteed parking already respects zone categories, slot categories and capacity.
* Nothing was re-seeded or restructured.

**Policy decisions needing approval** (current safe behaviour in brackets):

1. Renewal price and timing. [Current rate at renewal; starts at the current expiry; billed on the current or next visit.]
2. Refunds on cancellation. [Never automatic; an admin enters an amount, capped at the paid package amount, which needs approval.]
3. Re-entry with a different package or zone. [Refused with a clear error; staff must use the reservation's package and zone.]
4. Overtime rate for re-entry visits after expiry. [The rate bought with the reservation, measured from the current expiry.]
5. Safety add-on on reservation visits. [Charged per visit, per started day, as today.]
6. Expiry reminders after a renewal. [Reminders reset only for the visit in progress; there are no reminders while the vehicle is away. Customers can see the expiry in `/customer/reservations`.]
7. Walk-in reservations without a customer account. [Allowed; ownership is by vehicle. They don't appear in any customer portal.]
