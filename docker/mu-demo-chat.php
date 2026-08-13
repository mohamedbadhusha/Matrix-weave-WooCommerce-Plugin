<?php
/**
 * LOCAL SCREENSHOT HELPER — never ships with the plugin.
 *
 * Opens the Matrixweave chat panel automatically when a page is loaded with
 * ?demo_chat=1, so the headless screenshot run can capture the widget in its
 * open state. Without the query parameter this does nothing at all.
 *
 * Lives only in the throwaway Docker container's mu-plugins folder.
 */

add_action(
	'wp_footer',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- local screenshot helper.
		if ( ! isset( $_GET['demo_chat'] ) ) {
			return;
		}
		?>
		<script>
		( function () {
			var tries = 0;
			var timer = setInterval( function () {
				tries++;
				if ( window.Matrixweave && typeof window.Matrixweave.open === 'function' ) {
					clearInterval( timer );
					window.Matrixweave.open();
				} else if ( tries > 60 ) {
					clearInterval( timer );
				}
			}, 200 );
		} )();
		</script>
		<?php
	},
	99
);
