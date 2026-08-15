=== AI Chatbot, Live Chat & Sales Agent – Matrixweave ===
Contributors: mugamathubathusha
Tags: ai chatbot, live chat, sales agent, customer support, woocommerce
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI chatbot and live chat for any WordPress site — and on WooCommerce, a sales agent that answers your customers' order questions securely.

== Description ==

**Matrixweave** adds an AI chatbot to your site in a few clicks. It greets visitors, answers questions about what you sell, and hands the conversation to your team when a human is needed.

It works on **any WordPress site** — a services business, a clinic, a trading company. Install it, press **Connect Matrixweave**, and the chat is live on the front end with no theme edits and no snippet to copy.

On a **WooCommerce store** it becomes a sales and support agent. It solves the one thing that used to require editing `wp-config.php` and `functions.php`: letting the AI answer **"where's my order?"** for a signed-in customer — safely.

= What it does =

* **One-click setup** — press Connect, sign in, and you are sent straight back with everything wired up. No keys to copy, no snippet, no theme edits.
* **Secure order lookups** — when a customer is logged in, the plugin signs their identity **on your server** (HMAC, keyed by your Secret key) and hands the proof to the widget. The AI can then look up **that customer's** orders — and only theirs. Your Secret key never reaches the browser.
* **Automatic catalog connection** — connecting also creates a read-only WooCommerce REST API key and hands it over for you, so your products and orders sync with nothing to paste.
* **Connection tester** — verify your Secret key and API endpoint from the WordPress admin.
* **Appearance & behaviour** — set the agent mode, accent color and greeting, or defer to your dashboard settings.
* **Wishlist-aware replies** — when the YITH WooCommerce Wishlist plugin is active, a verified signed-in customer's saved product names are passed along so the agent can reference them. See the third-party service notice below for exactly what is sent.

= How the identity signing works =

1. A customer signs into your WooCommerce store as usual.
2. On each page load, the plugin asks the Matrixweave API to sign the logged-in customer's email using your Secret key (server-to-server). The signed proof is cached per customer for 50 minutes.
3. The signed values are folded into `Matrixweave.init()` so the widget can prove who the customer is. The AI unlocks order history for that verified customer only.

The Secret key is stored on your server and, optionally, can be provided via a `MATRIXWEAVE_SECRET_KEY` constant in `wp-config.php` to keep it out of the database entirely.

= Third-party service (Matrixweave) =

This plugin is a connector to **Matrixweave**, a third-party AI service that powers the chat agent. The AI runs on Matrixweave's servers, so the plugin requires a Matrixweave account and sends data to Matrixweave to work:

* The chat widget script is loaded from `https://www.matrixweave.com/widget.js` on pages where the chat is shown.
* Messages that visitors type in the chat are sent to the Matrixweave API (`https://api.matrixweave.com`) to generate the AI's replies.
* For a signed-in customer, the plugin sends that customer's email address to the Matrixweave API (server-to-server, keyed by your Secret key) to obtain a signed proof, so the AI can look up that customer's own orders.
* Alongside that signed proof, the widget also passes the signed-in customer's **display name** (so the agent can greet them by name) and — only if the YITH WooCommerce Wishlist plugin is active — the **product names on their wishlist** (newest 10) to the Matrixweave API, so the agent can reference items they saved. Both are sent only for logged-in customers, only when "Personalized order lookups" is enabled, and never for guests.
* If you connect your catalog, the plugin generates a **read-only** WooCommerce REST API key on your own site and sends it to the Matrixweave API; Matrixweave then reads your products and orders through the standard WooCommerce REST API to answer product and order questions. The key is read-only and cannot change anything on your store.
* When you press **Connect Matrixweave**, the plugin sends you to `https://www.matrixweave.com/connect` with your site's address and a random one-time value, so the account you sign in to there can be linked back to this site. Nothing is sent until you press that button — activating the plugin contacts nothing.

By installing the plugin and connecting your account you consent to this. Please review Matrixweave's terms and privacy policy:

* Service: [https://www.matrixweave.com](https://www.matrixweave.com)
* Terms of Service: [https://www.matrixweave.com/terms](https://www.matrixweave.com/terms)
* Privacy Policy: [https://www.matrixweave.com/privacy](https://www.matrixweave.com/privacy)

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` (or install the ZIP via **Plugins → Add New → Upload**).
2. Activate it through the **Plugins** screen.
3. Go to **Matrixweave** in the admin menu.
4. Click **Connect Matrixweave**. Sign in, or create a free account, and you are sent straight back — your keys, the chat widget and your product and order sync are all set up for you.

That is the whole setup. There is nothing to copy and paste.

If you would rather do it by hand — moving a site between workspaces, or a host that blocks the round trip — you can still paste your **Public key** (`pk_...`) and **Secret key** (`sk_...`) into the keys section, and use **Generate WooCommerce API key** to connect your catalog manually.

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

== Screenshots ==

1. One button connects the store — the panel then reports how many products synced and whether order lookups really work.
2. Everything on one screen: agent mode, accent color and greeting, plus one-click generation of a WooCommerce REST API key to connect your catalog.
3. The chat agent on your storefront. Signed-in customers can ask "where's my order?" and get an answer for their own orders only.

== Changelog ==

= 1.2.0 =
* **One-click setup.** A new **Connect Matrixweave** button replaces the whole manual flow: sign in (or create a free account), and you are sent straight back with your keys stored, the chat widget live, and your products and orders syncing. Nothing to copy, nothing to paste.
* The settings screen now reports what Matrixweave can actually see of your store — how many products have synced, and whether order lookups really work — instead of a stored "connected" flag that stayed green through revoked credentials or an empty catalog.
* Your plan and this month's AI chat usage are shown in wp-admin, so you no longer have to open the dashboard to check whether you are near your limit.
* The catalog key the plugin creates is now always **read-only**. Nothing in this product writes to your store, so nothing needs permission to.
* Manual key entry is still there for moving a site between workspaces, or hosts that block the round trip.

= 1.1.4 =
* Renamed to "AI Chatbot, Live Chat & Sales Agent – Matrixweave". The plugin works on any WordPress site, not only WooCommerce stores, and the old name hid that.
* Rewrote the description so it says up front what works everywhere and what needs WooCommerce.
* Swapped the "order tracking" tag for "live chat".
* Nothing changed in the code: same settings, same option name, same admin menu, same install folder.

= 1.1.3 =
* Renamed the plugin listing to "AI Chatbot & Sales Agent for WooCommerce – Matrixweave" so it says what it does. Nothing changed inside the plugin: same settings, same option names, same admin menu, and the install folder and slug are unchanged.
* Added screenshots of the settings screen and the chat agent running on a storefront.

= 1.1.2 =
* Naming: every transient the plugin stores is now prefixed with `matrixweave_` (previously `mw_`), so it cannot collide with another plugin's cached data. Admin field IDs use the same full prefix. No change to behaviour or settings.
* Docs: the third-party service notice now lists every piece of customer data sent to Matrixweave — the signed-in customer's display name and wishlist product names are named explicitly alongside the email address.

= 1.1.1 =
* WordPress.org readiness: the chat loader now enqueues the standard WordPress way, translations auto-load, and the code passes the official Plugin Check. Added a third-party service (Matrixweave) disclosure with Terms/Privacy links. Marked Tested up to 7.0 and tidied the readme. No change to behaviour.

= 1.1.0 =
* New: wishlist-aware AI suggestions. When the YITH WooCommerce Wishlist plugin is active, the signed-in customer's wishlist product names (max 10, cached 10 minutes) are passed to the AI alongside their verified identity — the agent can say "I noticed X is on your wishlist — it's in stock right now". No wishlist plugin, no change.

= 1.0.3 =
* Fix: identity signing and the connection tester rejected SUCCESSFUL API responses — the API answers 201 for the signing call while the plugin demanded exactly 200, so order lookups never activated and "Test order-lookup connection" always failed even with a valid Secret key. Any 2xx is now accepted.

= 1.0.2 =
* Fix: "Test order-lookup connection" now tests the key currently typed in the field (not only the previously saved one) — paste and Test works before Save.
* Fix: the "Allow the agent to create orders (Read/Write)" checkbox is remembered across saves and page reloads (also persisted when you generate a key).
* Perf: a failed identity signing (bad key / API unreachable) is cached for 5 minutes instead of retrying on every page load for logged-in users.

= 1.0.1 =
* Fix: settings menu now appears on plain WordPress sites without WooCommerce (capability falls back to administrators).

= 1.0.0 =
* Initial release: widget auto-embed, server-side signed customer identity for order lookups, one-click WooCommerce REST API key generation, connection tester, HPOS compatibility.
