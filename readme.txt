=== Matrixweave for WooCommerce ===
Contributors: matrixweave
Tags: woocommerce, ai, chatbot, sales agent, customer support, live chat, order tracking
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The official Matrixweave AI Sales & Support agent for WooCommerce. Auto-embeds the chat and securely unlocks personalized order lookups — no theme code.

== Description ==

**Matrixweave for WooCommerce** connects your store to your Matrixweave AI agent in a few clicks.

It solves the one thing that used to require editing `wp-config.php` and `functions.php`: letting the AI answer **"where's my order?"** for a signed-in customer — safely.

= What it does =

* **One-line install** — paste your Public key, and the chat widget loads on your storefront automatically. No snippet, no theme edits.
* **Secure order lookups** — when a customer is logged in, the plugin signs their identity **on your server** (HMAC, keyed by your Secret key) and hands the proof to the widget. The AI can then look up **that customer's** orders — and only theirs. Your Secret key never reaches the browser.
* **One-click catalog connection** — generate a read-only WooCommerce REST API key from the plugin, then paste it into Matrixweave. No hunting through WooCommerce settings.
* **Connection tester** — verify your Secret key and API endpoint from the WordPress admin.
* **Appearance & behaviour** — set the agent mode, accent color and greeting, or defer to your dashboard settings.

= How the identity signing works =

1. A customer signs into your WooCommerce store as usual.
2. On each page load, the plugin asks the Matrixweave API to sign the logged-in customer's email using your Secret key (server-to-server). The signed proof is cached per customer for 50 minutes.
3. The signed values are folded into `Matrixweave.init()` so the widget can prove who the customer is. The AI unlocks order history for that verified customer only.

The Secret key is stored on your server and, optionally, can be provided via a `MATRIXWEAVE_SECRET_KEY` constant in `wp-config.php` to keep it out of the database entirely.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` (or install the ZIP via **Plugins → Add New → Upload**).
2. Activate it through the **Plugins** screen.
3. Go to **Matrixweave** in the admin menu.
4. Paste your **Public key** (`pk_...`) and, for order lookups, your **Secret key** (`sk_...`) from your Matrixweave dashboard (**Settings → Chat Widget**).
5. (Optional) Click **Generate WooCommerce API key** and paste the values into Matrixweave → **ERP Connections → Add Source → WooCommerce** to connect your catalog.
6. Save. Open your store — the chat is live.

== Frequently Asked Questions ==

= Is my Secret key exposed to visitors? =
No. The Secret key is used only server-side to sign identities. It is never enqueued, printed, or sent to the browser. You can also define it as a `MATRIXWEAVE_SECRET_KEY` constant in `wp-config.php`.

= Do I still need to add the widget snippet manually? =
No. Leave **Embed the widget** on and the plugin loads it for you. If you already embed it yourself, turn that option off to avoid loading it twice.

= Does this work with High-Performance Order Storage (HPOS)? =
Yes. The plugin declares HPOS and checkout-blocks compatibility and only reads customer session data.

= Can guests use the chat? =
Yes. Guests get the normal AI agent. Order lookups only activate for logged-in customers whose identity can be verified.

= Does it work without WooCommerce (plain WordPress)? =
Yes. On a plain WordPress site — a services, trading or membership business — the widget embed and signed-in member identity (greet by name, personalized memory) work exactly the same; the plugin settings appear for administrators. Only the one-click API-key generation and order lookups need WooCommerce. Connect your catalog/price list in Matrixweave via CSV, Google Sheet or manual entry instead.

== Changelog ==

= 1.0.2 =
* Fix: "Test order-lookup connection" now tests the key currently typed in the field (not only the previously saved one) — paste and Test works before Save.
* Fix: the "Allow the agent to create orders (Read/Write)" checkbox is remembered across saves and page reloads (also persisted when you generate a key).
* Perf: a failed identity signing (bad key / API unreachable) is cached for 5 minutes instead of retrying on every page load for logged-in users.

= 1.0.1 =
* Fix: settings menu now appears on plain WordPress sites without WooCommerce (capability falls back to administrators).

= 1.0.0 =
* Initial release: widget auto-embed, server-side signed customer identity for order lookups, one-click WooCommerce REST API key generation, connection tester, HPOS compatibility.
