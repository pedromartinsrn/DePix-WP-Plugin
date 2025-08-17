# Depix WP Plugin

An open-source WordPress plugin to use DePix on WordPress.

## Overview
This plugin integrates the Depix/Eulen payment flow with WordPress. It securely stores an API token, exposes an admin configuration page, creates a database table for transactions, and provides API/client, webhook, and optional shortcode to test payments.

## Requirements
- WordPress 5.8+
- PHP 7.4+
- OpenSSL PHP extension enabled

## Installation
1) Zip the top-level plugin folder and upload via WP Admin → Plugins → Add New → Upload Plugin. Or copy the folder to `wp-content/plugins/` and activate.
2) Go to Settings → Depix and paste your API token. The token is encrypted with AES-256-GCM using WP salts.
3) Register the webhook at your provider pointing to: `https://your-site/wp-json/depix/v1/webhook`.

## Architecture
- `depixplugin.php`: bootstrap, constants, hooks, textdomain, enqueues.
- `class.depixplugin.php`: orchestrates initialization (service, DB, panel, webhook) and activation/deactivation hooks.
- `src/panel/class.eulenpanel.php`: admin UI, token encryption and storage, connectivity test (Ping).
- `src/services/class.eulen.php`: Depix/Eulen API client (ping, deposit, deposit-status), DB persistence.
- `src/helpers/class.requests.php`: HTTP wrapper (`wp_remote_*`) with Authorization header management.
- `src/services/class.database.php`: DB table creation and CRUD helpers.
- `src/services/class.eulenWebhook.php`: REST route `depix/v1/webhook` for async updates.
- `src/shortcodes/class.shortcode.php`: demo/diagnostic shortcode `[depix_test]` and AJAX polling.
- `assets/`: styles/scripts placeholders.
- `languages/`: translations (text domain `depixplugin`).

## Admin Panel (Settings → Depix)
- Option name: `depix_plugin_api_token_enc_v1`.
- When saving, the token is encrypted (AES-256-GCM) and stored as JSON (`v, alg, iv, tag, ct`).
- Connectivity test: a "Ping API" button calls `/ping` and shows the result.

## Payment Flow
1) Obtain and save the API token in Settings → Depix.
2) Create a deposit by calling `EulenService::deposit($amountInCents)` from your code.
3) Show the `qrCopyPaste` and/or `qrImageUrl` to the customer.
4) Monitor status:
   - via polling (AJAX → `depix_tx_status`), or
   - via webhook (`/wp-json/depix/v1/webhook`) which upserts the transaction row.
5) Treat as final when status is one of: `paid, completed, confirmed, success, depix_sent, expired, canceled, error`.

Minimal example:
```php
$service = new EulenService();
$resp = $service->deposit(1000); // R$ 10,00
// Parse $resp (JSON) for response.id, qrCopyPaste, qrImageUrl
```

## Database
- Table: `{prefix}_depixwp_transactions`
- Columns: `id, tx_id (unique), amount_cents, status, async, qr_copy_paste, qr_image_url, meta, created_at, updated_at`.
- Created on activation via `DepixTablesWP::executeInitialTable()`.

## Webhook
- Route: `POST /wp-json/depix/v1/webhook`
- Handler: `EulenWebhook::handleRequest`
- Permission: `verifyWebhookSignature` (currently returns `true`; implement signature/HMAC validation for production).
- Behavior: normalizes `id/qrId`, updates or inserts the transaction, returns `{ ok, final }`.

## Shortcode (optional)
- `[depix_test]` for demo/testing: renders a form, performs `deposit`, shows QR and starts status polling.
- To enable: uncomment `DepixShortcodes::init();` in `class.depixplugin.php`.

## i18n
- Text domain: `depixplugin`.
- Loaded on `plugins_loaded` from `languages/`.

## Security
- Token encrypted with AES-256-GCM using keys derived from WP SALTs.
- Nonces on demo form.
- `.htaccess` blocks PHP execution in subdirectories and allows static assets.
- Implement `verifyWebhookSignature` for production.

## Uninstallation (suggested)
- Optionally implement `uninstall.php` to delete `depix_plugin_api_token_enc_v1` and (optionally) drop the table.

## License
GPLv2 or later.
