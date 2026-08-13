<?php
/**
 * LOCAL SCREENSHOT HELPER — never ships with the plugin.
 *
 * Lets the headless screenshot run reach wp-admin without a login form:
 * hitting any URL with ?demo_login=1 authenticates as user 1 and redirects
 * to the same URL without the parameter.
 *
 * This lives only in the throwaway Docker container's mu-plugins folder.
 * It is not in the plugin directory, not in the release zip, and not in SVN.
 */

add_action(
	'init',
	function () {
		if ( ! isset( $_GET['demo_login'] ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			wp_set_auth_cookie( 1 );
		}
		wp_safe_redirect( remove_query_arg( 'demo_login' ) );
		exit;
	}
);
