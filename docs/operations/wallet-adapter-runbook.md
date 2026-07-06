# Wallet Adapter Provisioning and Rotation Runbook

Apple Pass Type ID certificates and Google Wallet service-account keys are resolved at
runtime from secret references in `config/wallet.php`. Never store certificate PEM,
private key material, or service-account JSON in the database, repository, `.env` values
printed to logs, or audit metadata.

## Configuration references

| Provider | Config keys | Secret reference env vars |
| --- | --- | --- |
| Apple | `wallet.apple.pass_type_identifier`, `wallet.apple.team_identifier`, `wallet.apple.web_service_base_url` | `WALLET_APPLE_CERT_SECRET_REF`, `WALLET_APPLE_KEY_SECRET_REF` |
| Google | `wallet.google.issuer_id` | `WALLET_GOOGLE_SERVICE_ACCOUNT_SECRET_REF` |

Adapter selection: `WALLET_APPLE_ADAPTER` and `WALLET_GOOGLE_ADAPTER` (`fake`, `apple`,
`google`). Use `fake` for local and CI validation.

## Apple Pass Type ID certificate

### Provisioning

1. Create or select a Pass Type ID in Apple Developer.
2. Generate a Pass Type ID certificate and export the certificate + private key through
   the approved secret store.
3. Store the certificate PEM and private key under `WALLET_APPLE_CERT_SECRET_REF` and
   `WALLET_APPLE_KEY_SECRET_REF` respectively (references only in configuration).
4. Set `WALLET_APPLE_PASS_TYPE_IDENTIFIER`, `WALLET_APPLE_TEAM_IDENTIFIER`, and
   `WALLET_APPLE_WEB_SERVICE_URL` to the production web-service base URL.
5. Run `php artisan zonetec:config:validate` and confirm readiness reports wallet
   adapter reachability without printing secret material.

### Rotation

1. Issue the replacement certificate in the secret store under new reference names.
2. Deploy updated `WALLET_APPLE_CERT_SECRET_REF` / `WALLET_APPLE_KEY_SECRET_REF` to
   the runtime environment.
3. Reload configuration (`php artisan config:cache` in deployed environments).
4. Trigger a controlled wallet pass update on a synthetic attendee and confirm
   `wallet_pass.updated` audit rows contain provider name only, never payload bytes.
5. Retire the previous secret references after the overlap window defined by operations
   policy.

### Compromise response

Disable signing at the secret store immediately, switch adapters to `fake` or block
outbound wallet traffic at the network layer, and mark affected passes for manual
reconciliation. Re-enable only after new references pass readiness.

## Google service-account key

### Provisioning

1. Create a Google Cloud service account with Wallet Objects API access for the issuer.
2. Store the JSON key in the approved secret store; reference it with
   `WALLET_GOOGLE_SERVICE_ACCOUNT_SECRET_REF`.
3. Set `WALLET_GOOGLE_ISSUER_ID` to the issuer configured in Google Wallet Console.
4. Validate with `php artisan zonetec:config:validate` and a synthetic pass generation
   using fake adapters before enabling `google` in production.

### Rotation

1. Create a new service-account key in Google Cloud and stage it under a new secret
   reference.
2. Update `WALLET_GOOGLE_SERVICE_ACCOUNT_SECRET_REF`, reload configuration, and verify
   pass update jobs succeed.
3. Delete the superseded key in Google Cloud after the overlap window.

### Compromise response

Revoke the compromised key in Google Cloud immediately, rotate the secret reference, and
review `wallet_pass.update_failed` / `wallet_pass.revocation_failed` audit rows for blast
radius. Never paste key JSON into tickets, chat, or command output.
