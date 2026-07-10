# Changelog

All notable changes to **Matrixweave for WooCommerce**.

## [1.0.1] — 2026-07-10

### Fixed
- Settings menu now appears on plain WordPress sites **without** WooCommerce.
  The admin menu/page required the `manage_woocommerce` capability, which only
  exists when WooCommerce is installed — administrators on a non-Woo site
  (services/trading/membership businesses using the widget only) never saw the
  Matrixweave menu. The capability now falls back to `manage_options` when
  WooCommerce is inactive (`Matrixweave_Settings::capability()`).

### Docs
- readme.txt FAQ: documented the no-WooCommerce feature set (widget embed and
  signed-in member identity work; order lookups and API-key generation are
  WooCommerce-only; catalogs connect via CSV/Sheet/manual in Matrixweave).

## [1.0.0] — 2026-07-10

Initial release.

### Added
- **Widget auto-embed** — loads the Matrixweave chat on every storefront page
  from just the Public key (`pk_`); no theme edits or snippets.
- **Server-side signed customer identity** — for logged-in customers, calls
  `POST /api/v1/widget/sign-identity` (X-Secret-Key auth, server-to-server),
  caches the HMAC proof per user for 50 minutes (inside the 1-hour signature
  window), and folds `customerEmail` / `identitySignature` / `identityIssuedAt`
  into `Matrixweave.init()` so the AI can answer "where's my order?" for that
  customer only. The Secret key never reaches the browser.
- **wp-config constants** — optional `MATRIXWEAVE_SECRET_KEY` (keeps the secret
  out of the database; locks the admin field) and `MATRIXWEAVE_API_URL`.
- **One-click catalog connection** — generates a read-only (or read/write)
  WooCommerce REST API key with copy buttons, ready to paste into Matrixweave →
  ERP Connections → Add Source → WooCommerce.
- **Connection tester** — verifies the Secret key + API URL from wp-admin.
- **Settings** — embed on/off, order lookups on/off, agent mode
  (`auto`/`sales_agent`/`support_agent`), accent color, greeting, advanced
  API/widget URLs; secret preserved when the field is left blank.
- **Cache hygiene** — signatures purged on logout, profile update, secret/API
  change, deactivation, and uninstall (uninstall also removes the option row).
- **Compatibility** — WooCommerce HPOS + checkout-blocks declared; nonce +
  capability-guarded AJAX; `matrixweave_widget_config` filter for developers.
