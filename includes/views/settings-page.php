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

$matrixweave_settings         = matrixweave()->settings;
$matrixweave_has_secret       = '' !== $matrixweave_settings->get_secret_key();
$matrixweave_secret_locked    = $matrixweave_settings->secret_is_from_constant();
$matrixweave_public_key       = $matrixweave_settings->get_public_key();
$matrixweave_order_lookup_on  = $matrixweave_settings->is_order_lookup_enabled();
$matrixweave_embed_on         = $matrixweave_settings->is_embed_enabled();
$matrixweave_wc_active        = class_exists( 'WooCommerce' );

$matrixweave_connect   = matrixweave()->connect;
$matrixweave_connected = $matrixweave_connect->is_connected();

// Live state, asked rather than assumed — a stored "connected" flag stays green
// through revoked credentials, a failed sync and an empty catalog. Only fetched
// once connected, so an unconfigured site makes no outbound call at all.
$matrixweave_status  = null;
$matrixweave_account = null;
if ( $matrixweave_connected ) {
	$matrixweave_api     = new Matrixweave_API( $matrixweave_settings->get_api_url(), $matrixweave_settings->get_secret_key() );
	$matrixweave_status  = $matrixweave_api->erp_status();
	$matrixweave_account = $matrixweave_api->account_status();
}
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
		<span class="matrixweave-pill <?php echo $matrixweave_public_key ? 'is-on' : 'is-off'; ?>">
			<?php echo $matrixweave_public_key ? esc_html__( 'Widget key set', 'matrixweave-for-woocommerce' ) : esc_html__( 'Widget key missing', 'matrixweave-for-woocommerce' ); ?>
		</span>
		<span class="matrixweave-pill <?php echo $matrixweave_has_secret ? 'is-on' : 'is-off'; ?>">
			<?php echo $matrixweave_has_secret ? esc_html__( 'Order lookups ready', 'matrixweave-for-woocommerce' ) : esc_html__( 'Order lookups off', 'matrixweave-for-woocommerce' ); ?>
		</span>
		<span class="matrixweave-pill <?php echo $matrixweave_wc_active ? 'is-on' : 'is-off'; ?>">
			<?php echo $matrixweave_wc_active ? esc_html__( 'WooCommerce active', 'matrixweave-for-woocommerce' ) : esc_html__( 'WooCommerce inactive', 'matrixweave-for-woocommerce' ); ?>
		</span>
	</div>

	<?php if ( ! $matrixweave_connected ) : ?>
		<?php /* The whole setup, in one button. No keys to find, nothing to paste. */ ?>
		<div class="matrixweave-card">
			<h2><?php esc_html_e( 'Connect your store', 'matrixweave-for-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'One click sets everything up — your workspace, the chat widget, and your product and order sync. No keys to copy.', 'matrixweave-for-woocommerce' ); ?></p>
			<p class="description">
				<?php esc_html_e( 'You’ll sign in (or create a free account) on matrixweave.com and come straight back here. The free plan includes 100 AI chats a month, with no card required.', 'matrixweave-for-woocommerce' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $matrixweave_connect->start_url() ); ?>" class="button button-primary button-hero">
					<?php esc_html_e( 'Connect Matrixweave', 'matrixweave-for-woocommerce' ); ?>
				</a>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: this site's address. */
					esc_html__( 'Connecting tells Matrixweave your site address (%s) so it can read your catalog. Nothing is sent until you press the button.', 'matrixweave-for-woocommerce' ),
					'<code>' . esc_html( $matrixweave_connect->site_url() ) . '</code>'
				);
				?>
			</p>
		</div>
	<?php else : ?>
		<div class="matrixweave-card">
			<h2><?php esc_html_e( 'Your workspace', 'matrixweave-for-woocommerce' ); ?></h2>
			<?php if ( is_array( $matrixweave_account ) ) : ?>
				<p>
					<strong><?php echo esc_html( isset( $matrixweave_account['workspaceName'] ) ? $matrixweave_account['workspaceName'] : '' ); ?></strong>
					<?php if ( ! empty( $matrixweave_account['plan'] ) ) : ?>
						— <?php echo esc_html( $matrixweave_account['plan'] ); ?>
					<?php endif; ?>
				</p>
				<?php if ( isset( $matrixweave_account['conversationsUsed'] ) ) : ?>
					<p class="description">
						<?php
						$matrixweave_used     = (int) $matrixweave_account['conversationsUsed'];
						$matrixweave_included = isset( $matrixweave_account['conversationsIncluded'] ) ? (int) $matrixweave_account['conversationsIncluded'] : 0;
						echo esc_html(
							$matrixweave_included > 0
								/* translators: 1: chats used, 2: chats included in the plan. */
								? sprintf( __( '%1$d of %2$d AI chats used this month.', 'matrixweave-for-woocommerce' ), $matrixweave_used, $matrixweave_included )
								/* translators: %d: chats used. */
								: sprintf( __( '%d AI chats this month.', 'matrixweave-for-woocommerce' ), $matrixweave_used )
						);
						?>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Connected. (Couldn’t reach Matrixweave for plan details just now.)', 'matrixweave-for-woocommerce' ); ?></p>
			<?php endif; ?>

			<?php
			// Four honest outcomes, from the live probe — never a bare "connected".
			if ( is_array( $matrixweave_status ) ) :
				$matrixweave_products = isset( $matrixweave_status['productCount'] ) ? (int) $matrixweave_status['productCount'] : 0;
				$matrixweave_readable = ! empty( $matrixweave_status['ordersReadable'] );
				?>
				<?php if ( empty( $matrixweave_status['connected'] ) ) : ?>
					<p class="matrixweave-pill is-off"><?php esc_html_e( 'Catalog not connected', 'matrixweave-for-woocommerce' ); ?></p>
				<?php elseif ( ! $matrixweave_readable ) : ?>
					<p class="matrixweave-pill is-off"><?php esc_html_e( 'Connected, but your store isn’t answering — order lookups will fail.', 'matrixweave-for-woocommerce' ); ?></p>
					<?php if ( ! empty( $matrixweave_status['syncError'] ) ) : ?>
						<p class="description"><?php echo esc_html( $matrixweave_status['syncError'] ); ?></p>
					<?php endif; ?>
				<?php elseif ( 0 === $matrixweave_products ) : ?>
					<p class="matrixweave-pill is-off"><?php esc_html_e( 'Connected, but no products have synced yet.', 'matrixweave-for-woocommerce' ); ?></p>
				<?php else : ?>
					<p class="matrixweave-pill is-on">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of products synced. */
								_n( '%d product synced, and order lookups are working.', '%d products synced, and order lookups are working.', $matrixweave_products, 'matrixweave-for-woocommerce' ),
								$matrixweave_products
							)
						);
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<p>
				<a href="<?php echo esc_url( $matrixweave_connect->dashboard_url() . '/billing' ); ?>" class="button" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Manage plan', 'matrixweave-for-woocommerce' ); ?>
				</a>
				<a href="<?php echo esc_url( $matrixweave_connect->dashboard_url() . '/conversations' ); ?>" class="button" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Open dashboard', 'matrixweave-for-woocommerce' ); ?>
				</a>
				<a href="<?php echo esc_url( $matrixweave_connect->start_url() ); ?>" class="button">
					<?php esc_html_e( 'Reconnect', 'matrixweave-for-woocommerce' ); ?>
				</a>
				<a href="<?php echo esc_url( $matrixweave_connect->disconnect_url() ); ?>" class="button button-link-delete">
					<?php esc_html_e( 'Disconnect', 'matrixweave-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<div class="matrixweave-grid">
		<div class="matrixweave-main">
			<form method="post" action="options.php">
				<?php settings_fields( Matrixweave_Settings::GROUP ); ?>

				<div class="matrixweave-card">
					<h2><?php esc_html_e( '1. Your Matrixweave keys', 'matrixweave-for-woocommerce' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Filled in for you when you connect. You only need to touch these if you are moving the site to a different workspace by hand.', 'matrixweave-for-woocommerce' ); ?>
					</p>
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
								<label for="matrixweave_public_key_field"><?php esc_html_e( 'Public key', 'matrixweave-for-woocommerce' ); ?></label>
							</th>
							<td>
								<input type="text" id="matrixweave_public_key_field" class="regular-text code"
									name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[public_key]"
									value="<?php echo esc_attr( $matrixweave_public_key ); ?>"
									placeholder="pk_..." autocomplete="off" />
								<p class="description"><?php esc_html_e( 'Starts with pk_. Safe to expose — this is what loads the widget in the browser.', 'matrixweave-for-woocommerce' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="matrixweave_secret_key_field"><?php esc_html_e( 'Secret key', 'matrixweave-for-woocommerce' ); ?></label>
							</th>
							<td>
								<?php if ( $matrixweave_secret_locked ) : ?>
									<p><code>MATRIXWEAVE_SECRET_KEY</code> <?php esc_html_e( 'is defined in wp-config.php — using that. This field is disabled.', 'matrixweave-for-woocommerce' ); ?></p>
								<?php else : ?>
									<input type="password" id="matrixweave_secret_key_field" class="regular-text code"
										name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[secret_key]"
										value="" autocomplete="new-password"
										placeholder="<?php echo $matrixweave_has_secret ? esc_attr__( '•••••••• saved — leave blank to keep', 'matrixweave-for-woocommerce' ) : 'sk_...'; ?>" />
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
									<input type="checkbox" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[embed_widget]" value="yes" <?php checked( $matrixweave_embed_on ); ?> />
									<span><?php esc_html_e( 'Automatically load the chat on the storefront. Turn off if you already paste the snippet yourself.', 'matrixweave-for-woocommerce' ); ?></span>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Personalized order lookups', 'matrixweave-for-woocommerce' ); ?></th>
							<td>
								<label class="matrixweave-switch">
									<input type="hidden" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[order_lookup]" value="no" />
									<input type="checkbox" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[order_lookup]" value="yes" <?php checked( $matrixweave_order_lookup_on ); ?> />
									<span><?php esc_html_e( 'Let the AI answer "where\'s my order?" for logged-in customers (needs a secret key).', 'matrixweave-for-woocommerce' ); ?></span>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="matrixweave_mode_field"><?php esc_html_e( 'Agent mode', 'matrixweave-for-woocommerce' ); ?></label></th>
							<td>
								<select id="matrixweave_mode_field" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[mode]">
									<option value="auto" <?php selected( $matrixweave_settings->get_mode(), 'auto' ); ?>><?php esc_html_e( 'Auto (Sales + Support)', 'matrixweave-for-woocommerce' ); ?></option>
									<option value="sales_agent" <?php selected( $matrixweave_settings->get_mode(), 'sales_agent' ); ?>><?php esc_html_e( 'Sales', 'matrixweave-for-woocommerce' ); ?></option>
									<option value="support_agent" <?php selected( $matrixweave_settings->get_mode(), 'support_agent' ); ?>><?php esc_html_e( 'Support', 'matrixweave-for-woocommerce' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="matrixweave_primary_color_field"><?php esc_html_e( 'Accent color', 'matrixweave-for-woocommerce' ); ?></label></th>
							<td>
								<input type="text" id="matrixweave_primary_color_field" class="regular-text"
									name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[primary_color]"
									value="<?php echo esc_attr( $matrixweave_settings->get_primary_color() ); ?>" placeholder="#6366f1" />
								<p class="description"><?php esc_html_e( 'Optional — overrides the color set in your dashboard. Leave blank to use the dashboard value.', 'matrixweave-for-woocommerce' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="matrixweave_greeting_field"><?php esc_html_e( 'Greeting', 'matrixweave-for-woocommerce' ); ?></label></th>
							<td>
								<input type="text" id="matrixweave_greeting_field" class="large-text"
									name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[greeting]"
									value="<?php echo esc_attr( $matrixweave_settings->get_greeting() ); ?>" placeholder="<?php esc_attr_e( 'Hi! How can I help?', 'matrixweave-for-woocommerce' ); ?>" />
							</td>
						</tr>
					</table>

					<details class="matrixweave-advanced">
						<summary><?php esc_html_e( 'Advanced (API endpoints)', 'matrixweave-for-woocommerce' ); ?></summary>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="matrixweave_api_url_field"><?php esc_html_e( 'API URL', 'matrixweave-for-woocommerce' ); ?></label></th>
								<td><input type="url" id="matrixweave_api_url_field" class="regular-text code" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[api_url]" value="<?php echo esc_attr( $matrixweave_settings->get_api_url() ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="matrixweave_widget_url_field"><?php esc_html_e( 'Widget script URL', 'matrixweave-for-woocommerce' ); ?></label></th>
								<td><input type="url" id="matrixweave_widget_url_field" class="regular-text code" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[widget_url]" value="<?php echo esc_attr( $matrixweave_settings->get_widget_url() ); ?>" /></td>
							</tr>
						</table>
					</details>

					<?php // Mirrors the Connect-your-catalog Read/Write checkbox (sidebar, outside this form) so Save persists it. Kept in sync by admin.js. ?>
					<input type="hidden" id="matrixweave-key-write-mirror" name="<?php echo esc_attr( Matrixweave_Settings::OPTION_KEY ); ?>[key_write]" value="<?php echo esc_attr( $matrixweave_settings->is_key_write_enabled() ? 'yes' : 'no' ); ?>" />

					<p class="submit">
						<?php submit_button( __( 'Save changes', 'matrixweave-for-woocommerce' ), 'primary', 'submit', false ); ?>
						<button type="button" class="button button-secondary matrixweave-test-btn" id="matrixweave-test-connection">
							<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Test order-lookup connection', 'matrixweave-for-woocommerce' ); ?>
						</button>
						<span class="matrixweave-test-result" id="matrixweave-test-result" aria-live="polite"></span>
					</p>
				</div>
			</form>
		</div>

		<div class="matrixweave-side">
			<div class="matrixweave-card matrixweave-connect">
				<h2><?php esc_html_e( 'Connect your catalog', 'matrixweave-for-woocommerce' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Generate a read-only WooCommerce API key, then paste it into Matrixweave → ERP Connections → Add Source → WooCommerce. No digging through WooCommerce settings.', 'matrixweave-for-woocommerce' ); ?></p>

				<label class="matrixweave-switch matrixweave-write-toggle">
					<input type="checkbox" id="matrixweave-key-write" value="1" <?php checked( $matrixweave_settings->is_key_write_enabled() ); ?> />
					<span><?php esc_html_e( 'Allow the agent to create orders (Read/Write)', 'matrixweave-for-woocommerce' ); ?></span>
				</label>

				<button type="button" class="button button-primary" id="matrixweave-generate-key" <?php disabled( ! $matrixweave_wc_active ); ?>>
					<?php esc_html_e( 'Generate WooCommerce API key', 'matrixweave-for-woocommerce' ); ?>
				</button>
				<?php if ( ! $matrixweave_wc_active ) : ?>
					<p class="description"><?php esc_html_e( 'Activate WooCommerce to generate a key.', 'matrixweave-for-woocommerce' ); ?></p>
				<?php endif; ?>

				<div class="matrixweave-key-output" id="matrixweave-key-output" hidden>
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
