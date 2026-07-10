# Kiosk and Badge Printing Operations Runbook

This runbook covers kiosk lifecycle management (pairing, confirmation, retirement) and
badge print/reprint operations. Follow this guide for day-of-event setup, troubleshooting,
and incident response.

## Kiosk Pairing

1. **Register the kiosk** via `POST /api/v1/tenant/events/{event_id}/kiosks` with the
   device name and optional location label. Requires `kiosk.manage` permission.
2. **Retrieve the pairing code** from the API response (`pairing_code`). Display this
   on the organizer admin screen.
3. **Enter the code** on the kiosk device. The device calls
   `POST /api/v1/kiosk/pair` with the code to receive a long-lived session token
   (`KIOSK_SESSION_TTL_HOURS`, default 168 h).
4. **Confirm the session** if `confirmation_required = true`: the device calls
   `POST /api/v1/kiosk/confirm-session` with the confirmation code. The kiosk moves
   from `pending` → `online` status after a successful heartbeat.

### Heartbeats

Kiosks must call `POST /api/v1/kiosk/heartbeat` with current `printer_status` at regular
intervals (recommended ≤ 60 s). Kiosks silent beyond
`EventCheckInSetting.kiosk_offline_threshold_seconds` are shown as `offline` in the
health table.

### Status States

| Status    | Meaning                                          |
|-----------|--------------------------------------------------|
| `pending` | Registered but not yet confirmed                 |
| `online`  | Heartbeat within threshold, printer ok           |
| `degraded`| Heartbeat within threshold but printer is `error`|
| `offline` | No heartbeat within threshold                    |
| `retired` | Permanently decommissioned                       |

## Retiring a Kiosk

Call `DELETE /api/v1/tenant/events/{event_id}/kiosks/{kiosk_id}` (requires `kiosk.manage`).
The kiosk's session is invalidated immediately. Retired kiosks cannot be re-paired; register
a new kiosk record instead.

## Badge Print and Reprint

### Printing a Badge

1. Ensure an `active` badge template exists for the event.
2. Call `POST /api/v1/tenant/events/{event_id}/badge-print-jobs` with
   `attendee_id`, optionally `credential_id`, and `template_id`.
3. The system renders the template, dispatches to the `PrinterAdapter`, and records
   a `BadgePrintJob` with status `printed` or `failed`.

### Reprinting a Badge

1. Obtain the original `badge_print_job_id` from the attendee record.
2. Call `POST /api/v1/tenant/events/{event_id}/badge-print-jobs/{id}/reprint`
   with `reason` (required, max 500 characters).
3. If `EventCheckInSetting.reprint_revokes_old_qr = true`, the attendee's
   credential is re-issued; the previous QR code is revoked.

### Failed Print Jobs

A `failed` status means the `PrinterAdapter` returned an error. The `BadgePrintJob`
record is preserved for audit. Re-initiate printing by issuing a new reprint request.
Set `FakePrinterAdapter::forceHealth('disconnected')` in tests to simulate failures.

## Troubleshooting

| Symptom                        | Check                                                  |
|--------------------------------|--------------------------------------------------------|
| Kiosk stuck in `pending`       | Confirm session via `POST /api/v1/kiosk/confirm-session` |
| Kiosk shows `offline`          | Check heartbeat frequency vs `kiosk_offline_threshold_seconds` |
| Badge print returns `failed`   | Check `PRINTER_ADAPTER` env var and adapter logs       |
| Reprint denied                 | Verify caller has `badge.reprint` permission           |
| Walk-up registration denied    | Verify `walk_up_registration_enabled = true` on event  |
