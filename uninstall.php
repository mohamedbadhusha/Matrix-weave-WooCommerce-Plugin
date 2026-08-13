<?php
/**
 * Uninstall cleanup.
 *
 * Removes the plugin's option and any cached identity signatures. Does NOT
 * touch WooCommerce REST API keys the owner generated — those live in
 * WooCommerce and may still be in use by the Matrixweave connection.
 *
 * @package Matrixweave
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'matrixweave_settings' );

global $wpdb;

// Cached identity signatures and wishlist names are stored as transients, which
// live in the options table behind a _transient_ / _transient_timeout_ prefix.
$matrixweave_transient_prefixes = array(
	'matrixweave_identity_',
	'matrixweave_wishlist_',
);

foreach ( $matrixweave_transient_prefixes as $matrixweave_prefix ) {
	$matrixweave_value   = $wpdb->esc_like( '_transient_' . $matrixweave_prefix ) . '%';
	$matrixweave_timeout = $wpdb->esc_like( '_transient_timeout_' . $matrixweave_prefix ) . '%';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$matrixweave_value,
			$matrixweave_timeout
		)
	);
	// phpcs:enable
}
