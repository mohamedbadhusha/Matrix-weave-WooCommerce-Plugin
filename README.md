# Matrixweave for WooCommerce

The official **Matrixweave** AI Sales &amp; Support agent plugin for WooCommerce.

It auto-embeds the Matrixweave chat widget on your storefront and — the part that
used to require editing `wp-config.php` and `functions.php` — securely unlocks
**personalized order lookups** so the AI can answer *"where's my order?"* for a
signed-in customer, and only that customer.

## Why this plugin

Matrixweave verifies a customer with a short-lived HMAC signature that must be
computed **server-side** with your tenant **Secret key** (`sk_...`). Doing that
by hand means a `wp-config.php` constant plus a PHP snippet in your theme. This
plugin does it for you:

- **Widget auto-embed** — paste your Public key (`pk_...`); the widget loads on
  every storefront page. No snippet, no theme edits.
- **Signed customer identity** — on each page load for a logged-in customer, the
  plugin calls `POST /api/v1/widget/sign-identity` (secret-key auth,
  server-to-server), caches the proof per customer for 50 minutes, and folds
  `customerEmail` / `identitySignature` / `identityIssuedAt` into
  `Matrixweave.init()`. **The Secret key never reaches the browser.**
- **One-click catalog connection** — generate a read-only WooCommerce REST API
  key from the plugin and paste it into Matrixweave → *ERP Connections → Add
  Source → WooCommerce*.
- **Connection tester** — validate your Secret key + API URL from wp-admin.
- **HPOS + checkout-blocks compatible.**

## How order lookups work

```
Customer logs into WooCommerce
        │
        ▼
wp_footer (this plugin, server-side)
  ├── is_user_logged_in()?  ── no ──▶ load widget for a guest
  └── yes
        ├── POST /api/v1/widget/sign-identity   (X-Secret-Key, cached 50m)
        │     → { email, issuedAt, signature }
        └── Matrixweave.init({ apiKey, apiUrl, customerEmail, identitySignature, identityIssuedAt })
                  │
                  ▼
        Matrixweave verifies the signature → unlocks get_my_orders for that
        customer only (email comes from the verified proof, never model input).
```

## Installation

**Direct download:** grab the ready-made zip from
[matrixweave.com/downloads/matrixweave-for-woocommerce.zip](https://www.matrixweave.com/downloads/matrixweave-for-woocommerce.zip)
(also linked from the [Connect Your Store](https://www.matrixweave.com/docs/connect-store)
and [Install the Widget](https://www.matrixweave.com/docs/install-widget) docs).

To build the zip from source instead: `bash bin/build-zip.sh` → `dist/matrixweave-for-woocommerce.zip`.

1. Install the zip via **Plugins → Add New → Upload Plugin**, then activate.
2. Open the **Matrixweave** admin menu.
3. Paste your **Public key** (`pk_...`) and **Secret key** (`sk_...`) from your
   Matrixweave dashboard (**Settings → Chat Widget**).
4. *(Optional)* Click **Generate WooCommerce API key** and paste the values into
   Matrixweave to connect your product catalog.
5. Save — the chat is live on your store.

### Keeping the Secret key out of the database (optional)

Define it in `wp-config.php` instead; the plugin will use the constant and lock
the admin field:

```php
define( 'MATRIXWEAVE_SECRET_KEY', 'sk_your_secret_key' );
// Optional, for self-hosted API:
// define( 'MATRIXWEAVE_API_URL', 'https://api.matrixweave.com' );
```

## Settings

| Setting | What it does |
|---------|--------------|
| Public key | `pk_...` — loads the widget (safe in the browser). |
| Secret key | `sk_...` — signs signed-in customers, server-side only. |
| Embed the widget | Auto-load the chat on the storefront. Turn off if you embed it yourself. |
| Personalized order lookups | Enable signed identity for logged-in customers. |
| Agent mode | `auto` / `sales_agent` / `support_agent`. |
| Accent color / Greeting | Optional overrides for the dashboard values. |
| API URL / Widget script URL | Advanced — for self-hosted deployments. |

## Requirements

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 5.0+ (required for order lookups and API-key generation; the
  widget alone works without it)

## Developer notes

- `matrixweave_widget_config` filter — modify the final `Matrixweave.init()`
  config before it is printed.
- Cached identity signatures are stored as per-user transients
  (`mw_identity_{userId}`) and purged on logout, profile update, secret/API
  change, deactivation, and uninstall.

## License

GPL-2.0-or-later.
