/* global jQuery, MatrixweaveAdmin */
( function ( $ ) {
	'use strict';

	var cfg = window.MatrixweaveAdmin || {};
	var i18n = cfg.i18n || {};

	function post( action, data, done ) {
		$.post(
			cfg.ajaxUrl,
			$.extend( { action: action, nonce: cfg.nonce }, data || {} ),
			done
		).fail( function () {
			done( { success: false, data: { message: i18n.error } } );
		} );
	}

	function copyText( text, $btn ) {
		var label = $btn.text();
		function flash() {
			$btn.text( i18n.copied );
			setTimeout( function () { $btn.text( label ); }, 1500 );
		}
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( flash, flash );
		} else {
			var $tmp = $( '<textarea>' ).val( text ).appendTo( 'body' ).select();
			try { document.execCommand( 'copy' ); } catch ( e ) {}
			$tmp.remove();
			flash();
		}
	}

	$( function () {

		// Show / hide the secret key field.
		$( '.matrixweave-toggle-secret' ).on( 'click', function () {
			var $input = $( '#matrixweave_secret_key_field' );
			var type = $input.attr( 'type' ) === 'password' ? 'text' : 'password';
			$input.attr( 'type', type );
			$( this ).find( '.dashicons' )
				.toggleClass( 'dashicons-visibility', type === 'password' )
				.toggleClass( 'dashicons-hidden', type === 'text' );
		} );

		// Test the order-lookup (secret key) connection. Sends the CURRENT
		// field values so a freshly pasted (not yet saved) key is what gets
		// tested — previously this tested the stored key, so "paste → Test"
		// failed until the user remembered to hit Save first.
		$( '#matrixweave-test-connection' ).on( 'click', function () {
			var $btn = $( this );
			var $out = $( '#matrixweave-test-result' );
			var data = {
				secret: $.trim( $( '#matrixweave_secret_key_field' ).val() || '' ),
				api_url: $.trim( $( '#matrixweave_api_url_field' ).val() || '' )
			};
			$btn.prop( 'disabled', true );
			$out.removeClass( 'is-ok is-error' ).text( i18n.testing );
			post( cfg.testAction, data, function ( res ) {
				$btn.prop( 'disabled', false );
				var msg = ( res && res.data && res.data.message ) || i18n.error;
				// A typed-but-unsaved key that verifies still needs Save.
				if ( res && res.success && data.secret ) {
					msg += ' ' + i18n.saveReminder;
				}
				$out.text( msg ).addClass( res && res.success ? 'is-ok' : 'is-error' );
			} );
		} );

		// Keep the in-form hidden mirror in sync with the sidebar Read/Write
		// checkbox so "Save changes" persists it across reloads.
		$( '#matrixweave-key-write' ).on( 'change', function () {
			$( '#matrixweave-key-write-mirror' ).val( $( this ).is( ':checked' ) ? 'yes' : 'no' );
		} );

		// Generate a WooCommerce REST API key.
		$( '#matrixweave-generate-key' ).on( 'click', function () {
			var $btn = $( this );
			var write = $( '#matrixweave-key-write' ).is( ':checked' ) ? '1' : '0';
			var original = $btn.text();
			$btn.prop( 'disabled', true ).text( i18n.generating );
			post( cfg.generateAction, { write: write }, function ( res ) {
				$btn.prop( 'disabled', false ).text( original );
				if ( ! res || ! res.success || ! res.data ) {
					window.alert( ( res && res.data && res.data.message ) || i18n.error );
					return;
				}
				var d = res.data;
				var $out = $( '#matrixweave-key-output' );
				$out.find( '[data-field="siteUrl"]' ).text( d.siteUrl || '' );
				$out.find( '[data-field="consumerKey"]' ).text( d.consumerKey || '' );
				$out.find( '[data-field="consumerSecret"]' ).text( d.consumerSecret || '' );
				$out.prop( 'hidden', false );
			} );
		} );

		// Copy buttons for the generated key.
		$( document ).on( 'click', '.matrixweave-copy-btn', function () {
			var text = $( this ).closest( '.matrixweave-copy' ).find( 'code' ).text();
			copyText( text, $( this ) );
		} );
	} );

} )( jQuery );
