jQuery( function( $ ) {

	$( '.gf-cardconnect-sunset-warning .notice-dismiss' ).on( 'click', function() {
		$.post( ajaxurl, {
			action: 'gf_cardconnect_dismiss_sunset_warning'
		} );
	} );
} );
