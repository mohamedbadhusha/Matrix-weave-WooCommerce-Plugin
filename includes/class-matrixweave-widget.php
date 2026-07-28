<?php
/**
 * Front-end widget embed.
 *
 * Loads the Matrixweave widget script and calls Matrixweave.init() in the site
 * footer. For logged-in customers it folds in the server-signed identity so the
 * agent can look up their orders — no theme edits, no wp-config constants.
 *
 * @package Matrixweave
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Matrixweave_Widget
 */
class Matrixweave_Widget {

	/**
	 * Settings instance.
	 *
	 * @var Matrixweave_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Matrixweave_Settings $settings Settings.
	 */
	public function __construct( Matrixweave_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		// Forget a cached signature the moment it could go stale.
		add_action( 'wp_logout', array( 'Matrixweave_Identity', 'purge_user' ) );
		add_action( 'profile_update', array( 'Matrixweave_Identity', 'purge_user' ) );
	}

	/**
	 * Enqueue the widget loader + a small init script in the footer.
	 *
	 * Uses wp_enqueue_script()/wp_add_inline_script() so the loader is a
	 * properly registered dependency rather than a hand-printed <script> tag.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! $this->settings->is_embed_enabled() ) {
			return;
		}

		$public_key = $this->settings->get_public_key();
		if ( empty( $public_key ) ) {
			return; // Nothing to embed without a public key.
		}

		$config = array(
			'apiKey' => $public_key,
			'apiUrl' => $this->settings->get_api_url(),
			'mode'   => $this->settings->get_mode(),
		);

		// Optional appearance overrides (only when the owner set them).
		$primary_color = $this->settings->get_primary_color();
		if ( ! empty( $primary_color ) ) {
			$config['primaryColor'] = $primary_color;
		}
		$greeting = $this->settings->get_greeting();
		if ( ! empty( $greeting ) ) {
			$config['greeting'] = $greeting;
		}

		// Signed customer identity (server-side; secret key never leaves PHP).
		$identity = Matrixweave_Identity::for_current_user( $this->settings );
		if ( is_array( $identity ) ) {
			$config = array_merge( $config, $identity );

			// Wishlist personalization: only meaningful alongside a verified
			// identity (the API ignores it otherwise), so it lives inside this
			// branch. Product names from the YITH Wishlist plugin, when active.
			$wishlist = $this->wishlist_product_names( get_current_user_id() );
			if ( ! empty( $wishlist ) ) {
				$config['customerWishlist'] = $wishlist;
			}
		}

		/**
		 * Filter the final Matrixweave.init() config before it is printed.
		 *
		 * @param array $config The widget init config.
		 */
		$config = apply_filters( 'matrixweave_widget_config', $config );

		$json = wp_json_encode( $config );
		if ( false === $json ) {
			return;
		}

		wp_enqueue_script(
			'matrixweave-widget',
			$this->settings->get_widget_url(),
			array(),
			MATRIXWEAVE_VERSION,
			true
		);

		// Poll for the loader's global (it may not be ready yet), then hand it
		// the server-built config. wp_json_encode output is safe JS.
		$init = sprintf(
			'(function(){function mwInit(){if(typeof window.Matrixweave==="undefined"||typeof window.Matrixweave.init!=="function"){return window.setTimeout(mwInit,200);}window.Matrixweave.init(%s);}mwInit();})();',
			$json
		);
		wp_add_inline_script( 'matrixweave-widget', $init );
	}

	/**
	 * Product names on the user's YITH wishlist (newest first, max 10).
	 *
	 * Reads the YITH WooCommerce Wishlist table directly — stable across the
	 * free and premium versions — and resolves names via wc_get_product() so
	 * deleted/hidden products drop out. Cached per user for 10 minutes.
	 * Returns [] when YITH (or WooCommerce) is not installed.
	 *
	 * @param int $user_id User ID.
	 * @return string[]
	 */
	private function wishlist_product_names( $user_id ) {
		if ( ! $user_id || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		global $wpdb;
		// $table is $wpdb->prefix concatenated with a fixed plugin string — no
		// user input. Table/identifier names cannot be bound as $wpdb->prepare()
		// parameters, so interpolation here is safe by construction.
		$table = $wpdb->prefix . 'yith_wcwl';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array(); // YITH Wishlist not installed.
		}

		$cache_key = 'mw_wishlist_' . (int) $user_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT prod_id FROM {$table} WHERE user_id = %d ORDER BY dateadded DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);
		// phpcs:enable

		$names = array();
		foreach ( (array) $ids as $pid ) {
			$product = wc_get_product( (int) $pid );
			if ( $product && $product->get_name() ) {
				$names[] = wp_strip_all_tags( $product->get_name() );
			}
		}

		set_transient( $cache_key, $names, 10 * MINUTE_IN_SECONDS );
		return $names;
	}
}
