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
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_mw\_identity\_%'
	    OR option_name LIKE '\_transient\_timeout\_mw\_identity\_%'"
);
// phpcs:enable
