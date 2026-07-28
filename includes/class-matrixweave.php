<?php
/**
 * Main plugin orchestrator.
 *
 * @package Matrixweave
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Matrixweave
 *
 * Singleton that wires together the settings, widget embed, identity signing
 * and store-connection helpers.
 */
final class Matrixweave {

	/**
	 * Single instance.
	 *
	 * @var Matrixweave|null
	 */
	private static $instance = null;

	/**
	 * Settings handler.
	 *
	 * @var Matrixweave_Settings
	 */
	public $settings;

	/**
	 * Widget embed handler.
	 *
	 * @var Matrixweave_Widget
	 */
	public $widget;

	/**
	 * Store-connection (REST key) helper.
	 *
	 * @var Matrixweave_Connection
	 */
	public $connection;

	/**
	 * Get the singleton instance.
	 *
	 * @return Matrixweave
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — sets everything up.
	 */
	private function __construct() {
		// Translations load automatically (WordPress 4.6+ just-in-time loader);
		// no manual load_plugin_textdomain() needed for a wordpress.org plugin.
		$this->settings   = new Matrixweave_Settings();
		$this->connection = new Matrixweave_Connection();
		$this->widget     = new Matrixweave_Widget( $this->settings );

		$this->settings->hooks();
		$this->connection->hooks();
		$this->widget->hooks();

		add_action( 'admin_notices', array( $this, 'maybe_woocommerce_notice' ) );
		add_filter( 'plugin_action_links_' . MATRIXWEAVE_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Nudge the admin if WooCommerce isn't active. The identity/order-lookup
	 * features rely on WooCommerce customer sessions; the widget still works
	 * without it, so this is a notice, not a hard requirement.
	 */
	public function maybe_woocommerce_notice() {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Matrixweave: WooCommerce is not active. The chat widget will still load, but personalized order lookups need WooCommerce.', 'matrixweave-for-woocommerce' );
		echo '</p></div>';
	}

	/**
	 * Add a "Settings" link on the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url  = admin_url( 'admin.php?page=' . Matrixweave_Settings::PAGE_SLUG );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'matrixweave-for-woocommerce' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}
}
