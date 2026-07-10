<?php
/**
 * Settings storage + admin settings page.
 *
 * @package Matrixweave
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Matrixweave_Settings
 */
class Matrixweave_Settings {

	const OPTION_KEY   = 'matrixweave_settings';
	const PAGE_SLUG    = 'matrixweave';
	const GROUP        = 'matrixweave_settings_group';
	const NONCE_ACTION = 'matrixweave_admin';

	/**
	 * Cached settings array.
	 *
	 * @var array|null
	 */
	private $data = null;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/* ---------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------- */

	/**
	 * Get all settings, merged with defaults.
	 *
	 * @return array
	 */
	public function all() {
		if ( null === $this->data ) {
			$stored     = get_option( self::OPTION_KEY, array() );
			$this->data = wp_parse_args( is_array( $stored ) ? $stored : array(), $this->defaults() );
		}
		return $this->data;
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	private function defaults() {
		return array(
			'public_key'    => '',
			'secret_key'    => '',
			'api_url'       => defined( 'MATRIXWEAVE_API_URL' ) ? MATRIXWEAVE_API_URL : MATRIXWEAVE_DEFAULT_API_URL,
			'widget_url'    => MATRIXWEAVE_DEFAULT_WIDGET_URL,
			'embed_widget'  => 'yes',
			'order_lookup'  => 'yes',
			'mode'          => 'auto',
			'primary_color' => '',
			'greeting'      => '',
		);
	}

	/**
	 * A single setting value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function get( $key, $default = '' ) {
		$all = $this->all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	// Typed convenience getters used across the plugin.
	public function get_public_key() {
		return trim( (string) $this->get( 'public_key' ) );
	}
	public function get_secret_key() {
		// A secret key may also be supplied via wp-config.php constant, which
		// takes precedence and keeps it out of the database entirely.
		if ( defined( 'MATRIXWEAVE_SECRET_KEY' ) && MATRIXWEAVE_SECRET_KEY ) {
			return trim( (string) MATRIXWEAVE_SECRET_KEY );
		}
		return trim( (string) $this->get( 'secret_key' ) );
	}
	public function get_api_url() {
		return untrailingslashit( (string) $this->get( 'api_url', MATRIXWEAVE_DEFAULT_API_URL ) );
	}
	public function get_widget_url() {
		return (string) $this->get( 'widget_url', MATRIXWEAVE_DEFAULT_WIDGET_URL );
	}
	public function get_mode() {
		$mode = (string) $this->get( 'mode', 'auto' );
		return in_array( $mode, array( 'auto', 'sales_agent', 'support_agent' ), true ) ? $mode : 'auto';
	}
	public function get_primary_color() {
		return (string) $this->get( 'primary_color' );
	}
	public function get_greeting() {
		return (string) $this->get( 'greeting' );
	}
	public function is_embed_enabled() {
		return 'yes' === $this->get( 'embed_widget', 'yes' );
	}
	public function is_order_lookup_enabled() {
		return 'yes' === $this->get( 'order_lookup', 'yes' );
	}
	public function secret_is_from_constant() {
		return defined( 'MATRIXWEAVE_SECRET_KEY' ) && MATRIXWEAVE_SECRET_KEY;
	}

	/* ---------------------------------------------------------------------
	 * Admin menu + registration
	 * ------------------------------------------------------------------- */

	/**
	 * Add the top-level admin menu.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Matrixweave', 'matrixweave-for-woocommerce' ),
			__( 'Matrixweave', 'matrixweave-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-format-chat',
			58
		);
	}

	/**
	 * Register the settings option + sanitizer.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->defaults(),
			)
		);
	}

	/**
	 * Sanitize + preserve the secret key when the field is left blank.
	 *
	 * @param array $input Raw submitted values.
	 * @return array
	 */
	public function sanitize( $input ) {
		$existing = get_option( self::OPTION_KEY, array() );
		$existing = is_array( $existing ) ? $existing : array();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();

		$out['public_key'] = isset( $input['public_key'] ) ? sanitize_text_field( $input['public_key'] ) : '';

		// Secret key: only overwrite when a fresh, non-masked value is provided.
		$submitted_secret = isset( $input['secret_key'] ) ? trim( sanitize_text_field( $input['secret_key'] ) ) : '';
		if ( '' === $submitted_secret ) {
			$out['secret_key'] = isset( $existing['secret_key'] ) ? $existing['secret_key'] : '';
		} else {
			$out['secret_key'] = $submitted_secret;
		}

		$api_url = isset( $input['api_url'] ) ? esc_url_raw( trim( $input['api_url'] ) ) : '';
		$out['api_url'] = $api_url ? untrailingslashit( $api_url ) : MATRIXWEAVE_DEFAULT_API_URL;

		$widget_url        = isset( $input['widget_url'] ) ? esc_url_raw( trim( $input['widget_url'] ) ) : '';
		$out['widget_url'] = $widget_url ? $widget_url : MATRIXWEAVE_DEFAULT_WIDGET_URL;

		$out['embed_widget'] = ( isset( $input['embed_widget'] ) && 'yes' === $input['embed_widget'] ) ? 'yes' : 'no';
		$out['order_lookup'] = ( isset( $input['order_lookup'] ) && 'yes' === $input['order_lookup'] ) ? 'yes' : 'no';

		$mode        = isset( $input['mode'] ) ? sanitize_text_field( $input['mode'] ) : 'auto';
		$out['mode'] = in_array( $mode, array( 'auto', 'sales_agent', 'support_agent' ), true ) ? $mode : 'auto';

		$out['primary_color'] = isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '';
		$out['greeting']      = isset( $input['greeting'] ) ? sanitize_text_field( $input['greeting'] ) : '';

		// If the credentials that affect signing changed, drop cached signatures.
		$old_secret = isset( $existing['secret_key'] ) ? $existing['secret_key'] : '';
		$old_api    = isset( $existing['api_url'] ) ? $existing['api_url'] : '';
		if ( $out['secret_key'] !== $old_secret || $out['api_url'] !== $old_api ) {
			Matrixweave_Identity::purge_all_cached();
		}

		return $out;
	}

	/**
	 * Enqueue admin CSS/JS on our settings page only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'matrixweave-admin', MATRIXWEAVE_URL . 'assets/css/admin.css', array(), MATRIXWEAVE_VERSION );
		wp_enqueue_script( 'matrixweave-admin', MATRIXWEAVE_URL . 'assets/js/admin.js', array( 'jquery' ), MATRIXWEAVE_VERSION, true );
		wp_localize_script(
			'matrixweave-admin',
			'MatrixweaveAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
				'generateAction' => Matrixweave_Connection::AJAX_GENERATE,
				'testAction'   => Matrixweave_Connection::AJAX_TEST,
				'i18n'         => array(
					'generating' => __( 'Generating…', 'matrixweave-for-woocommerce' ),
					'testing'    => __( 'Testing…', 'matrixweave-for-woocommerce' ),
					'copied'     => __( 'Copied!', 'matrixweave-for-woocommerce' ),
					'copy'       => __( 'Copy', 'matrixweave-for-woocommerce' ),
					'error'      => __( 'Something went wrong. Please try again.', 'matrixweave-for-woocommerce' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Page render
	 * ------------------------------------------------------------------- */

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		require MATRIXWEAVE_PATH . 'includes/views/settings-page.php';
	}
}
