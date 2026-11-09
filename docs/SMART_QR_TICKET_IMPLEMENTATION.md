# Smart QR Ticket implementation

## Design

ParkFlow keeps the existing `SPT1-<48 hex characters>` credential as the privileged staff checkout credential. The QR printed after this change can instead contain a separate 256-bit public capability URL. The public token is stored encrypted for reprints and indexed only by its SHA-256 hash.

The public URL is view-only. It displays parking, duration, estimated/final charge, payment status, and receipt reference. It exposes no customer name, phone number, email, internal IDs, staff actions, payment action, or gate action. Checkout continues through the authenticated staff API and the existing `ExitService` billing, payment, idempotency, inspection, and gate rules.

## Routes and interfaces

- `GET /t/{64-character-lowercase-hex-token}` renders the public ticket. It is the only new public route and is read-only.
- Existing authenticated staff endpoints remain unchanged. Their `qr_payload` field now accepts up to 500 characters so it can receive either a legacy `SPT1` credential or the configured ParkFlow URL.
- Ticket API responses retain `qr_payload` as the legacy value and add `qr_content`, `public_url`, and `instruction`. Existing clients reading `qr_payload` therefore continue to work.

## Configuration

Set `PARKING_PUBLIC_TICKET_URL` to the externally reachable HTTPS origin (and optional deployment base path), for example:

```text
PARKING_PUBLIC_TICKET_URL=https://parking.example.com
```

HTTP is rejected by default. `PARKING_PUBLIC_TICKET_ALLOW_HTTP=true` exists only for a controlled local development environment. If the URL is absent or invalid, ticket printing automatically retains the legacy staff QR payload.

Do not include credentials, query parameters, fragments, or `/t` in the configured base URL. If Laravel configuration is cached, refresh the cache through the normal deployment process after setting the environment value.

## Migration and deployment

The additive migration `2026_11_09_000001_create_public_ticket_tokens_table.php` creates one public token per newly issued QR ticket. It does not alter existing business tables or backfill old tickets. Existing tickets remain valid legacy staff tickets.

Recommended deployment order:

1. Verify a current database backup and normal recovery procedure.
2. Deploy the code with `PARKING_PUBLIC_TICKET_URL` configured.
3. Run the single reviewed migration using the normal controlled deployment account.
4. Build frontend assets and refresh application/config caches as appropriate.
5. Issue a test parking ticket, verify its public view, then verify both its URL and legacy value in authenticated staff checkout preview.

This repository change does not execute the migration.

## Local demo and troubleshooting

For a phone on the same trusted LAN, bind the development server only as deliberately required and configure `PARKING_PUBLIC_TICKET_URL` to the computer's reachable LAN URL. Enable the HTTP override only for that local demo. This feature does not alter Windows Firewall or expose the server automatically.

If a newly printed ticket still contains `SPT1`, check that the migration has been deliberately applied, the public URL parses as an absolute HTTPS URL, and cached configuration has been refreshed. If the public page is unavailable while staff scanning works, verify the public token table and reverse-proxy route. Never put a database password or customer information into the public URL configuration.

Automated feature tests use the PHPUnit-configured SQLite `:memory:` database. Before using a different test command, independently confirm its actual database connection is disposable; never point these tests at `smart_parking` or `smart_parking_restore_test_20261108`.

## Security and operations

- Tokens are cryptographically random and are never accepted from an arbitrary host. Staff URL parsing requires the exact configured scheme, host, port, and base path, with no query string or fragment.
- Public responses are rate-limited and send `no-store`, `no-referrer`, `noindex`, CSP, frame, and content-type protections.
- Reissuing a ticket revokes its old public token. Checkout marks only the privileged staff ticket used so the customer can still see the final status and receipt reference.
- Web-server access logs commonly include URL paths. Configure the reverse proxy and observability stack to redact `/t/*` paths and avoid copying ticket URLs into support logs.
- Treat a public URL like a physical parking ticket: anyone holding it can view the deliberately limited ticket data until it is reissued or deleted under the retention policy.

## Compatibility and rollback

Raw `SPT1` scans remain accepted. Authenticated staff checkout also accepts a trusted ParkFlow public URL and resolves it locally; it never follows the URL or makes an outbound request.

Application rollback is safe: older code ignores the additive table and existing legacy credentials remain valid. Do not drop the table during an incident rollback; remove it only through a separately reviewed retention-aware change after confirming no deployed version uses it.

## Verification checklist

- Public page works on a narrow mobile viewport and has no horizontal overflow.
- Duration advances from the server-provided clock and the page periodically reloads the server-authoritative quote.
- Active tickets show estimated charges; completed sessions show stored final charges and payment state.
- Invalid and revoked tokens disclose no ticket details.
- Raw legacy QR and trusted public URL both reach the same authenticated staff preview calculation.
- Reissue invalidates the old public URL and creates a new one.
- No public route can settle payment, mutate a session, or operate a gate.
