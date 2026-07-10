<?php
/**
 * Front-end widget embed.
 *
 * Loads the Matrixweave widget script and calls Matrixweave.init() in the site
 * footer. For logged-in customers it folds in the server-signed identity so the
 * agent can look up their orders — no theme edits, no wp-config constants.
 *
 * @package Matrixweave
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Matrixweave_Widget
 */
class Matrixweave_Widget {

	/**
	 * Settings instance.
	 *
	 * @var Matrixweave_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Matrixweave_Settings $settings Settings.
	 */
	public function __construct( Matrixweave_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_footer', array( $this, 'render' ), 100 );

		// Forget a cached signature the moment it could go stale.
		add_action( 'wp_logout', array( 'Matrixweave_Identity', 'purge_user' ) );
		add_action( 'profile_update', array( 'Matrixweave_Identity', 'purge_user' ) );
	}

	/**
	 * Output the widget loader + init in the footer.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! $this->settings->is_embed_enabled() ) {
			return;
		}

		$public_key = $this->settings->get_public_key();
		if ( empty( $public_key ) ) {
			return; // Nothing to embed without a public key.
		}

		$config = array(
			'apiKey' => $public_key,
			'apiUrl' => $this->settings->get_api_url(),
			'mode'   => $this->settings->get_mode(),
		);

		// Optional appearance overrides (only when the owner set them).
		$primary_color = $this->settings->get_primary_color();
		if ( ! empty( $primary_color ) ) {
			$config['primaryColor'] = $primary_color;
		}
		$greeting = $this->settings->get_greeting();
		if ( ! empty( $greeting ) ) {
			$config['greeting'] = $greeting;
		}

		// Signed customer identity (server-side; secret key never leaves PHP).
		$identity = Matrixweave_Identity::for_current_user( $this->settings );
		if ( is_array( $identity ) ) {
			$config = array_merge( $config, $identity );
		}

		/**
		 * Filter the final Matrixweave.init() config before it is printed.
		 *
		 * @param array $config The widget init config.
		 */
		$config = apply_filters( 'matrixweave_widget_config', $config );

		$widget_url = $this->settings->get_widget_url();
		$json       = wp_json_encode( $config );
		if ( false === $json ) {
			return;
		}
		?>
<!-- Matrixweave for WooCommerce v<?php echo esc_html( MATRIXWEAVE_VERSION ); ?> -->
<script src="<?php echo esc_url( $widget_url ); ?>" async></script>
<script>
(function () {
	function mwInit() {
		if (typeof window.Matrixweave === 'undefined' || typeof window.Matrixweave.init !== 'function') {
			return window.setTimeout(mwInit, 200);
		}
		window.Matrixweave.init(<?php echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output. ?>);
	}
	mwInit();
})();
</script>
<?php
	}
}
