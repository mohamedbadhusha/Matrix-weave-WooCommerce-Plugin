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
		// Accept any 2xx. The API historically answered 201 (framework default
		// for POST) while this check demanded exactly 200 — which silently
		// rejected every SUCCESSFUL signing and broke order lookups for all
		// installs ≤ 1.0.2.
		if ( $code < 200 || $code >= 300 ) {
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
	 * Exchange a one-time connect code for this site's workspace keys.
	 *
	 * The plugin's source ships to every install, so it can never carry a
	 * platform credential. Instead the merchant authorises on matrixweave.com,
	 * comes back with a short-lived code, and this call trades it — server to
	 * server — for the keys. The code alone is not enough: the API re-checks the
	 * nonce (which never left this server) and the site URL.
	 *
	 * @param string $code     One-time code returned on the redirect.
	 * @param string $nonce    The nonce this site generated when starting.
	 * @param string $site_url This site's address.
	 * @return array { success: bool, message: string, data?: array }
	 */
	public function claim_site_connect( $code, $nonce, $site_url ) {
		$response = wp_remote_post(
			$this->api_url . '/api/v1/widget/site-connect/claim',
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'Matrixweave-WooCommerce/' . MATRIXWEAVE_VERSION,
				),
				'body'        => wp_json_encode(
					array(
						'code'    => $code,
						'nonce'   => $nonce,
						'siteUrl' => $site_url,
					)
				),
			)
		);

		return $this->interpret( $response, __( 'Could not complete the connection. Please try again.', 'matrixweave-for-woocommerce' ) );
	}

	/**
	 * Hand Matrixweave a read-only WooCommerce REST key so it can read this
	 * store's catalog and orders.
	 *
	 * This is what removes the last copy-paste: the merchant used to read a
	 * consumer key and secret off one screen and type them into another.
	 *
	 * @param string $site_url        Store address.
	 * @param string $consumer_key    ck_… generated on this site.
	 * @param string $consumer_secret cs_… generated on this site.
	 * @return array { success: bool, message: string, data?: array }
	 */
	public function provision_store( $site_url, $consumer_key, $consumer_secret ) {
		if ( empty( $this->secret_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'No secret key set.', 'matrixweave-for-woocommerce' ),
			);
		}

		$response = wp_remote_post(
			$this->api_url . '/api/v1/widget/provision-erp',
			array(
				'timeout'     => 30, // The API validates the credential before replying.
				'redirection' => 0,
				'headers'     => array(
					'Content-Type' => 'application/json',
					'X-Secret-Key' => $this->secret_key,
					'User-Agent'   => 'Matrixweave-WooCommerce/' . MATRIXWEAVE_VERSION,
				),
				'body'        => wp_json_encode(
					array(
						'platform'       => 'WOOCOMMERCE',
						'siteUrl'        => $site_url,
						'consumerKey'    => $consumer_key,
						'consumerSecret' => $consumer_secret,
					)
				),
			)
		);

		return $this->interpret( $response, __( 'Could not connect your catalog. Please try again.', 'matrixweave-for-woocommerce' ) );
	}

	/**
	 * What Matrixweave can actually see of this store right now.
	 *
	 * Asked rather than assumed, deliberately: a stored "connected" flag stays
	 * green through expired credentials, a failed sync and an empty catalog.
	 *
	 * @return array|null { connected, platform, productCount, syncStatus, syncError, lastSyncedAt, ordersReadable }
	 */
	public function erp_status() {
		return $this->get_with_secret( '/api/v1/widget/erp-status' );
	}

	/**
	 * Plan, usage and workspace name — so "is this working, and am I near my
	 * limit?" is answerable without leaving wp-admin.
	 *
	 * @return array|null
	 */
	public function account_status() {
		return $this->get_with_secret( '/api/v1/widget/account-status' );
	}

	/**
	 * Shared GET for the secret-key-authed status endpoints.
	 *
	 * Returns null on any failure — these drive an informational panel, and a
	 * transient network blip must never break the settings screen.
	 *
	 * @param string $path API path.
	 * @return array|null
	 */
	private function get_with_secret( $path ) {
		if ( empty( $this->secret_key ) ) {
			return null;
		}

		$response = wp_remote_get(
			$this->api_url . $path,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array(
					'X-Secret-Key' => $this->secret_key,
					'User-Agent'   => 'Matrixweave-WooCommerce/' . MATRIXWEAVE_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) ? $body : null;
	}

	/**
	 * Turn a wp_remote_* result into a uniform outcome, preferring the API's own
	 * explanation over a bare status code — a merchant can act on "your store
	 * refused the key", not on "400".
	 *
	 * @param array|WP_Error $response Raw response.
	 * @param string         $fallback Message to use when the API said nothing useful.
	 * @return array { success: bool, message: string, data?: array }
	 */
	private function interpret( $response, $fallback ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				/* translators: %s: error detail from WordPress. */
				'message' => sprintf( __( 'Could not reach Matrixweave: %s', 'matrixweave-for-woocommerce' ), $response->get_error_message() ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();

		if ( $code >= 200 && $code < 300 ) {
			return array(
				'success' => true,
				'message' => '',
				'data'    => $body,
			);
		}

		// Nest sends `message` as a string, or as a string[] for validation
		// failures — flatten both so the real reason reaches the merchant.
		$detail = isset( $body['message'] ) ? $body['message'] : '';
		if ( is_array( $detail ) ) {
			$detail = implode( '; ', $detail );
		}

		return array(
			'success' => false,
			'message' => $detail ? (string) $detail : $fallback,
		);
	}

	/**
	 * Validate the configured secret key by requesting a throwaway signature.
	 *
	 * A 2xx with a signature proves the secret key is valid and active. Uses a
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
