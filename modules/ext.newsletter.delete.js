/**
 * Ask for confirmation on newsletter deletion form
 *
 */
( function ( mw, $, OO ) {
	'use strict';
	$( '#newsletter-delete-button button' ).on( 'click', function( e ) {
		var messageDialog = new OO.ui.MessageDialog();
		var windowManager = new OO.ui.WindowManager();

		$( 'body' ).append( windowManager.$element );
		windowManager.addWindows( [ messageDialog ] );

		windowManager.openWindow( messageDialog, {
			title: mw.msg( 'newsletter-delete-confirmation' ),
			message: mw.msg( 'newsletter-delete-confirm-details' ),
			actions: [ {
				label: mw.msg( 'newsletter-delete-confirm-cancel' ),
				flags: 'safe'
			}, {
				label: mw.msg( 'newsletter-delete-confirm-delete' ),
				action: 'delete',
				flags: [ 'primary', 'destructive' ]
			}, ],
		} )
		.then( function( opened ) {
			opened.then( function( closing, data ) {
				if ( data && data.action === 'delete' ) {
					// Clicked delete button - submit the form and do the deletion
					$( '#newsletter-delete-form' ).submit();
				}
				// Clicked cancel button - close dialog box
			} );
		} );
		e.preventDefault();
	} );
}( mediaWiki, jQuery, OO ) );
