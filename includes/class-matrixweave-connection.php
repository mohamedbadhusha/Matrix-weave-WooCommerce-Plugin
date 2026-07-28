<?php
/**
 * Store-connection helper.
 *
 * One-click generation of a read-only WooCommerce REST API key so the owner can
 * connect their catalog to Matrixweave (ERP Connections → Add Source → WooCommerce)
 * without hunting through WooCommerce → Settings → Advanced → REST API. Also
 * hosts the admin-side AJAX endpoints for generating keys and testing the
 * secret-key connection.
 *
 * @package Matrixweave
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Matrixweave_Connection
 */
class Matrixweave_Connection {

	const AJAX_GENERATE = 'matrixweave_generate_key';
	const AJAX_TEST     = 'matrixweave_test_connection';

	/**
	 * Register AJAX hooks (admin only).
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_ajax_' . self::AJAX_GENERATE, array( $this, 'ajax_generate_key' ) );
		add_action( 'wp_ajax_' . self::AJAX_TEST, array( $this, 'ajax_test_connection' ) );
	}

	/**
	 * AJAX: generate a WooCommerce REST API key pair for Matrixweave.
	 *
	 * Returns the Consumer Key / Secret exactly once (they are hashed at rest in
	 * WooCommerce and cannot be shown again), plus the Site URL, ready to paste
	 * into Matrixweave's "Add Source → WooCommerce" form.
	 *
	 * @return void
	 */
	public function ajax_generate_key() {
		$this->guard();

		if ( ! function_exists( 'wc_rand_hash' ) || ! function_exists( 'wc_api_hash' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'WooCommerce must be active to generate REST API keys.', 'matrixweave-for-woocommerce' ) )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce + capability verified in guard() at the top of this handler.
		$permissions = ( isset( $_POST['write'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['write'] ) ) ) ? 'read_write' : 'read';

		// Remember the Read/Write choice so the checkbox survives page
		// reloads even if the owner never clicks Save changes.
		$stored              = get_option( Matrixweave_Settings::OPTION_KEY, array() );
		$stored              = is_array( $stored ) ? $stored : array();
		$stored['key_write'] = ( 'read_write' === $permissions ) ? 'yes' : 'no';
		update_option( Matrixweave_Settings::OPTION_KEY, $stored );

		global $wpdb;
		$consumer_key    = 'ck_' . wc_rand_hash();
		$consumer_secret = 'cs_' . wc_rand_hash();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'woocommerce_api_keys',
			array(
				'user_id'         => get_current_user_id(),
				'description'     => 'Matrixweave AI Agent',
				'permissions'     => $permissions,
				'consumer_key'    => wc_api_hash( $consumer_key ),
				'consumer_secret' => $consumer_secret,
				'truncated_key'   => substr( $consumer_key, -7 ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		// phpcs:enable

		if ( ! $inserted ) {
			wp_send_json_error(
				array( 'message' => __( 'Could not create the WooCommerce API key. Please add it manually under WooCommerce → Settings → Advanced → REST API.', 'matrixweave-for-woocommerce' ) )
			);
		}

		wp_send_json_success(
			array(
				'siteUrl'        => untrailingslashit( home_url() ),
				'consumerKey'    => $consumer_key,
				'consumerSecret' => $consumer_secret,
				'permissions'    => $permissions,
				'message'        => __( 'API key created. Copy these into Matrixweave now — the secret is shown only once.', 'matrixweave-for-woocommerce' ),
			)
		);
	}

	/**
	 * AJAX: test the configured secret key against the Matrixweave API.
	 *
	 * @return void
	 */
	public function ajax_test_connection() {
		$this->guard();

		$settings = matrixweave()->settings;

		// Prefer the values currently TYPED on the settings page (posted by
		// admin.js) over the saved ones, so "paste key → Test" works before
		// the user clicks Save. Blank posted values fall back to saved.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce + capability verified in guard() at the top of this handler.
		$typed_secret = isset( $_POST['secret'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['secret'] ) ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in guard(); esc_url_raw() sanitizes the value.
		$typed_api    = isset( $_POST['api_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_url'] ) ) : '';

		$secret  = ( '' !== $typed_secret && ! $settings->secret_is_from_constant() ) ? $typed_secret : $settings->get_secret_key();
		$api_url = ( '' !== $typed_api ) ? untrailingslashit( $typed_api ) : $settings->get_api_url();

		$api    = new Matrixweave_API( $api_url, $secret );
		$result = $api->test_secret_key();

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		}
		wp_send_json_error( array( 'message' => $result['message'] ) );
	}

	/**
	 * Shared nonce + capability guard for the AJAX handlers.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'matrixweave-for-woocommerce' ) ), 403 );
		}
		check_ajax_referer( Matrixweave_Settings::NONCE_ACTION, 'nonce' );
	}
}
