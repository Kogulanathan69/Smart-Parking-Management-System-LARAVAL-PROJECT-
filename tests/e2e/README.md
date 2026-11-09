# ParkFlow browser E2E journeys

Plain Node + Playwright (library). Run against a **disposable** database seeded with demo data — never production.

```bash
# 1. disposable DB + demo accounts (all demo users share SEED_DEMO_PASSWORD)
php artisan migrate:fresh --seed            # on the test database only
php artisan parking:create-super-admin --name="E2E Super" --email=super_admin@smart-parking.test
php artisan serve                            # or Herd

# 2. run
E2E_BASE_URL=http://127.0.0.1:8000 E2E_PASSWORD='<SEED_DEMO_PASSWORD>' node tests/e2e/journeys.mjs
```

* The fixture images are **synthetic** (drawn plates on a plain background), not photos of real vehicles.
* If the local ANPR engine is offline, the Check-In journey uses manual identification instead of upload.
* Screenshots are written to `tests/e2e/output/` (git-ignored).
