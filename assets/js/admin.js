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
			var $input = $( '#mw_secret_key' );
			var type = $input.attr( 'type' ) === 'password' ? 'text' : 'password';
			$input.attr( 'type', type );
			$( this ).find( '.dashicons' )
				.toggleClass( 'dashicons-visibility', type === 'password' )
				.toggleClass( 'dashicons-hidden', type === 'text' );
		} );

		// Test the order-lookup (secret key) connection.
		$( '#mw-test-connection' ).on( 'click', function () {
			var $btn = $( this );
			var $out = $( '#mw-test-result' );
			$btn.prop( 'disabled', true );
			$out.removeClass( 'is-ok is-error' ).text( i18n.testing );
			post( cfg.testAction, {}, function ( res ) {
				$btn.prop( 'disabled', false );
				var msg = ( res && res.data && res.data.message ) || i18n.error;
				$out.text( msg ).addClass( res && res.success ? 'is-ok' : 'is-error' );
			} );
		} );

		// Generate a WooCommerce REST API key.
		$( '#mw-generate-key' ).on( 'click', function () {
			var $btn = $( this );
			var write = $( '#mw-key-write' ).is( ':checked' ) ? '1' : '0';
			var original = $btn.text();
			$btn.prop( 'disabled', true ).text( i18n.generating );
			post( cfg.generateAction, { write: write }, function ( res ) {
				$btn.prop( 'disabled', false ).text( original );
				if ( ! res || ! res.success || ! res.data ) {
					window.alert( ( res && res.data && res.data.message ) || i18n.error );
					return;
				}
				var d = res.data;
				var $out = $( '#mw-key-output' );
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
