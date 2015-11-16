/**
 * Javascript for managing newsletters
 *
 */
( function ( $, mw ) {
	'use strict';
	var api = new mw.Api();
	$( 'input[type=button]').click( function() {
		var remNewsletterId = this.name;
		var publisherId = this.id;
		var $that = $( this );

		api.postWithToken( 'edit', {
			action: 'newslettermanage',
			publisher: publisherId,
			id: remNewsletterId,
			do: 'removepublisher'

		} ).done( function ( data ) {
			mw.log( data );
			$that.closest( 'tr' ).remove();
		} );
	} );
} )( jQuery, mediaWiki );
