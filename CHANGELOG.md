# Changelog

All notable changes to **Matrixweave for WooCommerce**.

## [1.1.4] — 2026-08-13

### Changed
- **Renamed again, to `AI Chatbot, Live Chat & Sales Agent – Matrixweave`.**
  1.1.3 put the keywords first but kept "for WooCommerce", which reads as a
  requirement — yet the widget and signed-in identity work on any WordPress
  site; only order lookups and REST-key generation need WooCommerce. Naming a
  platform also narrows reach: "live chat" and "ai chatbot" are far higher
  volume than anything containing "woocommerce". This matches how Tidio and
  Crisp name their listings.
  - "WordPress" cannot appear in a plugin name (Foundation trademark policy;
    reviewers ask for its removal), so the alternative of naming both platforms
    would have had to use "WP".
  - The slug stays `matrixweave-for-woocommerce` — permanent — and the
    `woocommerce` tag remains, so store-owner searches still match.
- Rewrote the description opening: it now states plainly what works on any
  WordPress site and what is WooCommerce-only, instead of burying that in an
  FAQ near the bottom of the page.
- Tags: dropped `order tracking` (narrow, already implied) for `live chat`.
- Ships the mascot icon, which the 1.1.3 asset commits could not surface —
  WP.org pinned the page to `?rev=` of the first assets commit, and only a
  release appears to force a re-scan.
- No code changes.

## [1.1.3] — 2026-08-13

### Changed
- **Renamed the directory listing** to `AI Chatbot & Sales Agent for WooCommerce
  – Matrixweave`. The old title carried no search terms merchants actually type
  ("ai chatbot", "chatbot woocommerce", "ai agent"), and with no brand
  recognition yet that cost essentially all directory search traffic. Changed
  now, at fewer than 10 installs, when it is free to do.
  - Only the **display name** changed — `Plugin Name:` in the header and the
    `=== … ===` line in `readme.txt`.
  - The slug, install folder, text domain, option name, constants, classes and
    admin menu label are all untouched, so this is not a breaking change and
    needs no migration.
- Published the directory screenshots captured on a clean WordPress 7.0.4 +
  WooCommerce install; the `== Screenshots ==` captions added in 1.1.2 become
  visible with this release, since the directory reads the readme from the
  stable tag.

## [1.1.2] — 2026-08-12

### Changed
- Plugin review follow-up: all stored data now uses the plugin's full, distinct
  prefix so it cannot collide with another plugin or theme.
  - Transients renamed `mw_identity_*` → `matrixweave_identity_*`,
    `mw_identity_fail` → `matrixweave_identity_fail`, and
    `mw_wishlist_*` → `matrixweave_wishlist_*` (the wishlist prefix is now the
    `Matrixweave_Widget::WISHLIST_CACHE_PREFIX` constant).
  - `uninstall.php` cleans up both transient families using
    `$wpdb->prepare()` + `esc_like()` instead of a hand-escaped `LIKE`.
  - Admin field IDs (`mw_secret_key`, `mw-generate-key`, …) and the inline
    widget bootstrap function renamed to the `matrixweave` prefix.
- The stored option (`matrixweave_settings`), all constants, classes, hooks and
  the AJAX actions were already fully prefixed and are unchanged — no migration
  is needed. The old transients are short-lived caches (5–50 min) and simply
  expire.
- `readme.txt`: the third-party service notice now enumerates **all** customer
  data sent to Matrixweave. It previously named only the email address, while
  the widget also passes the signed-in customer's display name (`customerName`)
  and, with YITH Wishlist active, their wishlist product names
  (`customerWishlist`). Wishlist-aware replies are now listed as a feature in
  the description too, instead of only in the 1.1.0 changelog entry.
- Refreshed the `languages/*.pot` version stamp.

## [1.1.1] — 2026-07-28

### Changed
- WordPress.org directory compliance — passes the official Plugin Check:
  - Widget loader now enqueues via `wp_enqueue_script()` + `wp_add_inline_script()`
    instead of a hand-printed `<script>` tag.
  - Removed the discouraged manual `load_plugin_textdomain()` call (WordPress
    auto-loads translations for directory-hosted plugins).
  - Prefixed admin-view variables; annotated the nonce-verified-in-shared-guard
    AJAX handlers and the safe direct-DB wishlist read.
  - `readme.txt`: **Tested up to 7.0**, trimmed to 5 tags, shortened the short
    description; added a `languages/*.pot` translation template.
- No change to plugin behaviour, the widget, or the signed-identity order flow.

## [1.1.0] — 2026-07-11

### Added
- **Wishlist-aware AI suggestions.** When the YITH WooCommerce Wishlist plugin
  is installed, the plugin reads the signed-in customer's wishlist (newest 10,
  direct table read — works with YITH free and premium; per-user 10-minute
  transient cache) and folds the product names into `Matrixweave.init()` as
  `customerWishlist`. The widget forwards it ONLY together with the signed
  identity, and the API only honors it when the identity verifies — so the
  agent can personalize ("I noticed X is on your wishlist — it's in stock")
  without any spoofing surface. Sites without YITH are unaffected.

## [1.0.3] — 2026-07-11

### Fixed
- **Signing succeeded but the plugin threw the result away.** The Matrixweave
  API answers `201 Created` for `POST /widget/sign-identity` (framework
  default), while `Matrixweave_API` demanded exactly `200` — so every install
  ≤ 1.0.2 silently rejected VALID signatures: order lookups never activated
  and "Test order-lookup connection" always reported *"Could not verify the
  secret key"* even with a perfectly good key. The client now accepts any
  2xx. (The API was also pinned to answer 200 going forward, so already-
  installed older plugin versions start working too.)

## [1.0.2] — 2026-07-11

### Fixed
- **"Test order-lookup connection" now tests what you typed.** It used to test
  only the previously *saved* secret key, so the natural "paste key → Test"
  flow failed with *"Could not verify the secret key"* until the user
  remembered to click Save changes first. The test now posts the current
  Secret key + API URL field values (blank falls back to the saved ones), and
  a successful test of an unsaved key reminds you to click Save.
- **Read/Write checkbox persists.** "Allow the agent to create orders
  (Read/Write)" in the Connect-your-catalog box reset on every save/reload
  because it lives outside the settings form. It is now stored as a real
  setting via a hidden in-form mirror (synced by admin.js) and is also
  persisted immediately when you click Generate.

### Performance
- **Failed identity signing is negative-cached for 5 minutes.** With a wrong
  secret key (or the API unreachable), every page load for a logged-in user
  used to retry the server-side signing call in `wp_footer` — up to 8s of
  added load time per view. Failures now set a short-lived flag; the flag is
  cleared automatically when the key or API URL is changed and saved.

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
