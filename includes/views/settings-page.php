<?php
/**
 * Admin settings page view.
 *
 * @package Matrixweave
 * @var Matrixweave_Settings $this Provided via require scope.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings         = matrixweave()->settings;
$has_secret       = '' !== $settings->get_secret_key();
$secret_locked    = $settings->secret_is_from_constant();
$public_key       = $settings->get_public_key();
$order_lookup_on  = $settings->is_order_lookup_enabled();
$embed_on         = $settings->is_embed_enabled();
$wc_active        = class_exists( 'WooCommerce' );
?>
<div class="wrap matrixweave-wrap">
	<div class="matrixweave-header">
		<span class="dashicons dashicons-format-chat"></span>
		<div>
			<h1><?php esc_html_e( 'Matrixweave for WooCommerce', 'matrixweave-for-woocommerce' ); ?></h1>
			<p class="matrixweave-sub"><?php esc_html_e( 'Your AI Sales & Support agent — embedded, and secure order lookups for signed-in customers.', 'matrixweave-for-woocommerce' ); ?></p>
		</div>
	</div>

	<?php settings_errors(); ?>

	<div class="matrixweave-status-row">
		<span class="matrixweave-pill <?php echo $public_key ? 'is-on' : 'is-off'; ?>">
			<?php echo $public_key ? esc_html__( 'Widget key set', 'matrixweave-for-woocommerce' ) : esc_html__( 'Widget key missing', 'matrixweave-for-woocommerce' ); ?>
		</span>
		<span class="matrixweave-pill <?php echo $has_secret ? 'is-on' : 'is-off'; ?>">
			<?php echo $has_secret ? esc_html__( 'Order lookups ready', 'matrixweave-for-woocommerce' ) : esc_html__( 'Order lookups off', 'matrixweave-for-woocommerce' ); ?>
		</span>
		<span class="matrixweave-pill <?php echo $wc_active ? 'is-on' : 'is-off'; ?>">
			<?php echo $wc_active ? esc_html__( 'WooCommerce active', 'matrixweave-for-woocommerce' ) : esc_html__( 'WooCommerce inactive', 'matrixweave-for-woocommerce' ); ?>
		</span>
	</div>

	<div class="matrixweave-grid">
		<div class="matrixweave-main">
			<form method="post" action="options.php">
				<?php settings_fields( Matrixweave_Settings::GROUP ); ?>

				<div class="matrixweave-card">
					<h2><?php esc_html_e( '1. Your Matrixweave keys', 'matrixweave-for-woocommerce' ); ?></h2>
					<p class="description">
						<?php
						printf(
							/* translators: %s: dashboard settings link */
							esc_html__( 'Copy these from your Matrixweave dashboard under %s.', 'matrixweave-for-woocommerce' ),
							'<strong>' . esc_html__( 'Settings → Chat Widget', 'matrixweave-for-woocommerce' ) . '</strong>'
						);
						?>
					</p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="mw_public_key"><?php esc_html_e( 'Public key', 'matrixweave-for-woocommerce' ); ?></label>
							</th>
							<td>
								<input type="text" id="mw_public_key" class="regular-text code"
									name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[public_key]"
									value="<?php echo esc_attr( $public_key ); ?>"
									placeholder="pk_..." autocomplete="off" />
								<p class="description"><?php esc_html_e( 'Starts with pk_. Safe to expose — this is what loads the widget in the browser.', 'matrixweave-for-woocommerce' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="mw_secret_key"><?php esc_html_e( 'Secret key', 'matrixweave-for-woocommerce' ); ?></label>
							</th>
							<td>
								<?php if ( $secret_locked ) : ?>
									<p><code>MATRIXWEAVE_SECRET_KEY</code> <?php esc_html_e( 'is defined in wp-config.php — using that. This field is disabled.', 'matrixweave-for-woocommerce' ); ?></p>
								<?php else : ?>
									<input type="password" id="mw_secret_key" class="regular-text code"
										name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[secret_key]"
										value="" autocomplete="new-password"
										placeholder="<?php echo $has_secret ? esc_attr__( '•••••••• saved — leave blank to keep', 'matrixweave-for-woocommerce' ) : 'sk_...'; ?>" />
									<button type="button" class="button matrixweave-toggle-secret" aria-label="<?php esc_attr_e( 'Show / hide', 'matrixweave-for-woocommerce' ); ?>">
										<span class="dashicons dashicons-visibility"></span>
									</button>
									<p class="description">
										<?php esc_html_e( 'Starts with sk_. Stored on your server and used only to sign signed-in customers. It is never sent to the browser.', 'matrixweave-for-woocommerce' ); ?>
									</p>
								<?php endif; ?>
							</td>
						</tr>
					</table>

					<h2><?php esc_html_e( '2. Behaviour', 'matrixweave-for-woocommerce' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Embed the widget', 'matrixweave-for-woocommerce' ); ?></th>
							<td>
								<label class="matrixweave-switch">
									<input type="hidden" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[embed_widget]" value="no" />
									<input type="checkbox" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[embed_widget]" value="yes" <?php checked( $embed_on ); ?> />
									<span><?php esc_html_e( 'Automatically load the chat on the storefront. Turn off if you already paste the snippet yourself.', 'matrixweave-for-woocommerce' ); ?></span>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Personalized order lookups', 'matrixweave-for-woocommerce' ); ?></th>
							<td>
								<label class="matrixweave-switch">
									<input type="hidden" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[order_lookup]" value="no" />
									<input type="checkbox" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[order_lookup]" value="yes" <?php checked( $order_lookup_on ); ?> />
									<span><?php esc_html_e( 'Let the AI answer "where\'s my order?" for logged-in customers (needs a secret key).', 'matrixweave-for-woocommerce' ); ?></span>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="mw_mode"><?php esc_html_e( 'Agent mode', 'matrixweave-for-woocommerce' ); ?></label></th>
							<td>
								<select id="mw_mode" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[mode]">
									<option value="auto" <?php selected( $settings->get_mode(), 'auto' ); ?>><?php esc_html_e( 'Auto (Sales + Support)', 'matrixweave-for-woocommerce' ); ?></option>
									<option value="sales_agent" <?php selected( $settings->get_mode(), 'sales_agent' ); ?>><?php esc_html_e( 'Sales', 'matrixweave-for-woocommerce' ); ?></option>
									<option value="support_agent" <?php selected( $settings->get_mode(), 'support_agent' ); ?>><?php esc_html_e( 'Support', 'matrixweave-for-woocommerce' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="mw_primary_color"><?php esc_html_e( 'Accent color', 'matrixweave-for-woocommerce' ); ?></label></th>
							<td>
								<input type="text" id="mw_primary_color" class="regular-text"
									name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[primary_color]"
									value="<?php echo esc_attr( $settings->get_primary_color() ); ?>" placeholder="#6366f1" />
								<p class="description"><?php esc_html_e( 'Optional — overrides the color set in your dashboard. Leave blank to use the dashboard value.', 'matrixweave-for-woocommerce' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="mw_greeting"><?php esc_html_e( 'Greeting', 'matrixweave-for-woocommerce' ); ?></label></th>
							<td>
								<input type="text" id="mw_greeting" class="large-text"
									name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[greeting]"
									value="<?php echo esc_attr( $settings->get_greeting() ); ?>" placeholder="<?php esc_attr_e( 'Hi! How can I help?', 'matrixweave-for-woocommerce' ); ?>" />
							</td>
						</tr>
					</table>

					<details class="matrixweave-advanced">
						<summary><?php esc_html_e( 'Advanced (API endpoints)', 'matrixweave-for-woocommerce' ); ?></summary>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="mw_api_url"><?php esc_html_e( 'API URL', 'matrixweave-for-woocommerce' ); ?></label></th>
								<td><input type="url" id="mw_api_url" class="regular-text code" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[api_url]" value="<?php echo esc_attr( $settings->get_api_url() ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="mw_widget_url"><?php esc_html_e( 'Widget script URL', 'matrixweave-for-woocommerce' ); ?></label></th>
								<td><input type="url" id="mw_widget_url" class="regular-text code" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[widget_url]" value="<?php echo esc_attr( $settings->get_widget_url() ); ?>" /></td>
							</tr>
						</table>
					</details>

					<?php // Mirrors the Connect-your-catalog Read/Write checkbox (sidebar, outside this form) so Save persists it. Kept in sync by admin.js. ?>
					<input type="hidden" id="mw-key-write-mirror" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[key_write]" value="<?php echo esc_attr( $settings->is_key_write_enabled() ? 'yes' : 'no' ); ?>" />

					<p class="submit">
						<?php submit_button( __( 'Save changes', 'matrixweave-for-woocommerce' ), 'primary', 'submit', false ); ?>
						<button type="button" class="button button-secondary matrixweave-test-btn" id="mw-test-connection">
							<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Test order-lookup connection', 'matrixweave-for-woocommerce' ); ?>
						</button>
						<span class="matrixweave-test-result" id="mw-test-result" aria-live="polite"></span>
					</p>
				</div>
			</form>
		</div>

		<div class="matrixweave-side">
			<div class="matrixweave-card matrixweave-connect">
				<h2><?php esc_html_e( 'Connect your catalog', 'matrixweave-for-woocommerce' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Generate a read-only WooCommerce API key, then paste it into Matrixweave → ERP Connections → Add Source → WooCommerce. No digging through WooCommerce settings.', 'matrixweave-for-woocommerce' ); ?></p>

				<label class="matrixweave-switch matrixweave-write-toggle">
					<input type="checkbox" id="mw-key-write" value="1" <?php checked( $settings->is_key_write_enabled() ); ?> />
					<span><?php esc_html_e( 'Allow the agent to create orders (Read/Write)', 'matrixweave-for-woocommerce' ); ?></span>
				</label>

				<button type="button" class="button button-primary" id="mw-generate-key" <?php disabled( ! $wc_active ); ?>>
					<?php esc_html_e( 'Generate WooCommerce API key', 'matrixweave-for-woocommerce' ); ?>
				</button>
				<?php if ( ! $wc_active ) : ?>
					<p class="description"><?php esc_html_e( 'Activate WooCommerce to generate a key.', 'matrixweave-for-woocommerce' ); ?></p>
				<?php endif; ?>

				<div class="matrixweave-key-output" id="mw-key-output" hidden>
					<p class="matrixweave-key-warning"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Copy the secret now — it is shown only once.', 'matrixweave-for-woocommerce' ); ?></p>
					<div class="matrixweave-field"><label><?php esc_html_e( 'Site URL', 'matrixweave-for-woocommerce' ); ?></label><div class="matrixweave-copy"><code data-field="siteUrl"></code><button type="button" class="button matrixweave-copy-btn"><?php esc_html_e( 'Copy', 'matrixweave-for-woocommerce' ); ?></button></div></div>
					<div class="matrixweave-field"><label><?php esc_html_e( 'Consumer Key', 'matrixweave-for-woocommerce' ); ?></label><div class="matrixweave-copy"><code data-field="consumerKey"></code><button type="button" class="button matrixweave-copy-btn"><?php esc_html_e( 'Copy', 'matrixweave-for-woocommerce' ); ?></button></div></div>
					<div class="matrixweave-field"><label><?php esc_html_e( 'Consumer Secret', 'matrixweave-for-woocommerce' ); ?></label><div class="matrixweave-copy"><code data-field="consumerSecret"></code><button type="button" class="button matrixweave-copy-btn"><?php esc_html_e( 'Copy', 'matrixweave-for-woocommerce' ); ?></button></div></div>
				</div>
			</div>

			<div class="matrixweave-card matrixweave-help">
				<h2><?php esc_html_e( 'How order lookups work', 'matrixweave-for-woocommerce' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'A customer logs into your store as usual.', 'matrixweave-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'This plugin signs their identity on your server using the secret key.', 'matrixweave-for-woocommerce' ); ?></li>
					<li><?php esc_html_e( 'The AI can then answer "where\'s my order?" — for that customer only.', 'matrixweave-for-woocommerce' ); ?></li>
				</ol>
				<p><a href="https://www.matrixweave.com/docs/connect-store" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Read the docs →', 'matrixweave-for-woocommerce' ); ?></a></p>
			</div>
		</div>
	</div>
</div>
