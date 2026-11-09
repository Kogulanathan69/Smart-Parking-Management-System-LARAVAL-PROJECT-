# Role & permission matrix

Generated from the live route middleware. Permissions are fixed in code; the in-app page **Management › Roles & permissions** (`/manage/roles`) renders the same table for admins, super admins and auditors.
The UI hides what a role cannot use, but every page and API call is also authorised by the server (`role:` middleware, then policies/ownership checks — e.g. customers only ever receive their own sessions, vehicles, receipts and requests).

## Account rules (from the backend)

* Only **customers** can self-register (`/register`). Any `role` field sent there is ignored.
* Admins create **staff** and **customer** accounts; only a super admin creates **admin** (max 2) and **auditor** accounts. Super admins are created only with `php artisan parking:create-super-admin`.
* Admins manage (status) only **staff** and **customer** accounts; super admins manage every role, but never the last active super admin. Role changes (admin ↔ staff ↔ auditor) are effectively a super-admin action. Nobody can change their own role or status (`UserAccountService::MANAGEABLE_ROLES`).
* Inactive/suspended users cannot sign in and are signed out on their next request.
* **Auditor** is read-only for operational data. The one write the backend gives auditors is recording **audit findings** (`POST /api/v1/audit/findings`), which are the auditor's own notes and change no parking, payment or gate record.

## Pages

| Page | Super Admin | Admin | Staff | Auditor | Customer |
|---|---|---|---|---|---|
| `/account` | ✔ | ✔ | ✔ | ✔ | ✔ |
| `/admin/dashboard` | — | ✔ | — | — | — |
| `/audit/anpr-corrections` | ✔ | — | — | ✔ | — |
| `/audit/findings` | ✔ | — | — | ✔ | — |
| `/audit/logs` | ✔ | — | — | ✔ | — |
| `/audit/recognition-history` | ✔ | — | — | ✔ | — |
| `/auditor/dashboard` | — | — | — | ✔ | — |
| `/customer/dashboard` | — | — | — | — | ✔ |
| `/manage/cameras` | ✔ | ✔ | — | — | — |
| `/manage/cameras/{id}` | ✔ | ✔ | — | — | — |
| `/manage/complaints` | ✔ | ✔ | — | — | — |
| `/manage/incidents` | ✔ | ✔ | — | — | — |
| `/manage/packages` | ✔ | ✔ | — | — | — |
| `/manage/packages/{id}` | ✔ | ✔ | — | — | — |
| `/manage/payment-methods` | ✔ | ✔ | — | — | — |
| `/manage/penalty-types` | ✔ | ✔ | — | — | — |
| `/manage/price-calculator` | ✔ | ✔ | — | — | — |
| `/manage/refunds` | ✔ | ✔ | — | — | — |
| `/manage/roles` | ✔ | ✔ | — | ✔ | — |
| `/manage/settings` | ✔ | ✔ | — | — | — |
| `/manage/shifts` | ✔ | ✔ | — | — | — |
| `/manage/system` | ✔ | ✔ | — | — | — |
| `/manage/users` | ✔ | ✔ | — | — | — |
| `/manage/users/{id}` | ✔ | ✔ | — | — | — |
| `/manage/vehicle-categories` | ✔ | ✔ | — | — | — |
| `/manage/zones` | ✔ | ✔ | — | — | — |
| `/manage/zones/{id}` | ✔ | ✔ | — | — | — |
| `/my/credits` | — | — | — | — | ✔ |
| `/my/history` | — | — | — | — | ✔ |
| `/my/history/{uuid}` | — | — | — | — | ✔ |
| `/my/parking` | — | — | — | — | ✔ |
| `/my/parking-info` | — | — | — | — | ✔ |
| `/my/receipts` | — | — | — | — | ✔ |
| `/my/receipts/{number}` | — | — | — | — | ✔ |
| `/my/reservations` | — | — | — | — | ✔ |
| `/my/reservations/{id}` | — | — | — | — | ✔ |
| `/my/support` | — | — | — | — | ✔ |
| `/my/support/{id}` | — | — | — | — | ✔ |
| `/my/vehicles` | — | — | — | — | ✔ |
| `/notifications` | ✔ | ✔ | ✔ | ✔ | ✔ |
| `/ops/anpr` | ✔ | ✔ | ✔ | — | — |
| `/ops/anpr/{uuid}` | ✔ | ✔ | ✔ | — | — |
| `/ops/availability` | ✔ | ✔ | ✔ | — | — |
| `/ops/check-in` | ✔ | ✔ | ✔ | — | — |
| `/ops/check-out` | ✔ | ✔ | ✔ | — | — |
| `/ops/expiring` | ✔ | ✔ | ✔ | — | — |
| `/ops/incidents/new` | ✔ | ✔ | ✔ | — | — |
| `/ops/inspections` | ✔ | ✔ | ✔ | — | — |
| `/ops/my-shifts` | — | — | ✔ | — | — |
| `/ops/reservations` | ✔ | ✔ | ✔ | — | — |
| `/ops/reservations/{id}` | ✔ | ✔ | ✔ | — | — |
| `/ops/sessions` | ✔ | ✔ | ✔ | — | — |
| `/ops/sessions/{uuid}` | ✔ | ✔ | ✔ | — | — |
| `/ops/vehicles` | ✔ | ✔ | ✔ | — | — |
| `/ops/vehicles/{id}` | ✔ | ✔ | ✔ | — | — |
| `/reports` | ✔ | ✔ | — | ✔ | — |
| `/reports/anpr` | ✔ | ✔ | — | ✔ | — |
| `/reports/calendar` | ✔ | ✔ | — | ✔ | — |
| `/reports/financial` | ✔ | ✔ | — | ✔ | — |
| `/reports/inspections` | ✔ | ✔ | — | ✔ | — |
| `/reports/occupancy` | ✔ | ✔ | — | ✔ | — |
| `/reports/payments` | ✔ | ✔ | — | ✔ | — |
| `/reports/sessions` | ✔ | ✔ | — | ✔ | — |
| `/reports/staff` | ✔ | ✔ | — | ✔ | — |
| `/staff/dashboard` | — | — | ✔ | — | — |
| `/super-admin/dashboard` | ✔ | — | — | — | — |

## API (grouped)

| Area | Method + path | Super Admin | Admin | Staff | Auditor | Customer |
|---|---|---|---|---|---|---|
| Admin | GET `/api/v1/admin/anpr/cameras` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/anpr/cameras` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/anpr/cameras/{camera}` | ✔ | ✔ | — | — | — |
| Admin | PATCH `/api/v1/admin/anpr/cameras/{camera}` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/anpr/cameras/{camera}/health` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/anpr/cameras/{camera}/rotate-token` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/attendance` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/complaints` | ✔ | ✔ | — | — | — |
| Admin | PATCH `/api/v1/admin/complaints/{complaint}` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/incidents` | ✔ | ✔ | — | — | — |
| Admin | PATCH `/api/v1/admin/incidents/{incident}` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/packages` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/packages` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/packages/{package}` | ✔ | ✔ | — | — | — |
| Admin | PUT|PATCH `/api/v1/admin/packages/{package}` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/packages/{package}/rates` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/packages/{package}/rates` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/payment-methods` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/payment-methods` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/penalties/{penalty}/waive` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/penalty-types` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/penalty-types` | ✔ | ✔ | — | — | — |
| Admin | PATCH `/api/v1/admin/penalty-types/{type}` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/pricing/preview` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/refunds` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/refunds/{refund}/decision` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/reservations/{reservation}/cancel` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/settings` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/settings/history` | ✔ | ✔ | — | — | — |
| Admin | PUT `/api/v1/admin/settings/{key}` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/shift-assignments` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/shift-assignments` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/shift-assignments/{assignment}/leave` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/shift-coverage` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/shifts` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/shifts` | ✔ | ✔ | — | — | — |
| Admin | PUT `/api/v1/admin/shifts/{shift}` | ✔ | ✔ | — | — | — |
| Admin | PATCH `/api/v1/admin/slots/{slot}/status` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/system/status` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/users` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/users` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/users/{user}` | ✔ | ✔ | — | — | — |
| Admin | PATCH `/api/v1/admin/users/{user}/role` | ✔ | ✔ | — | — | — |
| Admin | PATCH `/api/v1/admin/users/{user}/status` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/vehicle-categories` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/vehicle-categories` | ✔ | ✔ | — | — | — |
| Admin | PATCH `/api/v1/admin/vehicle-categories/{category}` | ✔ | ✔ | — | — | — |
| Admin | PATCH `/api/v1/admin/vehicles/{vehicle}` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/zones` | ✔ | ✔ | — | — | — |
| Admin | POST `/api/v1/admin/zones` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/zones/{zone}` | ✔ | ✔ | — | — | — |
| Admin | PUT|PATCH `/api/v1/admin/zones/{zone}` | ✔ | ✔ | — | — | — |
| Admin | PUT `/api/v1/admin/zones/{zone}/capacity` | ✔ | ✔ | — | — | — |
| Admin | GET `/api/v1/admin/zones/{zone}/slots` | ✔ | ✔ | — | — | — |
| ANPR device | GET `/api/v1/anpr/device/config` | — (camera agent token) | — (camera agent token) | — (camera agent token) | — (camera agent token) | — (camera agent token) |
| ANPR device | POST `/api/v1/anpr/device/events` | — (camera agent token) | — (camera agent token) | — (camera agent token) | — (camera agent token) | — (camera agent token) |
| ANPR device | POST `/api/v1/anpr/device/heartbeat` | — (camera agent token) | — (camera agent token) | — (camera agent token) | — (camera agent token) | — (camera agent token) |
| Anpr | GET `/api/v1/anpr/health` | public | public | public | public | public |
| Audit | GET `/api/v1/audit/anpr/corrections` | ✔ | — | — | ✔ | — |
| Audit | GET `/api/v1/audit/findings` | ✔ | — | — | ✔ | — |
| Audit | POST `/api/v1/audit/findings` | — | — | — | ✔ | — |
| Audit | GET `/api/v1/audit/logs` | ✔ | — | — | ✔ | — |
| Customer | GET `/api/v1/customer/complaints` | — | — | — | — | ✔ |
| Customer | POST `/api/v1/customer/complaints` | — | — | — | — | ✔ |
| Customer | GET `/api/v1/customer/complaints/{complaint}` | — | — | — | — | ✔ |
| Customer | GET `/api/v1/customer/credits` | — | — | — | — | ✔ |
| Customer | GET `/api/v1/customer/parking-info` | — | — | — | — | ✔ |
| Customer | GET `/api/v1/customer/receipts` | — | — | — | — | ✔ |
| Customer | GET `/api/v1/customer/receipts/{receipt:number}` | — | — | — | — | ✔ |
| Customer | GET `/api/v1/customer/reservations` | — | — | — | — | ✔ |
| Customer | GET `/api/v1/customer/reservations/{reservation}` | — | — | — | — | ✔ |
| Customer | GET `/api/v1/customer/sessions` | — | — | — | — | ✔ |
| Customer | GET `/api/v1/customer/sessions/{session}` | — | — | — | — | ✔ |
| Customer | GET `/api/v1/customer/sessions/{session}/bill` | — | — | — | — | ✔ |
| Customer | GET `/api/v1/customer/vehicles` | — | — | — | — | ✔ |
| Customer | POST `/api/v1/customer/vehicles` | — | — | — | — | ✔ |
| Customer | GET `/api/v1/customer/vehicles/{vehicle}` | — | — | — | — | ✔ |
| Customer | PUT|PATCH `/api/v1/customer/vehicles/{vehicle}` | — | — | — | — | ✔ |
| Customer | DELETE `/api/v1/customer/vehicles/{vehicle}` | — | — | — | — | ✔ |
| Evidence | GET `/api/v1/evidence/{evidence}` | ✔ | ✔ | ✔ | ✔ | ✔ |
| Me | GET `/api/v1/me` | ✔ | ✔ | ✔ | ✔ | ✔ |
| Notifications | GET `/api/v1/notifications` | ✔ | ✔ | ✔ | ✔ | ✔ |
| Notifications | POST `/api/v1/notifications/read-all` | ✔ | ✔ | ✔ | ✔ | ✔ |
| Notifications | POST `/api/v1/notifications/{id}/read` | ✔ | ✔ | ✔ | ✔ | ✔ |
| Reference Data | GET `/api/v1/reference-data` | ✔ | ✔ | ✔ | ✔ | ✔ |
| Reports | GET `/api/v1/reports/anpr/camera-health` | ✔ | ✔ | — | ✔ | — |
| Reports | GET `/api/v1/reports/anpr/overview` | ✔ | ✔ | — | ✔ | — |
| Reports | GET `/api/v1/reports/anpr/recognition-history` | ✔ | ✔ | — | ✔ | — |
| Reports | GET `/api/v1/reports/calendar` | ✔ | ✔ | — | ✔ | — |
| Reports | GET `/api/v1/reports/financial` | ✔ | ✔ | — | ✔ | — |
| Reports | GET `/api/v1/reports/inspections` | ✔ | ✔ | — | ✔ | — |
| Reports | GET `/api/v1/reports/occupancy` | ✔ | ✔ | — | ✔ | — |
| Reports | GET `/api/v1/reports/overview` | ✔ | ✔ | — | ✔ | — |
| Reports | GET `/api/v1/reports/payments` | ✔ | ✔ | — | ✔ | — |
| Reports | GET `/api/v1/reports/sessions` | ✔ | ✔ | — | ✔ | — |
| Reports | GET `/api/v1/reports/staff-performance` | ✔ | ✔ | — | ✔ | — |
| Staff | GET `/api/v1/staff/anpr/cameras` | ✔ | ✔ | ✔ | ✔ | — |
| Staff | POST `/api/v1/staff/anpr/entry/confirm` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/anpr/entry/preview` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/anpr/events` | ✔ | ✔ | ✔ | ✔ | — |
| Staff | GET `/api/v1/staff/anpr/events/{event}` | ✔ | ✔ | ✔ | ✔ | — |
| Staff | GET `/api/v1/staff/anpr/events/{event}/images/{image}` | ✔ | ✔ | ✔ | ✔ | — |
| Staff | POST `/api/v1/staff/anpr/events/{event}/verify` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/anpr/exit/preview` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/anpr/exit/settle` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/anpr/manual-review` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/anpr/recognize` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/availability` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/entries` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/evidence` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/exits/preview` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/exits/settle` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/incidents` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/inspections/due` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/payments/{payment}/confirm` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/payments/{payment}/fail` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/penalty-types` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/reservations` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/reservations/{reservation}` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/reservations/{reservation}/renew` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/sessions` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/sessions/expiring` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/sessions/{session}` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/sessions/{session}/bill` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/sessions/{session}/confirm-left` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/sessions/{session}/gate` | ✔ | ✔ | ✔ | ✔ | — |
| Staff | POST `/api/v1/staff/sessions/{session}/gate/exit` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/sessions/{session}/inspections` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/sessions/{session}/inspections` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/sessions/{session}/payments` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/sessions/{session}/penalties` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/sessions/{session}/penalties` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/sessions/{session}/ticket` | ✔ | ✔ | ✔ | — | — |
| Staff | POST `/api/v1/staff/sessions/{session}/ticket/reissue` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/shifts/mine` | — | — | ✔ | — | — |
| Staff | POST `/api/v1/staff/shifts/{assignment}/check-in` | — | — | ✔ | — | — |
| Staff | POST `/api/v1/staff/shifts/{assignment}/check-out` | — | — | ✔ | — | — |
| Staff | GET `/api/v1/staff/vehicles` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/vehicles/lookup` | ✔ | ✔ | ✔ | — | — |
| Staff | GET `/api/v1/staff/vehicles/{vehicle}` | ✔ | ✔ | ✔ | — | — |
