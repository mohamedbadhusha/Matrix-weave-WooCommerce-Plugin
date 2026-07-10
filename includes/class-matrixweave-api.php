<?php
/**
 * Thin HTTP client for the Matrixweave API.
 *
 * All calls that use the SECRET key run here, server-side only. The secret key
 * is never enqueued, printed, or exposed to the browser.
 *
 * @package Matrixweave
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Matrixweave_API
 */
class Matrixweave_API {

	/**
	 * API base URL, e.g. https://api.matrixweave.com (no trailing slash).
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Tenant secret key (sk_...). Kept only in memory for the request.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_url    Matrixweave API base URL.
	 * @param string $secret_key Tenant secret key.
	 */
	public function __construct( $api_url, $secret_key = '' ) {
		$this->api_url    = untrailingslashit( $api_url ? $api_url : MATRIXWEAVE_DEFAULT_API_URL );
		$this->secret_key = trim( (string) $secret_key );
	}

	/**
	 * Sign a customer's identity so the widget can unlock their order history.
	 *
	 * Calls POST /api/v1/widget/sign-identity with the X-Secret-Key header. The
	 * response is { email, issuedAt, signature }. Returns null on any failure.
	 *
	 * @param string $email Customer email (already verified as logged-in).
	 * @return array|null { email, issuedAt, signature } or null.
	 */
	public function sign_identity( $email ) {
		if ( empty( $this->secret_key ) || empty( $email ) ) {
			return null;
		}

		$response = wp_remote_post(
			$this->api_url . '/api/v1/widget/sign-identity',
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'headers'     => array(
					'Content-Type' => 'application/json',
					'X-Secret-Key' => $this->secret_key,
					'User-Agent'   => 'Matrixweave-WooCommerce/' . MATRIXWEAVE_VERSION,
				),
				'body'        => wp_json_encode( array( 'email' => $email ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['signature'] ) || empty( $body['email'] ) || empty( $body['issuedAt'] ) ) {
			return null;
		}

		return array(
			'email'     => (string) $body['email'],
			'issuedAt'  => (int) $body['issuedAt'],
			'signature' => (string) $body['signature'],
		);
	}

	/**
	 * Validate the configured secret key by requesting a throwaway signature.
	 *
	 * A 200 with a signature proves the secret key is valid and active. Uses a
	 * neutral email that never becomes a real customer lookup.
	 *
	 * @return array { success: bool, message: string }
	 */
	public function test_secret_key() {
		if ( empty( $this->secret_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'No secret key set.', 'matrixweave-for-woocommerce' ),
			);
		}

		$probe = 'connection-test@' . wp_parse_url( home_url(), PHP_URL_HOST );
		$id    = $this->sign_identity( $probe );

		if ( is_array( $id ) && ! empty( $id['signature'] ) ) {
			return array(
				'success' => true,
				'message' => __( 'Secret key verified — order lookups are ready.', 'matrixweave-for-woocommerce' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Could not verify the secret key. Double-check it (starts with sk_) and the API URL.', 'matrixweave-for-woocommerce' ),
		);
	}
}
