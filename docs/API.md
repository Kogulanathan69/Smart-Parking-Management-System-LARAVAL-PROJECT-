# Smart Parking API (v1)

Base URL: `/api/v1`. JSON only. All amounts are **integer minor units (LKR cents)**: `150000` = LKR 1,500.00. Fields ending in `_minor` are integers. A formatted string is sometimes included too (e.g. `"total_charge": "1,600.00"`). Timestamps are ISO-8601 in UTC. `*_local` fields are in Asia/Colombo time.

## Authentication

The API uses Laravel Fortify **session cookies**, the same login as the Blade frontend. No API tokens.

| Method | Path | Purpose |
|---|---|---|
| POST | `/login` | `{email, password, remember?}` |
| POST | `/logout` | |
| POST | `/register` | Public sign-up. Always creates an active **customer**, and any `role` field is ignored. `{name, email, phone?, password, password_confirmation}` |
| POST | `/forgot-password` | `{email}` |
| POST | `/reset-password` | `{token, email, password, password_confirmation}` |
| PUT | `/user/profile-information` | `{name, email, phone?}` |
| PUT | `/user/password` | `{current_password, password, password_confirmation}` |
| GET | `/dashboard` | Redirects to the role dashboard |
| GET | `/{super-admin,admin,staff,auditor,customer}/dashboard` | Role dashboard summary JSON |

Two-factor authentication and passkey routes from Fortify stay enabled, unchanged.

Every state-changing request needs the CSRF header. (Sanctum is not installed. Any page rendered by Laravel sets the `XSRF-TOKEN` cookie.) With `fetch`, read the `XSRF-TOKEN` cookie and send it as `X-XSRF-TOKEN`. Axios does this automatically. Also send `Accept: application/json`.

**Login response** (`200`, JSON clients):

```json
{
  "user": {"id": 1, "name": "…", "email": "…", "role": "staff", "status": "active", "dashboard": "/staff/dashboard", "two_factor_enabled": false},
  "redirect": "/staff/dashboard"
}
```

Inactive or suspended accounts get `422` with `errors.email`. Five failed attempts per minute (per email + IP) return `429`. If an account is suspended while the user is logged in, the user is logged out on their next request: `403 {"error_code": "account_inactive"}`.

## Error format

| Status | Body |
|---|---|
| 401 | `{"message": "Unauthenticated."}` |
| 403 | `{"message": "…"}`. Wrong role or policy. Other roles always get 403, never 404, so record IDs can't be enumerated. |
| 404 | `{"message": "…"}` or `{"error_code": "invalid_ticket"}` |
| 409 | Business conflict, e.g. `{"message": "…", "error_code": "duplicate_active_entry"}` |
| 422 | Validation `{"message": "…", "errors": {"field": ["…"]}}` or business rule `{"message": "…", "error_code": "…"}` |
| 429 | Rate limited |

Business `error_code` values: `no_slot_available`, `duplicate_active_entry`, `invalid_ticket`, `ticket_already_used`, `payment_pending`, `payment_method_required`, `insufficient_tender`, `reference_required`, `exit_inspection_required`, `category_not_allowed`, `package_zone_mismatch`, `plate_required`, `category_mismatch`, `rate_not_configured`, `safety_rate_not_configured`, `capacity_below_occupied`, `slot_locked`, `account_limit_reached`, `last_super_admin`, `forbidden_role`, `forbidden_account`, `vehicle_unavailable`, `outside_shift_window`, `already_checked_in`, `duplicate_assignment`, `invalid_staff`, `inspection_completed`, `account_inactive`, and new in this release: `fuel_type_not_allowed`, `category_inactive`, `vehicle_in_active_session`, `category_change_conflict`, `duplicate_penalty`, `penalty_type_inactive`, `penalty_already_waived`, `invalid_session_state`, `idempotency_conflict`, `range_too_large`.

## Endpoints

Endpoints marked **(new)** were added in the vehicle/fuel/penalty/reminder release. Existing endpoints that now accept extra filters are listed in that section.

### Account

| Method | Path | Roles |
|---|---|---|
| GET | `/api/v1/me` | any signed-in role |
| GET | `/api/v1/reference-data` | any signed-in role |

### Notifications

| Method | Path | Roles |
|---|---|---|
| GET | `/api/v1/notifications` | any signed-in role |
| POST | `/api/v1/notifications/read-all` | any signed-in role |
| POST | `/api/v1/notifications/{id}/read` | any signed-in role |

### Evidence

| Method | Path | Roles |
|---|---|---|
| GET | `/api/v1/evidence/{evidence}` | any signed-in role |

### Customer

| Method | Path | Roles |
|---|---|---|
| GET | `/api/v1/customer/complaints` | customer |
| POST | `/api/v1/customer/complaints` | customer |
| GET | `/api/v1/customer/complaints/{complaint}` | customer |
| GET | `/api/v1/customer/credits` | customer |
| GET | `/api/v1/customer/receipts` | customer |
| GET | `/api/v1/customer/receipts/{receipt:number}` | customer |
| GET | `/api/v1/customer/sessions` | customer |
| GET | `/api/v1/customer/sessions/{session}` | customer |
| GET | `/api/v1/customer/sessions/{session}/bill` **(new)** | customer |
| GET | `/api/v1/customer/vehicles` | customer |
| POST | `/api/v1/customer/vehicles` | customer |
| GET | `/api/v1/customer/vehicles/{vehicle}` | customer |
| PUT|PATCH | `/api/v1/customer/vehicles/{vehicle}` | customer |
| DELETE | `/api/v1/customer/vehicles/{vehicle}` | customer |

### Staff operations

| Method | Path | Roles |
|---|---|---|
| GET | `/api/v1/staff/availability` | staff, admin, super_admin |
| POST | `/api/v1/staff/entries` | staff, admin, super_admin |
| POST | `/api/v1/staff/evidence` | staff, admin, super_admin |
| POST | `/api/v1/staff/exits/preview` | staff, admin, super_admin |
| POST | `/api/v1/staff/exits/settle` | staff, admin, super_admin |
| POST | `/api/v1/staff/incidents` | staff, admin, super_admin |
| GET | `/api/v1/staff/inspections/due` | staff, admin, super_admin |
| POST | `/api/v1/staff/payments/{payment}/confirm` | staff, admin, super_admin |
| POST | `/api/v1/staff/payments/{payment}/fail` | staff, admin, super_admin |
| GET | `/api/v1/staff/penalty-types` **(new)** | staff, admin, super_admin |
| GET | `/api/v1/staff/sessions` | staff, admin, super_admin |
| GET | `/api/v1/staff/sessions/expiring` **(new)** | staff, admin, super_admin |
| GET | `/api/v1/staff/sessions/{session}` | staff, admin, super_admin |
| GET | `/api/v1/staff/sessions/{session}/bill` **(new)** | staff, admin, super_admin |
| POST | `/api/v1/staff/sessions/{session}/confirm-left` | staff, admin, super_admin |
| GET | `/api/v1/staff/sessions/{session}/inspections` | staff, admin, super_admin |
| POST | `/api/v1/staff/sessions/{session}/inspections` | staff, admin, super_admin |
| POST | `/api/v1/staff/sessions/{session}/payments` | staff, admin, super_admin |
| GET | `/api/v1/staff/sessions/{session}/penalties` **(new)** | staff, admin, super_admin |
| POST | `/api/v1/staff/sessions/{session}/penalties` **(new)** | staff, admin, super_admin |
| GET | `/api/v1/staff/sessions/{session}/ticket` | staff, admin, super_admin |
| POST | `/api/v1/staff/sessions/{session}/ticket/reissue` | staff, admin, super_admin |
| GET | `/api/v1/staff/shifts/mine` | staff |
| POST | `/api/v1/staff/shifts/{assignment}/check-in` | staff |
| POST | `/api/v1/staff/shifts/{assignment}/check-out` | staff |
| GET | `/api/v1/staff/vehicles` **(new)** | staff, admin, super_admin |
| GET | `/api/v1/staff/vehicles/lookup` **(new)** | staff, admin, super_admin |
| GET | `/api/v1/staff/vehicles/{vehicle}` **(new)** | staff, admin, super_admin |

### Admin & Super Admin

| Method | Path | Roles |
|---|---|---|
| GET | `/api/v1/admin/attendance` | admin, super_admin |
| GET | `/api/v1/admin/complaints` | admin, super_admin |
| PATCH | `/api/v1/admin/complaints/{complaint}` | admin, super_admin |
| GET | `/api/v1/admin/incidents` | admin, super_admin |
| PATCH | `/api/v1/admin/incidents/{incident}` | admin, super_admin |
| GET | `/api/v1/admin/packages` | admin, super_admin |
| POST | `/api/v1/admin/packages` | admin, super_admin |
| GET | `/api/v1/admin/packages/{package}` | admin, super_admin |
| PUT|PATCH | `/api/v1/admin/packages/{package}` | admin, super_admin |
| GET | `/api/v1/admin/packages/{package}/rates` | admin, super_admin |
| POST | `/api/v1/admin/packages/{package}/rates` | admin, super_admin |
| GET | `/api/v1/admin/payment-methods` | admin, super_admin |
| POST | `/api/v1/admin/payment-methods` | admin, super_admin |
| POST | `/api/v1/admin/penalties/{penalty}/waive` **(new)** | admin, super_admin |
| GET | `/api/v1/admin/penalty-types` **(new)** | admin, super_admin |
| POST | `/api/v1/admin/penalty-types` **(new)** | admin, super_admin |
| PATCH | `/api/v1/admin/penalty-types/{type}` **(new)** | admin, super_admin |
| POST | `/api/v1/admin/pricing/preview` | admin, super_admin |
| GET | `/api/v1/admin/refunds` | admin, super_admin |
| POST | `/api/v1/admin/refunds/{refund}/decision` | admin, super_admin |
| GET | `/api/v1/admin/settings` | admin, super_admin |
| GET | `/api/v1/admin/settings/history` | admin, super_admin |
| PUT | `/api/v1/admin/settings/{key}` | admin, super_admin |
| GET | `/api/v1/admin/shift-assignments` | admin, super_admin |
| POST | `/api/v1/admin/shift-assignments` | admin, super_admin |
| POST | `/api/v1/admin/shift-assignments/{assignment}/leave` | admin, super_admin |
| GET | `/api/v1/admin/shift-coverage` | admin, super_admin |
| GET | `/api/v1/admin/shifts` | admin, super_admin |
| POST | `/api/v1/admin/shifts` | admin, super_admin |
| PUT | `/api/v1/admin/shifts/{shift}` | admin, super_admin |
| PATCH | `/api/v1/admin/slots/{slot}/status` | admin, super_admin |
| GET | `/api/v1/admin/users` | admin, super_admin |
| POST | `/api/v1/admin/users` | admin, super_admin |
| GET | `/api/v1/admin/users/{user}` | admin, super_admin |
| PATCH | `/api/v1/admin/users/{user}/role` | admin, super_admin |
| PATCH | `/api/v1/admin/users/{user}/status` | admin, super_admin |
| GET | `/api/v1/admin/vehicle-categories` **(new)** | admin, super_admin |
| POST | `/api/v1/admin/vehicle-categories` **(new)** | admin, super_admin |
| PATCH | `/api/v1/admin/vehicle-categories/{category}` **(new)** | admin, super_admin |
| PATCH | `/api/v1/admin/vehicles/{vehicle}` **(new)** | admin, super_admin |
| GET | `/api/v1/admin/zones` | admin, super_admin |
| POST | `/api/v1/admin/zones` | admin, super_admin |
| GET | `/api/v1/admin/zones/{zone}` | admin, super_admin |
| PUT|PATCH | `/api/v1/admin/zones/{zone}` | admin, super_admin |
| PUT | `/api/v1/admin/zones/{zone}/capacity` | admin, super_admin |
| GET | `/api/v1/admin/zones/{zone}/slots` | admin, super_admin |

### Reports (read-only)

| Method | Path | Roles |
|---|---|---|
| GET | `/api/v1/reports/calendar` **(new)** | admin, super_admin, auditor |
| GET | `/api/v1/reports/financial` | admin, super_admin, auditor |
| GET | `/api/v1/reports/inspections` | admin, super_admin, auditor |
| GET | `/api/v1/reports/occupancy` | admin, super_admin, auditor |
| GET | `/api/v1/reports/overview` | admin, super_admin, auditor |
| GET | `/api/v1/reports/payments` | admin, super_admin, auditor |
| GET | `/api/v1/reports/sessions` | admin, super_admin, auditor |
| GET | `/api/v1/reports/staff-performance` | admin, super_admin, auditor |

### Audit

| Method | Path | Roles |
|---|---|---|
| GET | `/api/v1/audit/findings` | super_admin, auditor |
| POST | `/api/v1/audit/findings` | auditor |
| GET | `/api/v1/audit/logs` | super_admin, auditor |

## Key request/response examples

### Vehicle entry: `POST /api/v1/staff/entries`

```json
{
  "plate_number": "WP CAB-1234",
  "vehicle_category_id": 3,
  "parking_zone_id": 2,
  "parking_package_id": 2,
  "customer_name": "Kamal",
  "customer_phone": "0771234567"
}
```

`plate_number` may be omitted for categories that don't need a plate (bicycle). The vehicle then gets a reference like `BICY-7KQ2MX`. `parking_slot_id` (optional) asks for a specific slot. `customer_id` links a registered customer. If `customer_phone` matches a registered customer, or the vehicle has an owner, the session is linked to that customer automatically.

`201`:

```json
{
  "data": {
    "uuid": "0d107c3b-cae1-4b33-98c0-6202072fea22",
    "reference": "PS-261031-D3E79X",
    "status": "active",
    "vehicle": {"id": 1, "identifier": "WPCAB1234", "plate_number": "WP CAB-1234", "vehicle_category": "car"},
    "zone": {"id": 2, "code": "B", "name": "Zone B – Daily"},
    "slot": {"id": 40, "code": "B-CAR-001"},
    "package": {"id": 2, "code": "daily", "name": "Daily (24 hours)"},
    "has_safety": false,
    "entry_at": "2026-10-31T17:49:07+00:00",
    "package_ends_at": "2026-11-01T17:49:07+00:00",
    "rate_version": 1
  },
  "ticket": {
    "qr_payload": "SPT1-c7d0a2d1f92d9ba52bd9e5143cb2fbc4a81c531a13b63615",
    "qr_svg": "<svg …>",
    "session_reference": "PS-261031-D3E79X",
    "vehicle": "WPCAB1234", "zone": "B", "slot": "B-CAR-001",
    "package": "Daily (24 hours)",
    "entry_at_local": "2026-10-31 23:19",
    "package_ends_at_local": "2026-11-01 23:19",
    "print_count": 1
  }
}
```

The QR payload holds only a random 192-bit token, with no plate or personal data. Embed `qr_svg` in a print-only HTML block and call `window.print()`.

### Exit preview: `POST /api/v1/staff/exits/preview`

`{"qr_payload": "SPT1-…"}` (the scanner types this into a focused input). Response `200`:

```json
{
  "quote": {
    "currency": "LKR",
    "duration_minutes": 1500,
    "package_ends_at": "2026-11-01T17:49:07+00:00",
    "lines": [
      {"code": "package", "label": "Package: daily", "amount_minor": 150000},
      {"code": "overtime", "label": "Overtime", "amount_minor": 10000, "detail": {"minutes": 60, "hourly_minor": 10000, "daily_cap_minor": 150000}}
    ],
    "subtotal_minor": 160000, "tax_minor": 0, "total_minor": 160000,
    "paid_minor": 0, "balance_minor": 160000, "overpaid_minor": 0,
    "early_exit_unused_minor": 0,
    "requires_exit_inspection": false
  }
}
```

### Exit settlement: `POST /api/v1/staff/exits/settle`

```json
{
  "qr_payload": "SPT1-…",
  "idempotency_key": "b1f0c3c2-6a55-4c1e-9e2b-2d9a4f1d7a10",
  "payment_method": "cash",
  "tendered_minor": 200000,
  "overpayment_resolution": "credit"
}
```

* Generate **one `idempotency_key` (UUID) per exit attempt** in the browser, and reuse it if the request is retried after a network error. A repeated key never charges twice; it replays the result with `"replayed": true`.
* `payment_method` can be `cash`, `card` (needs `external_reference`) or `online`. It can be omitted if the session is already fully prepaid.
* `200` with `"status": "completed", "exit_authorized": true` returns `session`, `payment`, `receipt` and `gate`.
* `202` with `"status": "payment_pending"` means an online payment is waiting for confirmation. The exit is **not** authorised. Then call `POST /staff/payments/{id}/confirm` with `{external_reference}` or `/fail` with `{reason}`.
* Scanning a used ticket again returns `409 ticket_already_used`.

### Prepayment: `POST /api/v1/staff/sessions/{uuid}/payments`

`{"idempotency_key": "…", "payment_method": "cash", "amount_minor": 900000}`. At exit, any unused prepaid amount becomes customer credit or a refund (BRD §7).

### Change zone capacity: `PUT /api/v1/admin/zones/{id}/capacity`

`{"vehicle_category_id": 3, "capacity": 12}` returns `{"data": {"vehicle_category": "car", "total": 12, "available": 11, "occupied": 1, "reserved": 0, "unavailable": 0}}`. Capacity can't go below occupied plus reserved slots (`422 capacity_below_occupied`).

### Publish a new price: `POST /api/v1/admin/packages/{id}/rates`

`{"vehicle_category_id": 3, "price_minor": 160000, "overtime_hourly_minor": 10000, "overtime_daily_cap_minor": 160000, "grace_minutes": 15, "effective_from": null}` creates version N+1. Existing sessions keep the rate snapshot they were given at entry.

### Pricing preview: `POST /api/v1/admin/pricing/preview`

`{"parking_package_id": 2, "vehicle_category_id": 3, "entry_at": "2026-11-01 08:00", "exit_at": "2026-11-02 08:16", "with_safety": false}` returns `{"quote": {…, "total_minor": 160000}}`. Times are read as Asia/Colombo local time.

### Settings: `PUT /api/v1/admin/settings/{key}`

`{"value": 20}`. See the settings table in `BACKEND_GUIDE.md`. Every change is versioned (`GET /admin/settings/history`) and audited.

### Evidence upload: `POST /api/v1/staff/evidence` (multipart)

Fields `attachable_type` (`inspection` or `incident`), `attachable_id`, `file` (jpg/png/webp, max 5 MB). The file is stored privately. Download it through `GET /api/v1/evidence/{uuid}`, which checks authorisation.

### Pagination

List endpoints return Laravel's standard paginated resource format: `data`, `links`, `meta`. Use `?page=N`.

---

## Vehicle, fuel type, penalty, bill, filter and reminder APIs

### Fuel types and vehicle categories

Fuel type and vehicle category are **separate fields**.

| Fuel type value | Label |
|---|---|
| `petrol` | Petrol |
| `diesel` | Diesel |
| `electric` | Electric |
| `hybrid` | Hybrid |
| `other` | Other |
| `not_applicable` | Not applicable. This is required for non-motorised categories (`is_motorized: false`, e.g. bicycle) and not allowed for motorised ones. |

* `GET /api/v1/reference-data` now also returns `fuel_types`, and for each category `is_motorized` and `allowed_fuel_types`. It also returns `session_filters`, described below.
* `fuel_type` is **optional** on `POST /customer/vehicles` and `POST /staff/entries`, so existing clients keep working. If it is left out, it defaults to `other` (motorised) or `not_applicable`. The frontend should still ask for it.
* At the gate, a `fuel_type` sent for an existing vehicle corrects the stored value, and the correction is audited.
* Each session records the category and fuel type **at entry** (`vehicle_category`, `fuel_type` in the session resource). Editing the vehicle later never changes past sessions or bills.

**Customer: register a vehicle.** `POST /api/v1/customer/vehicles`

```json
{"plate_number": "WP CAB-1234", "vehicle_category_id": 3, "fuel_type": "diesel", "make": "Toyota"}
```

Returns `201`:

```json
{"data": {"id": 1, "identifier": "WPCAB1234", "plate_number": "WP CAB-1234", "is_reference": false,
  "vehicle_category": "car", "vehicle_category_id": 3, "fuel_type": "diesel", "fuel_type_label": "Diesel",
  "make": "Toyota", "model": null, "color": null, "created_at": "2026-11-03T03:30:00+00:00"}}
```

**Other vehicle endpoints:**

* `GET /customer/vehicles?q=cab&vehicle_category_id=3&fuel_type=diesel` lists the customer's own vehicles only.
* `PATCH /customer/vehicles/{id}` accepts `vehicle_category_id`, `fuel_type`, `make`, `model` and `color`. A category change while the vehicle is parked returns `409 vehicle_in_active_session`. An inactive category returns `422`.
* Staff vehicle register: `GET /staff/vehicles?q=&vehicle_category_id=&fuel_type=&owner_id=&per_page=` (paginated). `GET /staff/vehicles/{id}` returns the vehicle, owner and last 20 sessions.
* Admin: `PATCH /admin/vehicles/{id}` accepts the same fields plus `owner_id`, which must be a customer or `null`.
* Removing a vehicle from an account (`DELETE /customer/vehicles/{id}`) only unlinks it. Its parking history is kept.

**Vehicle categories (Admin / Super Admin):**

* `GET /admin/vehicle-categories` lists all categories, including inactive ones.
* `POST /admin/vehicle-categories` with `{"code": "three_wheeler", "name": "Three-wheeler", "requires_plate": true, "is_motorized": true}`. The `code` is lowercase and can't be changed later.
* `PATCH /admin/vehicle-categories/{id}` accepts `name`, `requires_plate`, `is_motorized`, `is_active` and `sort_order`.
* Categories are never deleted, only deactivated. A deactivated category is refused for new vehicles and new entries. Vehicles already parked in it can still exit.
* Changes that would make existing vehicles invalid are refused with `409 category_change_conflict`. For example: making a category non-motorised while its vehicles have a petrol fuel type, or requiring a plate while plate-less vehicles exist.

### Penalties (separate from overtime, never automatic)

| Method | Path | Roles |
|---|---|---|
| GET | `/staff/penalty-types` | staff, admin, super_admin (active types) |
| GET | `/staff/sessions/{uuid}/penalties` | staff, admin, super_admin |
| POST | `/staff/sessions/{uuid}/penalties` | staff, admin, super_admin. Body: `{penalty_type_id, reason}` |
| POST | `/admin/penalties/{id}/waive` | admin, super_admin. Body: `{reason}` |
| GET / POST / PATCH | `/admin/penalty-types[/{id}]` | admin, super_admin. Body: `{code, name, description?, amount_minor, is_active?}` |

Apply a penalty, response `201`:

```json
{"data": {"id": 1, "type": {"id": 1, "code": "lost_ticket", "name": "Lost parking ticket"},
  "amount_minor": 50000, "amount": "500.00", "reason": "Customer lost the QR ticket", "status": "applied",
  "applied_by": 1, "applied_at": "2026-11-04T04:30:00+00:00", "waived_by": null, "waived_at": null, "waiver_reason": null}}
```

Rules:

* A penalty type can be active only once per session (`409 duplicate_penalty`). This is enforced by a unique database key, so it also holds under concurrent requests.
* The amount is copied when the penalty is applied, so later catalogue price changes don't affect it.
* Penalties can only change while the session is `active` and no payment is pending (`409 invalid_session_state` / `payment_pending`).
* A waived penalty is kept for audit, and the same type can then be applied again.
* Every apply or waive is written to the audit log.

**Lost ticket flow:**

1. Find the session with `GET /staff/sessions?q=<plate>`.
2. Apply the `lost_ticket` penalty: `POST /staff/sessions/{uuid}/penalties`.
3. Reissue the ticket: `POST /staff/sessions/{uuid}/ticket/reissue`. The old QR is revoked.
4. Settle with the new QR.

### Final bill: `GET /staff/sessions/{uuid}/bill` and `GET /customer/sessions/{uuid}/bill`

Open sessions get a live bill as of now. Closed sessions get the stored settlement. The same `bill` object is embedded in every new receipt at `receipt.breakdown.bill`.

```json
{"data": {
  "session_reference": "PS-261103-TAZR4D", "status": "active",
  "vehicle_number": "WPCAB1234", "plate_number": "WP CAB-1234", "vehicle_category": "car", "fuel_type": "diesel",
  "zone": "B", "package": "Daily (24 hours)",
  "entry_at": "2026-11-03T03:30:00+00:00", "exit_at": "2026-11-04T04:30:00+00:00",
  "entry_at_local": "2026-11-03 09:00", "exit_at_local": "2026-11-04 10:00",
  "duration_minutes": 1500, "duration_human": "1d 1h 0m", "currency": "LKR",
  "base_charge_minor": 150000, "overtime_charge_minor": 10000, "penalty_charge_minor": 50000,
  "safety_charge_minor": 0, "tax_minor": 0, "total_payable_minor": 210000,
  "amount_paid_minor": 0, "balance_minor": 210000, "overpaid_minor": 0,
  "lines": [
    {"code": "package", "label": "Package: daily", "amount_minor": 150000},
    {"code": "overtime", "label": "Overtime", "amount_minor": 10000, "detail": {"minutes": 60, "hourly_minor": 10000, "daily_cap_minor": 150000}},
    {"code": "penalty", "label": "Penalty: Lost parking ticket", "amount_minor": 50000, "detail": {"penalty_id": 1, "penalty_code": "lost_ticket", "reason": "Customer lost the QR ticket"}}
  ],
  "payment_methods": [], "payments": [], "receipt_number": null}}
```

The exit `quote` (from preview and settle) now also includes `penalty_minor`, `parking_total_minor` and a `charges` summary. Tax applies only to parking charges, never to penalties; tax rules are still pending.

### Payments: idempotency

Repeating `POST /staff/exits/settle` with the same `idempotency_key` but a **different `payment_method`** now returns `409 idempotency_conflict`. Before, it was replayed. A genuine retry must repeat the same details. Prepayments already rejected a changed amount or method (PR #1).

### Remaining time

Every session resource now has a `time` object:

```json
{"status": "expired", "remaining_minutes": 0, "overdue_minutes": 60,
 "package_ends_at": "2026-11-04T03:30:00+00:00", "grace_ends_at": "2026-11-04T03:45:00+00:00"}
```

| `status` | Meaning |
|---|---|
| `no_fixed_expiry` | Hourly short-time package. There is nothing to count down, so `remaining_minutes` is `null`. |
| `active` | More time is left than the largest reminder threshold |
| `expiring_soon` | Within that threshold |
| `in_grace` | Past the package end but inside the grace period |
| `expired` | Past grace; overtime applies |
| `closed` | The session has ended |

`GET /staff/sessions/expiring?within=60` lists open sessions whose package ends within N minutes, plus those already expired.

**Reminders:**

* `php artisan parking:send-expiry-reminders` is scheduled every 5 minutes.
* It notifies the **owning customer only** (customers with an account) using in-app database notifications. **No SMS, WhatsApp or email provider is integrated.**
* Customers read them with the existing `GET /notifications` and `POST /notifications/{id}/read` endpoints. The notification `data.kind` is `package_expiring` or `package_expired`.
* A unique key prevents duplicate reminders, including when scheduler runs overlap.

### Parking-record filters

These filters can be combined and are validated. They are accepted by:

* `GET /reports/sessions` (admin, super_admin, auditor)
* `GET /staff/sessions` (staff, admin, super_admin)
* `GET /customer/sessions` (always limited to the customer's own sessions)

| Parameter | Values |
|---|---|
| `vehicle_category_id` or `vehicle_category` | id or code (e.g. `car`). This is the category **recorded at entry**. |
| `fuel_type` | a fuel type value |
| `status` | `active`, `exit_authorized`, `completed`, `cancelled`. Comma-separated for several, e.g. `status=active,exit_authorized`. |
| `date` or `from` + `to` | `Y-m-d`, Sri Lanka calendar days. `date` can't be combined with `from`/`to`. |
| `date_field` | `entry` (default), `exit`, or `present` (parked at any time during the day) |
| `plate` (or `q`) | partial plate match |
| `zone_id`, `package_id` | ids |
| `payment_status` | `paid`, `pending`, `failed` or `refunded` match sessions with a payment in that state; `unpaid` matches sessions with no paid payment |
| `per_page` | 1 to 100 |

Defaults are unchanged: `/staff/sessions` shows open sessions when no `status` is given, and `/reports/sessions` covers the last 7 days when no date is given.

Example: `GET /reports/sessions?vehicle_category=car&fuel_type=diesel&status=active&from=2026-11-01&to=2026-11-30`.

The four "primary" filters (vehicle category, fuel type, status, date range) are a **proposed interpretation** of the stakeholder's "four filter functions". They are set in `config/parking.php` (`session_filters.primary`) and returned in `reference-data.session_filters`, so the UI can change which filters it shows first without any API change.

### Calendar: `GET /reports/calendar?month=2026-11` (or `?from=2026-11-03&to=2026-11-04`, max 62 days)

Roles: admin, super_admin, auditor.

```json
{"data": {"from": "2026-11-03", "to": "2026-11-04", "timezone": "Asia/Colombo", "currency": "LKR", "days": [
  {"date": "2026-11-03", "entries": 1, "exits": 0, "parked_at_end_of_day": 1, "revenue_minor": 0, "penalties_minor": 0},
  {"date": "2026-11-04", "entries": 0, "exits": 0, "parked_at_end_of_day": 1, "revenue_minor": 0, "penalties_minor": 50000}]}}
```

To show the vehicles for a selected day, call `/reports/sessions?date=YYYY-MM-DD&date_field=entry` (entries), `date_field=exit` (exits) or `date_field=present`. Daily revenue is counted by payment time, and penalties by the time they were applied.

`GET /reports/financial` now also returns `penalties` (`applied_count`, `applied_minor`, `waived_count`, `waived_minor`, `by_type`).

---

## Weekly & monthly guaranteed parking (reservations)

Packages with `guarantees_slot: true` hold a **dedicated slot for their whole validity**. The seed data sets this for weekly (7 days) and monthly (30 days); admins can change it with `PATCH /admin/packages/{id}`. Only fixed and night packages can have it. Hourly and add-on packages are refused.

The entry and exit endpoints are unchanged; the API decides what to do from the vehicle and the package:

| Situation at `POST /staff/entries` | What happens |
|---|---|
| Guaranteed package, no reservation | **First entry.** A free slot is allocated, the reservation is created (expiry = entry + package duration), and the first visit's bill carries the package price |
| Vehicle has a valid reservation | **Re-entry.** The same slot is reused, the expiry stays the same, and the package price is *not* charged again. The request must use the reservation's package and zone, otherwise you get `409 reservation_package_mismatch` or `reservation_zone_mismatch` (the `context` shows the reservation) |
| Reservation has expired | The old reservation ends and the entry is treated as a new purchase. The previous slot is tried first |
| Non-guaranteed package | Unchanged |

**Exit** (settle, or `confirm-left` in gate mode):

* While the reservation is valid, the slot goes from `occupied` to `reserved` once the vehicle has left.
* Re-entry visits are billed only for overtime past the reservation's expiry, for the safety add-on, and for any penalties.
* A temporary exit never creates an early-exit credit or refund.

**Endpoints:**

| Method | Path | Roles |
|---|---|---|
| GET | `/staff/reservations?status=&plate=&zone_id=&expiring_within=&per_page=` | staff, admin, super_admin |
| GET | `/staff/reservations/{id}` (plus `visits`) | staff, admin, super_admin |
| POST | `/staff/reservations/{id}/renew` | staff, admin, super_admin |
| POST | `/admin/reservations/{id}/cancel`, body `{reason, refund_minor?}` | admin, super_admin |
| GET | `/customer/reservations`, `/customer/reservations/{id}` | customer (own only) |

`GET /staff/vehicles/lookup?plate=` now returns `active_reservation` (`slot`, `zone`, `package`, `expires_at`, `is_valid`).

**Reservation resource:**

```json
{"id": 1, "status": "active", "is_valid": true, "vehicle": {"id": 1, "identifier": "WPCAR0001"},
 "zone": {"id": 4, "code": "D"}, "slot": {"id": 70, "code": "D-CAR-001", "status": "reserved"},
 "package": {"id": 5, "code": "monthly", "name": "Monthly (30 days)"},
 "starts_at": "2026-11-05T02:30:00+00:00", "expires_at": "2026-12-05T02:30:00+00:00", "expires_at_local": "2026-12-05 08:00",
 "remaining_minutes": 43020, "vehicle_on_site": false, "current_session_uuid": null,
 "periods": [{"sequence": 1, "kind": "initial", "price_minor": 3000000, "price": "30,000.00", "status": "billed", "paid": true}]}
```

**Renewal** adds one package period starting at the current expiry, at the current rate. The slot is kept.

* The renewal price is billed on the visit in progress, or on the next visit if the vehicle is away. The response shows `billed_on`.
* Only one unbilled renewal can be pending at a time (`409 renewal_pending`).
* Expired reservations can't be renewed (`409 reservation_not_renewable`); the next entry starts a new purchase instead.

**Cancellation** (admin only):

* Periods that haven't started yet are voided.
* A vacant slot is released straight away. If the vehicle is inside, the slot stays occupied until it leaves.
* There's **no automatic refund**. An optional `refund_minor`, capped at the paid package amount, creates a *pending* refund that goes through the existing approval endpoint.

**Expiry** runs as `php artisan parking:expire-reservations` every 5 minutes. It is idempotent, and `--at=` sets the evaluation time.

* Vacant `reserved` slots are released.
* Occupied slots are never released. The vehicle is billed overtime from the expiry and the slot is released when it leaves.

**New error codes:** `reservation_package_mismatch`, `reservation_zone_mismatch`, `reservation_category_mismatch`, `reserved_slot_unavailable`, `reservation_expired`, `reservation_not_renewable`, `renewal_pending`, `reservation_not_active`, `refund_exceeds_paid`, `package_not_reservable`, `package_inactive`, `slot_reserved_for_customer`, `vehicle_has_reservation`.
