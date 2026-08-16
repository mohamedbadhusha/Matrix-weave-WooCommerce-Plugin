<?php
/**
 * One-click connection to Matrixweave.
 *
 * Replaces the old four-step setup — sign up on matrixweave.com, paste the
 * public key, paste the secret key, then generate a WooCommerce REST key and
 * paste its two halves into the dashboard — with a single button.
 *
 * The flow, same shape Jetpack and WooCommerce.com use:
 *
 *   1. This site mints a nonce, keeps it in its own options, and sends the
 *      merchant to matrixweave.com/connect.
 *   2. They sign in (or sign up) and press one button.
 *   3. They come back to this screen carrying a short-lived one-time code.
 *   4. This site exchanges that code, server to server, for its workspace keys,
 *      then generates a read-only WooCommerce REST key and hands it over.
 *
 * ⚠️ The plugin's source ships to every install, so it can hold no shared
 * platform credential. The nonce is what makes the code safe: it never leaves
 * this server, and the API refuses a claim whose nonce or site URL does not
 * match the one that started the flow.
 *
 * @package Matrixweave
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Matrixweave_Connect
 */
class Matrixweave_Connect {

	/** Option holding the pending handshake: nonce + when it started. */
	const PENDING_OPTION = 'matrixweave_connect_pending';

	/** Query arg the dashboard sends the merchant back with. */
	const CODE_ARG = 'mw_code';

	/** A handshake left unfinished this long is abandoned, not resumed. */
	const PENDING_TTL = 900; // 15 minutes.

	/**
	 * Settings handler.
	 *
	 * @var Matrixweave_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Matrixweave_Settings $settings Settings handler.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_init', array( $this, 'maybe_start' ) );
		add_action( 'admin_init', array( $this, 'maybe_finish' ) );
		add_action( 'admin_init', array( $this, 'maybe_disconnect' ) );
	}

	/**
	 * Is this site already linked to a workspace?
	 *
	 * @return bool
	 */
	public function is_connected() {
		return '' !== trim( (string) $this->settings->get_public_key() )
			&& '' !== trim( (string) $this->settings->get_secret_key() );
	}

	/**
	 * URL of our own settings screen.
	 *
	 * @return string
	 */
	public function settings_url() {
		return admin_url( 'admin.php?page=' . Matrixweave_Settings::PAGE_SLUG );
	}

	/**
	 * Step 1 — send the merchant to matrixweave.com to authorise.
	 *
	 * Deliberately triggered by a link on our own settings page, never
	 * automatically on activation: a plugin that phones home the moment it is
	 * switched on is both rude and against WordPress.org's guidelines.
	 *
	 * @return void
	 */
	public function maybe_start() {
		if ( ! isset( $_GET['matrixweave_connect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified immediately below.
			return;
		}
		if ( ! $this->can_manage() ) {
			return;
		}
		check_admin_referer( 'matrixweave_connect' );

		$nonce = wp_generate_password( 32, false, false );
		update_option(
			self::PENDING_OPTION,
			array(
				'nonce'      => $nonce,
				'started_at' => time(),
			),
			false // Not autoloaded: transient state, read once.
		);

		// The return address carries a WordPress nonce of our own. The one-time
		// code is already useless to anyone else — the API re-checks the nonce
		// above, which never leaves this server — but without this, a CSRF'd
		// admin visit to ?mw_code=anything would still *consume* the pending
		// handshake and make the merchant start over.
		//
		// NOT wp_nonce_url(): that escapes the separator to `&amp;` because it
		// is built for printing into HTML. Here the URL travels as DATA — the
		// dashboard hands it back to the browser — so the escaped form returns
		// a parameter literally named `amp;_wpnonce`, check_admin_referer fails,
		// and the connect flow breaks every single time. Verified against a real
		// WordPress before this line looked like this.
		$return = add_query_arg(
			'_wpnonce',
			wp_create_nonce( 'matrixweave_finish' ),
			$this->settings_url()
		);

		// add_query_arg does not encode: WordPress documents that the caller
		// must pass values already encoded. These are whole URLs, so they must.
		$url = add_query_arg(
			array(
				'site'     => rawurlencode( $this->site_url() ),
				'nonce'    => rawurlencode( $nonce ),
				'redirect' => rawurlencode( $return ),
				'platform' => 'WOOCOMMERCE',
			),
			$this->dashboard_url() . '/connect'
		);

		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Deliberately off-site: this is the authorisation hop.
		exit;
	}

	/**
	 * Step 4 — the merchant is back with a code. Exchange it, then wire up the
	 * catalog.
	 *
	 * @return void
	 */
	public function maybe_finish() {
		if ( ! isset( $_GET[ self::CODE_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified immediately below.
			return;
		}
		if ( ! $this->can_manage() ) {
			return;
		}
		// The dashboard returns to the address we gave it, nonce and all, so a
		// genuine round trip always carries this. Anything else is not one.
		check_admin_referer( 'matrixweave_finish' );

		$code = sanitize_text_field( wp_unslash( $_GET[ self::CODE_ARG ] ) );
		$this->finish( $code );

		// Drop the code from the address bar so a refresh cannot replay it and
		// so it never lands in browser history or a screenshot.
		wp_safe_redirect( $this->settings_url() );
		exit;
	}

	/**
	 * Undo the link, on the merchant's own instruction.
	 *
	 * Clears the keys held here. It does NOT delete their workspace or their
	 * conversations — that is theirs, and lives on matrixweave.com.
	 *
	 * @return void
	 */
	public function maybe_disconnect() {
		if ( ! isset( $_GET['matrixweave_disconnect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified immediately below.
			return;
		}
		if ( ! $this->can_manage() ) {
			return;
		}
		check_admin_referer( 'matrixweave_disconnect' );

		$stored = get_option( Matrixweave_Settings::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();
		unset( $stored['public_key'], $stored['secret_key'] );
		update_option( Matrixweave_Settings::OPTION_KEY, $stored );
		delete_option( self::PENDING_OPTION );

		$this->notice( 'success', __( 'Disconnected. Your Matrixweave workspace and its history are untouched — reconnect any time.', 'matrixweave-for-woocommerce' ) );
		wp_safe_redirect( $this->settings_url() );
		exit;
	}

	/**
	 * Exchange the code and store the keys, then connect the catalog.
	 *
	 * @param string $code One-time code.
	 * @return void
	 */
	private function finish( $code ) {
		$pending = get_option( self::PENDING_OPTION, array() );
		$pending = is_array( $pending ) ? $pending : array();
		$nonce   = isset( $pending['nonce'] ) ? (string) $pending['nonce'] : '';
		$started = isset( $pending['started_at'] ) ? (int) $pending['started_at'] : 0;

		if ( '' === $nonce || ( time() - $started ) > self::PENDING_TTL ) {
			delete_option( self::PENDING_OPTION );
			$this->notice( 'error', __( 'That connection attempt expired. Please click Connect again.', 'matrixweave-for-woocommerce' ) );
			return;
		}

		$api    = new Matrixweave_API( $this->settings->get_api_url(), '' );
		$result = $api->claim_site_connect( $code, $nonce, $this->site_url() );

		// Single use either way: a code that has been presented is spent, and
		// leaving the nonce behind would let a stale one be retried.
		delete_option( self::PENDING_OPTION );

		if ( empty( $result['success'] ) ) {
			$this->notice( 'error', $result['message'] );
			return;
		}

		$data       = isset( $result['data'] ) ? $result['data'] : array();
		$public_key = isset( $data['publicKey'] ) ? (string) $data['publicKey'] : '';
		$secret_key = isset( $data['secretKey'] ) ? (string) $data['secretKey'] : '';

		if ( '' === $public_key || '' === $secret_key ) {
			$this->notice( 'error', __( 'Matrixweave did not return the workspace keys. Please try connecting again.', 'matrixweave-for-woocommerce' ) );
			return;
		}

		$stored               = get_option( Matrixweave_Settings::OPTION_KEY, array() );
		$stored               = is_array( $stored ) ? $stored : array();
		$stored['public_key'] = $public_key;
		$stored['secret_key'] = $secret_key;
		update_option( Matrixweave_Settings::OPTION_KEY, $stored );

		$workspace = isset( $data['workspaceName'] ) ? (string) $data['workspaceName'] : '';
		$catalog   = $this->connect_catalog( $secret_key );

		if ( $catalog['success'] ) {
			$this->notice(
				'success',
				$workspace
					/* translators: %s: workspace name. */
					? sprintf( __( 'Connected to %s. Your products and orders are syncing now.', 'matrixweave-for-woocommerce' ), $workspace )
					: __( 'Connected. Your products and orders are syncing now.', 'matrixweave-for-woocommerce' )
			);
			return;
		}

		// The workspace link succeeded; only the catalog did not. Say exactly
		// that — the chat widget already works, and telling the merchant the
		// whole thing failed would be wrong and would send them round again.
		$this->notice(
			'warning',
			/* translators: %s: reason the catalog connection failed. */
			sprintf( __( 'Connected, but your catalog could not be linked yet: %s You can retry from this page.', 'matrixweave-for-woocommerce' ), $catalog['message'] )
		);
	}

	/**
	 * Generate a read-only WooCommerce REST key and hand it to Matrixweave.
	 *
	 * @param string $secret_key The workspace secret key just received.
	 * @return array { success: bool, message: string }
	 */
	public function connect_catalog( $secret_key ) {
		if ( ! function_exists( 'wc_rand_hash' ) || ! function_exists( 'wc_api_hash' ) ) {
			return array(
				'success' => false,
				'message' => __( 'WooCommerce is not active, so there is no catalog to connect yet.', 'matrixweave-for-woocommerce' ),
			);
		}

		// Matrixweave reads the catalog by calling this site's REST API from its
		// own servers. On localhost, a private network or a .test domain it
		// simply cannot — so say that plainly instead of letting the merchant
		// read a connection failure as a broken plugin. Bail BEFORE creating a
		// key: an API key that can never be used is just litter in their store.
		if ( ! $this->is_publicly_reachable() ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: this site's address. */
					__( 'this site (%s) is not reachable from the internet, so Matrixweave cannot read your catalog from here. The chat widget works already — connect the catalog again once the site is live.', 'matrixweave-for-woocommerce' ),
					$this->site_url()
				),
			);
		}

		global $wpdb;
		$consumer_key    = 'ck_' . wc_rand_hash();
		$consumer_secret = 'cs_' . wc_rand_hash();

		// Read-only, always. The AI reads the catalog and answers questions
		// about orders; nothing in this product writes to a merchant's store,
		// and a key that cannot write cannot be turned into one that does.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'woocommerce_api_keys',
			array(
				'user_id'         => get_current_user_id(),
				'description'     => 'Matrixweave AI Agent',
				'permissions'     => 'read',
				'consumer_key'    => wc_api_hash( $consumer_key ),
				'consumer_secret' => $consumer_secret,
				'truncated_key'   => substr( $consumer_key, -7 ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		// phpcs:enable

		if ( ! $inserted ) {
			return array(
				'success' => false,
				'message' => __( 'WooCommerce would not create an API key on this site.', 'matrixweave-for-woocommerce' ),
			);
		}
		$key_id = (int) $wpdb->insert_id;

		$api    = new Matrixweave_API( $this->settings->get_api_url(), $secret_key );
		$result = $api->provision_store( $this->site_url(), $consumer_key, $consumer_secret );

		if ( ! empty( $result['success'] ) ) {
			// The new key is proven, so every earlier one of ours is dead weight.
			$this->prune_old_keys( $key_id );
		}

		return array(
			'success' => ! empty( $result['success'] ),
			'message' => isset( $result['message'] ) ? $result['message'] : '',
		);
	}

	/**
	 * Delete the API keys this plugin minted before the one that just worked.
	 *
	 * A key is created BEFORE Matrixweave is asked to accept it, because the
	 * request has to carry one — so every failed attempt leaves a live
	 * read-only credential behind in the store. A merchant retrying a connection
	 * that kept failing accumulated one per click (a real store collected
	 * several in an afternoon), and they are indistinguishable from each other
	 * in WooCommerce → Settings → Advanced → REST API.
	 *
	 * Pruned on SUCCESS only, deliberately. A failed attempt keeps everything:
	 * the platform now saves a connection whose test merely timed out, so a
	 * "failure" here does not prove the key is unused, and deleting it could
	 * break a connection that was in fact accepted.
	 *
	 * @param int $keep_key_id Row id of the key to keep.
	 * @return void
	 */
	private function prune_old_keys( $keep_key_id ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}woocommerce_api_keys WHERE description = %s AND key_id != %d",
				'Matrixweave AI Agent',
				(int) $keep_key_id
			)
		);
		// phpcs:enable
	}

	/**
	 * Could a server on the public internet actually reach this site?
	 *
	 * Deliberately conservative: it only rules out addresses that provably
	 * cannot be reached — loopback, the private IPv4 ranges, and the hostnames
	 * local development conventionally uses. Anything else is assumed public,
	 * because a false "you are not reachable" on a live store would be far
	 * worse than letting the real connection attempt fail with its own message.
	 *
	 * @return bool
	 */
	public function is_publicly_reachable() {
		$host = wp_parse_url( $this->site_url(), PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}
		$host = strtolower( $host );

		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1', '[::1]' ), true ) ) {
			return false;
		}

		// Hostnames reserved for local development and testing (RFC 6761/8375).
		foreach ( array( '.local', '.localhost', '.test', '.example', '.invalid', '.internal', '.home.arpa' ) as $suffix ) {
			if ( substr( $host, -strlen( $suffix ) ) === $suffix ) {
				return false;
			}
		}

		// Private and link-local IPv4. filter_var's own flags say it best.
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return (bool) filter_var(
				$host,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			);
		}

		// A hostname with no dot at all cannot resolve publicly (e.g. "wordpress"
		// inside a container network).
		return false !== strpos( $host, '.' );
	}

	/**
	 * This site's address, as Matrixweave will know it.
	 *
	 * `home_url()` is the public front end — the same value the REST API is
	 * reachable on, and what the API stores as the store's identity.
	 *
	 * @return string
	 */
	public function site_url() {
		return untrailingslashit( home_url() );
	}

	/**
	 * The dashboard origin, derived from the configured API URL so a
	 * self-hosted or staging deployment follows automatically.
	 *
	 * @return string
	 */
	public function dashboard_url() {
		$api = $this->settings->get_api_url();
		$host = wp_parse_url( $api, PHP_URL_HOST );

		// api.matrixweave.com → www.matrixweave.com. Anything unrecognised
		// falls back to the public site rather than guessing.
		if ( is_string( $host ) && 0 === strpos( $host, 'api.' ) ) {
			$scheme = wp_parse_url( $api, PHP_URL_SCHEME );
			return ( $scheme ? $scheme : 'https' ) . '://www.' . substr( $host, 4 );
		}
		return 'https://www.matrixweave.com';
	}

	/**
	 * Link that starts the handshake.
	 *
	 * @return string
	 */
	public function start_url() {
		return wp_nonce_url(
			add_query_arg( 'matrixweave_connect', '1', $this->settings_url() ),
			'matrixweave_connect'
		);
	}

	/**
	 * Link that clears the connection.
	 *
	 * @return string
	 */
	public function disconnect_url() {
		return wp_nonce_url(
			add_query_arg( 'matrixweave_disconnect', '1', $this->settings_url() ),
			'matrixweave_disconnect'
		);
	}

	/**
	 * Can the current user set this up?
	 *
	 * Falls back to `manage_options` so the plugin stays usable on a plain
	 * WordPress site with no WooCommerce.
	 *
	 * @return bool
	 */
	private function can_manage() {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Stash a one-shot admin notice across the redirect.
	 *
	 * @param string $type    success | warning | error.
	 * @param string $message Message text.
	 * @return void
	 */
	private function notice( $type, $message ) {
		set_transient(
			'matrixweave_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}
}
