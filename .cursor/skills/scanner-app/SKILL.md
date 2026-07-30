---
name: scanner-app
description: >-
  Integrate or extend the external zone scanner app against Zoon's
  /api/v1/scanner-app login and scan APIs. Use when building mobile scanner
  clients, debugging zone-code auth, or documenting scanner-app endpoints.
---

# Scanner App API

External zone scanners authenticate with an **8-digit zone `scanner_code`**, then call scan endpoints with a session token. Behavior matches the web page `/tenant/events/{event_id}/scanner`.

## Base URL

`/api/v1/scanner-app`

## Auth flow

1. `POST /login` with `scanner_code` only (exactly 8 digits). The API resolves the zone and its event from that code.
2. Store returned `token`.
3. Send `Authorization: ScannerApp {token}` on every later request.
4. `POST /logout` to revoke.

## Endpoints

### Login

`POST /api/v1/scanner-app/login`

```json
{ "scanner_code": "48291037" }
```

Response includes `token`, `expires_at`, `authorization`, `event`, `zone`.

`scanner_code` is **globally unique** across all events.

### Me

`GET /api/v1/scanner-app/me`  
Header: `Authorization: ScannerApp {token}`

### Scan

`POST /api/v1/scanner-app/scan`  
Headers: `Authorization: ScannerApp {token}`, `Idempotency-Key: {uuid}`

```json
{ "qr_payload": "..." }
```

or `{ "credential_id": "..." }`

Uses the same `SubmitScanAction` path as the web scanner and attributes the scan to the session's `EventZone`.

### Logout

`POST /api/v1/scanner-app/logout`  
Header: `Authorization: ScannerApp {token}`

## Errors

- `scanner_app_zone_not_found` — unknown scanner code
- `scanner_app_session_invalid` — missing/expired/revoked token

## Zone codes

Organizers set/edit `scanner_code` on venue zones (create/edit venue or map editor). Web deep link: `/tenant/events/{id}/scanner?code=XXXXXXXX`.
