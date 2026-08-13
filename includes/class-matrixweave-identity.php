<?php
/**
 * Server-side customer identity signing with per-user caching.
 *
 * This is the heart of the "personalized order lookup" feature. When a customer
 * is logged in, we ask the Matrixweave API to sign their email (HMAC keyed by
 * the tenant secret key). The signed proof is injected into Matrixweave.init()
 * so the AI can answer "where's my order?" for that customer — and only that
 * customer. The secret key never leaves the server.
 *
 * Signatures are valid for ~1 hour (server-side replay window), so we cache the
 * signed proof per user for 50 minutes to avoid an API round-trip on every page
 * load.
 *
 * @package Matrixweave
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Matrixweave_Identity
 */
class Matrixweave_Identity {

	const CACHE_PREFIX = 'matrixweave_identity_';
	const CACHE_TTL    = 3000; // 50 minutes, comfortably inside the 1h signature window.
	const FAIL_FLAG    = 'matrixweave_identity_fail'; // set after a failed signing attempt
	const FAIL_TTL     = 300;  // 5 minutes between retries when signing fails

	/**
	 * Get the signed identity payload for the current logged-in customer.
	 *
	 * Returns an array ready to spread into Matrixweave.init():
	 *   { customerEmail, identitySignature, identityIssuedAt, customerName }
	 * or null when there is no logged-in customer / signing failed / disabled.
	 *
	 * @param Matrixweave_Settings $settings Settings instance.
	 * @return array|null
	 */
	public static function for_current_user( Matrixweave_Settings $settings ) {
		if ( ! $settings->is_order_lookup_enabled() ) {
			return null;
		}
		if ( ! is_user_logged_in() ) {
			return null;
		}

		$secret = $settings->get_secret_key();
		if ( empty( $secret ) ) {
			return null;
		}

		$user  = wp_get_current_user();
		$email = is_object( $user ) ? sanitize_email( $user->user_email ) : '';
		if ( empty( $email ) || ! is_email( $email ) ) {
			return null;
		}

		$signed = self::get_cached( $user->ID, $email );

		if ( null === $signed ) {
			// Negative cache: when signing fails (bad key, API unreachable),
			// don't retry on EVERY page load — a hanging remote call in
			// wp_footer would slow the whole site for logged-in users.
			if ( get_transient( self::FAIL_FLAG ) ) {
				return null;
			}
			$api    = new Matrixweave_API( $settings->get_api_url(), $secret );
			$signed = $api->sign_identity( $email );
			if ( null === $signed ) {
				set_transient( self::FAIL_FLAG, 1, self::FAIL_TTL );
				return null;
			}
			self::set_cached( $user->ID, $email, $signed );
		}

		$payload = array(
			'customerEmail'     => $signed['email'],
			'identitySignature' => $signed['signature'],
			'identityIssuedAt'  => (int) $signed['issuedAt'],
		);

		// A friendly greeting for the signed-in shopper (widget skips the name prompt).
		$name = trim( $user->display_name );
		if ( '' !== $name ) {
			$payload['customerName'] = $name;
		}

		return $payload;
	}

	/**
	 * Read a cached signature for a user, but only if it's still fresh and for
	 * the same email (guards against a mid-session email change).
	 *
	 * @param int    $user_id User ID.
	 * @param string $email   Expected email.
	 * @return array|null
	 */
	private static function get_cached( $user_id, $email ) {
		$cached = get_transient( self::key( $user_id ) );
		if ( ! is_array( $cached ) || empty( $cached['signature'] ) ) {
			return null;
		}
		if ( empty( $cached['email'] ) || strtolower( $cached['email'] ) !== strtolower( $email ) ) {
			return null;
		}
		return $cached;
	}

	/**
	 * Store a signature in the per-user cache.
	 *
	 * @param int    $user_id User ID.
	 * @param string $email   Email.
	 * @param array  $signed  Signed payload.
	 * @return void
	 */
	private static function set_cached( $user_id, $email, array $signed ) {
		set_transient( self::key( $user_id ), $signed, self::CACHE_TTL );
	}

	/**
	 * Forget a single user's cached signature (e.g. on logout / profile save).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function purge_user( $user_id ) {
		if ( $user_id ) {
			delete_transient( self::key( $user_id ) );
		}
	}

	/**
	 * Best-effort purge of every cached signature. Used on deactivation and when
	 * the secret key / API URL changes (so stale proofs can't be reused).
	 *
	 * @return void
	 */
	public static function purge_all_cached() {
		global $wpdb;
		// A changed key/URL must retry immediately — drop the failure flag.
		delete_transient( self::FAIL_FLAG );
		// Transients live in the options table with a _transient_ / _transient_timeout_ prefix.
		$like = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
		$to   = $wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $to ) );
		// phpcs:enable
	}

	/**
	 * Transient key for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function key( $user_id ) {
		return self::CACHE_PREFIX . (int) $user_id;
	}
}
