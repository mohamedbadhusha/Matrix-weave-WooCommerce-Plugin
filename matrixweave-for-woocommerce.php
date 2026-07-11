<?php
/**
 * Plugin Name:       Matrixweave for WooCommerce
 * Plugin URI:        https://www.matrixweave.com/docs/connect-store
 * Description:        Official Matrixweave AI Sales &amp; Support agent for WooCommerce. Auto-embeds the chat widget and securely signs logged-in customers so the AI can answer "where's my order?" — no theme code, no wp-config edits.
 * Version:           1.0.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Matrixweave
 * Author URI:        https://www.matrixweave.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       matrixweave-for-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 5.0
 * WC tested up to:   9.4
 *
 * @package Matrixweave
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'MATRIXWEAVE_VERSION', '1.0.3' );
define( 'MATRIXWEAVE_FILE', __FILE__ );
define( 'MATRIXWEAVE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MATRIXWEAVE_URL', plugin_dir_url( __FILE__ ) );
define( 'MATRIXWEAVE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Default Matrixweave API base. Overridable per-site in Settings, or via the
 * MATRIXWEAVE_API_URL constant (defined in wp-config.php) for self-hosted setups.
 */
if ( ! defined( 'MATRIXWEAVE_DEFAULT_API_URL' ) ) {
	define( 'MATRIXWEAVE_DEFAULT_API_URL', 'https://api.matrixweave.com' );
}
if ( ! defined( 'MATRIXWEAVE_DEFAULT_WIDGET_URL' ) ) {
	define( 'MATRIXWEAVE_DEFAULT_WIDGET_URL', 'https://www.matrixweave.com/widget.js' );
}

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS)
 * and the new checkout blocks. This plugin only reads customer/session data, so
 * it is compatible with both the legacy and the new order tables.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MATRIXWEAVE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', MATRIXWEAVE_FILE, true );
		}
	}
);

// Autoload the plugin classes.
require_once MATRIXWEAVE_PATH . 'includes/class-matrixweave-api.php';
require_once MATRIXWEAVE_PATH . 'includes/class-matrixweave-identity.php';
require_once MATRIXWEAVE_PATH . 'includes/class-matrixweave-connection.php';
require_once MATRIXWEAVE_PATH . 'includes/class-matrixweave-widget.php';
require_once MATRIXWEAVE_PATH . 'includes/class-matrixweave-settings.php';
require_once MATRIXWEAVE_PATH . 'includes/class-matrixweave.php';

/**
 * Boot the plugin once all plugins are loaded (so we can detect WooCommerce).
 */
function matrixweave() {
	return Matrixweave::instance();
}

add_action( 'plugins_loaded', 'matrixweave', 20 );

// Activation: seed defaults and flush caches.
register_activation_hook(
	__FILE__,
	function () {
		if ( false === get_option( Matrixweave_Settings::OPTION_KEY, false ) ) {
			add_option(
				Matrixweave_Settings::OPTION_KEY,
				array(
					'api_url'         => MATRIXWEAVE_DEFAULT_API_URL,
					'widget_url'      => MATRIXWEAVE_DEFAULT_WIDGET_URL,
					'embed_widget'    => 'yes',
					'order_lookup'    => 'yes',
					'mode'            => 'auto',
				)
			);
		}
	}
);

// Deactivation: clear cached identity signatures so nothing lingers.
register_deactivation_hook(
	__FILE__,
	function () {
		Matrixweave_Identity::purge_all_cached();
	}
);
