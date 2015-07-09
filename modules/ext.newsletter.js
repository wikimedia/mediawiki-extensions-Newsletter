/**
 * Javascript for radio buttons
 *
 */
( function ( $, mw ) {
	'use strict';

	var api = new mw.Api();
	$( 'input[type=radio][value=subscribe]' ).change( function() {
		var newsletterId = ( this.name ).substr( ( this.name ).indexOf( "-" ) + 1 );
		api.post( {
			action: 'newsletterapi',
			newsletterId: newsletterId,
			todo: 'subscribe'
		} ).done( function ( data ) {
			console.log( data );
		} );
        document.getElementById( 'newsletter-' + newsletterId).value++;

	} );

	$( 'input[type=radio][value=unsubscribe]' ).change( function() {
		var newsletterId = ( this.name ).substr( ( this.name ).indexOf( "-" ) + 1 );
		api.post( {
			action: 'newsletterapi',
			newsletterId: newsletterId,
			todo: 'unsubscribe'

		} ).done( function ( data ) {
			console.log( data );
		} );
        document.getElementById( 'newsletter-' + newsletterId).value--;
	} );

} )( jQuery, mediaWiki );
