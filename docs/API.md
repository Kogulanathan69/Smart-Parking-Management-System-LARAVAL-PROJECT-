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

Business `error_code` values: `no_slot_available`, `duplicate_active_entry`, `invalid_ticket`, `ticket_already_used`, `payment_pending`, `payment_method_required`, `insufficient_tender`, `reference_required`, `exit_inspection_required`, `category_not_allowed`, `package_zone_mismatch`, `plate_required`, `category_mismatch`, `rate_not_configured`, `safety_rate_not_configured`, `capacity_below_occupied`, `slot_locked`, `account_limit_reached`, `last_super_admin`, `forbidden_role`, `forbidden_account`, `vehicle_unavailable`, `outside_shift_window`, `already_checked_in`, `duplicate_assignment`, `invalid_staff`, `inspection_completed`, `account_inactive`.

## Endpoints

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
| GET | `/api/v1/staff/sessions` | staff, admin, super_admin |
| GET | `/api/v1/staff/sessions/{session}` | staff, admin, super_admin |
| POST | `/api/v1/staff/sessions/{session}/confirm-left` | staff, admin, super_admin |
| GET | `/api/v1/staff/sessions/{session}/inspections` | staff, admin, super_admin |
| POST | `/api/v1/staff/sessions/{session}/inspections` | staff, admin, super_admin |
| POST | `/api/v1/staff/sessions/{session}/payments` | staff, admin, super_admin |
| GET | `/api/v1/staff/sessions/{session}/ticket` | staff, admin, super_admin |
| POST | `/api/v1/staff/sessions/{session}/ticket/reissue` | staff, admin, super_admin |
| GET | `/api/v1/staff/shifts/mine` | staff |
| POST | `/api/v1/staff/shifts/{assignment}/check-in` | staff |
| POST | `/api/v1/staff/shifts/{assignment}/check-out` | staff |
| GET | `/api/v1/staff/vehicles/lookup` | staff, admin, super_admin |

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
| GET | `/api/v1/admin/zones` | admin, super_admin |
| POST | `/api/v1/admin/zones` | admin, super_admin |
| GET | `/api/v1/admin/zones/{zone}` | admin, super_admin |
| PUT|PATCH | `/api/v1/admin/zones/{zone}` | admin, super_admin |
| PUT | `/api/v1/admin/zones/{zone}/capacity` | admin, super_admin |
| GET | `/api/v1/admin/zones/{zone}/slots` | admin, super_admin |

### Reports (read-only)

| Method | Path | Roles |
|---|---|---|
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
