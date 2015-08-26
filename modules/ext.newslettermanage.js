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
		api.post( {
			action: 'newslettermanageapi',
			publisher: publisherId,
			newsletterId: remNewsletterId,
			todo: 'removepublisher'

		} ).done( function ( data ) {
			mw.log( data );
		} );
		$( this ).closest( 'tr' ).remove();
	} );
} )( jQuery, mediaWiki );
