<?php
/**
 * LOCAL SCREENSHOT HELPER — never ships with the plugin.
 *
 * The screens worth photographing in 1.2.0 are the CONNECTED ones: the
 * workspace panel with a plan, a real product count and a live order-lookup
 * check. Those render from live API responses, which a throwaway container on
 * localhost has no account to fetch.
 *
 * So this stubs the two status endpoints and seeds the stored keys. What it
 * does NOT do is fake any markup: every pixel is still drawn by the plugin's
 * own view, from the same fields the real API returns. Only the network hop is
 * replaced, so a screenshot shows the real screen rather than a mock-up.
 *
 * Numbers are representative of a real mid-sized store and deliberately
 * generic — no customer's data appears in a public directory listing.
 *
 *   ?demo_state=connected  → stored keys present  (workspace + status panels)
 *   ?demo_state=fresh      → no keys              (the Connect call to action)
 *
 * Lives only in the throwaway Docker container's mu-plugins folder. Not in the
 * plugin directory, not in the release zip, not in SVN.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- local screenshot helper.
		if ( ! isset( $_GET['demo_state'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- local screenshot helper.
		$state  = sanitize_text_field( wp_unslash( $_GET['demo_state'] ) );
		$stored = get_option( 'matrixweave_settings', array() );
		$stored = is_array( $stored ) ? $stored : array();

		if ( 'connected' === $state ) {
			// Shaped like a real key (pk_ + 32 hex) so the screenshot shows the
			// field as a merchant sees it. Not a working key for any workspace.
			$stored['public_key']   = 'pk_4c81b7e2a95d43f0b6ea27c1d8f350ab';
			$stored['secret_key']   = 'sk_9f2d61ac0e784b35a7c40e6bd219753';
			$stored['embed_widget'] = 'yes';
			$stored['order_lookup'] = 'yes';
		} else {
			unset( $stored['public_key'], $stored['secret_key'] );
		}

		update_option( 'matrixweave_settings', $stored );
		wp_safe_redirect( remove_query_arg( 'demo_state' ) );
		exit;
	},
	5 // before the plugin reads its settings
);

/**
 * Quieten the container's own housekeeping so it can't photobomb a listing.
 *
 * A throwaway stack that gets stopped and started accumulates WooCommerce
 * Action Scheduler backlog and update badges — "3 past-due actions found;
 * something may be wrong" reads, to anyone browsing the directory, as the
 * PLUGIN reporting a fault. None of it belongs in a screenshot of our screen.
 */
add_action(
	'admin_head',
	function () {
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		echo '<style>.update-plugins,.awaiting-mod,.update-nag,#wp-admin-bar-updates{display:none!important}</style>';
	},
	1
);

/**
 * Answer the plugin's status calls locally, with the exact field names the
 * real endpoints return (see Matrixweave_API::erp_status / account_status).
 */
add_filter(
	'pre_http_request',
	function ( $preempt, $args, $url ) {
		if ( false === strpos( (string) $url, 'matrixweave.com' ) ) {
			return $preempt;
		}

		$body = null;

		if ( false !== strpos( $url, '/widget/erp-status' ) ) {
			$body = array(
				'connected'      => true,
				'platform'       => 'WOOCOMMERCE',
				'storeUrl'       => home_url(),
				'productCount'   => 4465,
				'syncStatus'     => 'IDLE',
				'syncError'      => null,
				'lastSyncedAt'   => gmdate( 'c' ),
				'ordersReadable' => true,
			);
		} elseif ( false !== strpos( $url, '/widget/account-status' ) ) {
			$body = array(
				'workspaceName'         => 'Northgate Supply Co.',
				'plan'                  => 'Growth',
				'conversationsUsed'     => 318,
				'conversationsIncluded' => 2000,
			);
		} elseif ( false !== strpos( $url, '/widget/sign-identity' ) ) {
			$body = array(
				'email'     => 'customer@example.com',
				'issuedAt'  => time(),
				'signature' => str_repeat( 'a', 64 ),
			);
		}

		if ( null === $body ) {
			return $preempt;
		}

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $body ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);
