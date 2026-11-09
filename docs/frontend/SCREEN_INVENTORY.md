# Screen inventory

Every browser screen, generated from the live route table (`routes/web.php`), the sidebar definition (`App\Support\Frontend\Navigation`) and the views' API calls. All page routes are rendered and role-checked for **all five roles** by `tests/Feature/FrontendPagesTest.php`; *E2E* marks screens driven end-to-end by `tests/e2e/journeys.mjs`.

| # | Screen (route name) | Roles | Menu path | Route | Backend actions used | State | E2E |
|---|---|---|---|---|---|---|---|
| 1 | — | guest | — | `/` | — | Implemented | — |
| 2 | account | any signed-in | Account › My account | `/account` | /user/confirm-password, /user/confirmed-password-status, /user/confirmed-two-factor-authentication, /user/password, /user/profile-information, /user/two-factor-authentication, /user/two-factor-qr-code, /user/two-factor-recovery-codes, /user/two-factor-secret-key | Implemented | — |
| 3 | dashboard.admin | admin | Overview › Dashboard | `/admin/dashboard` | GET /admin/dashboard (JSON for API clients); /reports/anpr/camera-health, /staff/anpr/events | Implemented | Yes |
| 4 | audit.corrections | super_admin, auditor | Audit › ANPR corrections | `/audit/anpr-corrections` | /audit/anpr/corrections | Implemented | Yes |
| 5 | audit.findings | super_admin, auditor | Audit › Audit findings | `/audit/findings` | /audit/findings | Implemented | — |
| 6 | audit.logs | super_admin, auditor | Audit › Audit trail | `/audit/logs` | /audit/logs | Implemented | Yes |
| 7 | audit.anpr-events | super_admin, auditor | Audit › Recognition history | `/audit/recognition-history` | /staff/anpr/events | Implemented | Yes |
| 8 | dashboard.auditor | auditor | Overview › Dashboard | `/auditor/dashboard` | GET /auditor/dashboard (JSON for API clients); /reports/overview | Implemented | Yes |
| 9 | dashboard.customer | customer | Overview › Dashboard | `/customer/dashboard` | GET /customer/dashboard (JSON for API clients); /customer/sessions | Implemented | Yes |
| 10 | password.request | guest | — | `/forgot-password` | Fortify PasswordResetLinkController@create | Implemented | — |
| 11 | login | guest | — | `/login` | Fortify AuthenticatedSessionController@create | Implemented | Yes |
| 12 | manage.cameras | admin, super_admin | Management › Cameras | `/manage/cameras` | /admin/anpr/cameras | Implemented | — |
| 13 | manage.cameras.show | admin, super_admin | Opened from its list page | `/manage/cameras/{id}` | /admin/anpr/cameras/{id}, /admin/anpr/cameras/{id}/health, /admin/anpr/cameras/{id}/rotate-token | Implemented | — |
| 14 | manage.complaints | admin, super_admin | Management › Customer support | `/manage/complaints` | /admin/complaints, /admin/complaints/{id} | Implemented | — |
| 15 | manage.incidents | admin, super_admin | Management › Incidents | `/manage/incidents` | /admin/incidents, /admin/incidents/{id} | Implemented | — |
| 16 | manage.packages | admin, super_admin | Management › Packages & rates | `/manage/packages` | /admin/packages | Implemented | — |
| 17 | manage.packages.show | admin, super_admin | Opened from its list page | `/manage/packages/{id}` | /admin/packages/{id}, /admin/packages/{id}/rates | Implemented | — |
| 18 | manage.payment-methods | admin, super_admin | Management › Payment methods | `/manage/payment-methods` | /admin/payment-methods | Implemented | — |
| 19 | manage.penalties | admin, super_admin | Management › Penalty types | `/manage/penalty-types` | /admin/penalty-types, /admin/penalty-types/{id} | Implemented | Yes |
| 20 | manage.pricing | admin, super_admin | Management › Price calculator | `/manage/price-calculator` | /admin/pricing/preview | Implemented | — |
| 21 | manage.refunds | admin, super_admin | Management › Refunds | `/manage/refunds` | /admin/refunds, /admin/refunds/{id}/decision | Implemented | — |
| 22 | manage.roles | admin, super_admin, auditor | Management › Roles & permissions | `/manage/roles` | server-side from route table | Implemented | — |
| 23 | manage.settings | admin, super_admin | Management › Settings | `/manage/settings` | /admin/settings, /admin/settings/history, /admin/settings/{id} | Implemented | — |
| 24 | manage.shifts | admin, super_admin | Management › Shifts & roster | `/manage/shifts` | /admin/attendance, /admin/shift-assignments, /admin/shift-assignments/{id}/leave, /admin/shift-coverage, /admin/shifts, /admin/shifts/{id}, /admin/users | Implemented | — |
| 25 | manage.system | admin, super_admin | Management › System status | `/manage/system` | /admin/system/status, /reports/anpr/camera-health | Implemented | Yes |
| 26 | manage.users | admin, super_admin | Management › Users | `/manage/users` | /admin/users | Implemented | Yes |
| 27 | manage.users.show | admin, super_admin | Opened from its list page | `/manage/users/{id}` | /admin/users/{id}, /admin/users/{id}/role, /admin/users/{id}/status | Implemented | — |
| 28 | manage.categories | admin, super_admin | Management › Vehicle categories | `/manage/vehicle-categories` | /admin/vehicle-categories, /admin/vehicle-categories/{id} | Implemented | — |
| 29 | manage.zones | admin, super_admin | Management › Zones & slots | `/manage/zones` | /admin/zones | Implemented | — |
| 30 | manage.zones.show | admin, super_admin | Opened from its list page | `/manage/zones/{id}` | /admin/slots/{id}/status, /admin/zones/{id}, /admin/zones/{id}/capacity, /admin/zones/{id}/slots | Implemented | — |
| 31 | my.credits | customer | My parking › Credit balance | `/my/credits` | /customer/credits | Implemented | — |
| 32 | my.history | customer | My parking › History | `/my/history` | /customer/sessions | Implemented | — |
| 33 | my.history.show | customer | Opened from its list page | `/my/history/{uuid}` | /customer/sessions/{{ | Implemented | — |
| 34 | my.active | customer | My parking › Active parking | `/my/parking` | /customer/sessions | Implemented | — |
| 35 | my.parking-info | customer | My parking › Parking info & prices | `/my/parking-info` | /customer/parking-info | Implemented | Yes |
| 36 | my.receipts | customer | My parking › Receipts | `/my/receipts` | /customer/receipts | Implemented | — |
| 37 | my.receipts.show | customer | Opened from its list page | `/my/receipts/{number}` | /customer/receipts/{{ | Implemented | — |
| 38 | my.reservations | customer | My parking › Reservations | `/my/reservations` | /customer/reservations | Implemented | — |
| 39 | my.reservations.show | customer | Opened from its list page | `/my/reservations/{id}` | /customer/reservations/{{ | Implemented | — |
| 40 | my.support | customer | My parking › Support | `/my/support` | /customer/complaints | Implemented | — |
| 41 | my.support.show | customer | Opened from its list page | `/my/support/{id}` | /customer/complaints/{{ | Implemented | — |
| 42 | my.vehicles | customer | My parking › My vehicles | `/my/vehicles` | /customer/vehicles, /customer/vehicles/{id} | Implemented | Yes |
| 43 | notifications | any signed-in | Account › Notifications | `/notifications` | /notifications, /notifications/read-all, /notifications/{id}/read | Implemented | — |
| 44 | ops.anpr.queue | staff, admin, super_admin | Gate operations › ANPR review queue | `/ops/anpr` | /staff/anpr/events | Implemented | Yes |
| 45 | ops.anpr.show | staff, admin, super_admin | Opened from its list page | `/ops/anpr/{uuid}` | /staff/anpr/events/{id} | Implemented | — |
| 46 | ops.availability | staff, admin, super_admin | Gate operations › Availability | `/ops/availability` | /staff/availability | Implemented | — |
| 47 | ops.check-in | staff, admin, super_admin | Gate operations › Check-In | `/ops/check-in` | /staff/anpr/cameras, /staff/anpr/entry/confirm, /staff/anpr/entry/preview, /staff/anpr/events, /staff/anpr/events/{id}, /staff/anpr/events/{id}/verify, /staff/anpr/manual-review, /staff/anpr/recognize, /staff/availability | Implemented | Yes |
| 48 | ops.check-out | staff, admin, super_admin | Gate operations › Check-Out | `/ops/check-out` | /staff/anpr/cameras, /staff/anpr/events, /staff/anpr/events/{id}, /staff/anpr/events/{id}/verify, /staff/anpr/exit/preview, /staff/anpr/exit/settle, /staff/anpr/manual-review, /staff/anpr/recognize, /staff/exits/preview, /staff/exits/settle, /staff/payments/{id}/${confirm, /staff/sessions, /staff/sessions/{id}/confirm-left, /staff/sessions/{id}/gate/exit, /staff/sessions/{id}/inspections | Implemented | Yes |
| 49 | ops.expiring | staff, admin, super_admin | Gate operations › Expiring & overstay | `/ops/expiring` | /staff/sessions/expiring | Implemented | — |
| 50 | ops.incidents.create | staff, admin, super_admin | Safety & shifts › Report incident | `/ops/incidents/new` | /staff/evidence, /staff/incidents | Implemented | — |
| 51 | ops.inspections | staff, admin, super_admin | Safety & shifts › Safety tasks | `/ops/inspections` | /staff/evidence, /staff/inspections/due, /staff/sessions/{id}/inspections | Implemented | — |
| 52 | ops.my-shifts | staff | Safety & shifts › My shifts | `/ops/my-shifts` | /staff/shifts/mine, /staff/shifts/{id}/check-{id} | Implemented | — |
| 53 | ops.reservations | staff, admin, super_admin | Gate operations › Reservations | `/ops/reservations` | /staff/reservations | Implemented | — |
| 54 | ops.reservations.show | staff, admin, super_admin | Opened from its list page | `/ops/reservations/{id}` | /admin/reservations/{{, /staff/reservations/{{ | Implemented | — |
| 55 | ops.sessions | staff, admin, super_admin | Gate operations › Parked vehicles | `/ops/sessions` | /staff/sessions | Implemented | Yes |
| 56 | ops.sessions.show | staff, admin, super_admin | Opened from its list page | `/ops/sessions/{uuid}` | /admin/penalties/{id}/waive, /staff/payments/{id}/${confirmIt, /staff/penalty-types, /staff/sessions/{id}, /staff/sessions/{id}/bill, /staff/sessions/{id}/confirm-left, /staff/sessions/{id}/gate, /staff/sessions/{id}/gate/exit, /staff/sessions/{id}/inspections, /staff/sessions/{id}/payments, /staff/sessions/{id}/penalties, /staff/sessions/{id}/ticket, /staff/sessions/{id}/ticket/reissue | Implemented | — |
| 57 | ops.vehicles | staff, admin, super_admin | Gate operations › Vehicle register | `/ops/vehicles` | /staff/vehicles | Implemented | — |
| 58 | ops.vehicles.show | staff, admin, super_admin | Opened from its list page | `/ops/vehicles/{id}` | /admin/vehicles/{{, /staff/vehicles/{{ | Implemented | — |
| 59 | register | guest | — | `/register` | Fortify RegisteredUserController@create | Implemented | — |
| 60 | reports.overview | admin, super_admin, auditor | Reports › Overview | `/reports` | /reports/overview | Implemented | — |
| 61 | reports.anpr | admin, super_admin, auditor | Reports › ANPR & cameras | `/reports/anpr` | /reports/anpr/camera-health, /reports/anpr/overview, /reports/anpr/recognition-history | Implemented | — |
| 62 | reports.calendar | admin, super_admin, auditor | Reports › Calendar | `/reports/calendar` | /reports/calendar | Implemented | — |
| 63 | reports.financial | admin, super_admin, auditor | Reports › Financial | `/reports/financial` | /reports/financial | Implemented | — |
| 64 | reports.inspections | admin, super_admin, auditor | Reports › Safety inspections | `/reports/inspections` | /reports/inspections | Implemented | — |
| 65 | reports.occupancy | admin, super_admin, auditor | Reports › Occupancy | `/reports/occupancy` | /reports/occupancy | Implemented | — |
| 66 | reports.payments | admin, super_admin, auditor | Reports › Payments ledger | `/reports/payments` | /reports/payments | Implemented | Yes |
| 67 | reports.sessions | admin, super_admin, auditor | Reports › Session ledger | `/reports/sessions` | /reports/sessions | Implemented | Yes |
| 68 | reports.staff | admin, super_admin, auditor | Reports › Staff performance | `/reports/staff` | /reports/staff-performance | Implemented | — |
| 69 | password.reset | guest | — | `/reset-password/{token}` | Fortify NewPasswordController@create | Implemented | — |
| 70 | dashboard.staff | staff | Overview › Dashboard | `/staff/dashboard` | GET /staff/dashboard (JSON for API clients); /staff/anpr/events, /staff/dashboard, /staff/sessions/expiring | Implemented | Yes |
| 71 | dashboard.super-admin | super_admin | Overview › Dashboard | `/super-admin/dashboard` | GET /super-admin/dashboard (JSON for API clients); /reports/anpr/camera-health, /staff/anpr/events | Implemented | Yes |
| 72 | two-factor.login | guest | — | `/two-factor-challenge` | Fortify TwoFactorAuthenticatedSessionController@create | Implemented | — |
| 73 | password.confirm | guest | — | `/user/confirm-password` | Fortify ConfirmablePasswordController@show | Implemented | — |

Screens: 73. Modals and drawers (create/edit forms, confirmations, slot editor, verification step) live inside these screens and are listed in `USER_JOURNEYS.md`.
