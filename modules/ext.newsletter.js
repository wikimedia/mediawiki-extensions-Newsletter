/**
 * Used on Special:Newsletters. Event handler for link clicks on 'action' field.
 */
( function ( mw, $ ) {
	'use strict';

	function doAPIRequest( action, nlId ) {
		var api = new mw.Api();

		return api.postWithToken( 'edit', {
			action: 'newslettersubscribe',
			id: nlId,
			do: action
		} );
	}

	function updateLinkAttribs( $link, action ) {
		$link
			.text( mw.msg( 'newsletter-' + action + '-button' ) )
			.removeClass( 'newsletter-' + action + 'd' );

		// Invert action name because this is how the class is named.
		var invertAction = action === 'subscribe' ? 'unsubscribed' : 'subscribed';
		$link.addClass( 'newsletter-' + invertAction );
	}

	$( function () {
		$( 'a.newsletter-subscription' ).click( function ( event ) {
			var promise,
				$link = $( this ),
				newsletterId = ( $link.prop( 'id' ) )
					.substr( ( $link.prop( 'id' ) ).indexOf( '-' ) + 1 );

			// Avoid double clicks while in progress .newsletter-link-disabled also helps with this
			if ( $link.data( 'nlDisabled' ) ) {
				// For older browsers which doesn't support pointer-events from .newsletter-link-disabled
				return false;
			}

			$link.data( 'nlDisabled', true ).addClass( 'newsletter-link-disabled' );

			if ( $link.hasClass( 'newsletter-subscribed' ) ) {
				// Currently subscribed so let them unsubscribe.
				$link.text( mw.msg( 'newsletter-unsubscribing' ) );
				// @todo Handle failures as well
				promise = doAPIRequest( 'unsubscribe', newsletterId )
					.done( function ( data ) {
						updateLinkAttribs( $link, 'subscribe' );
						$( 'input#newsletter-' + newsletterId ).get( 0 ).value--;
						mw.notify( mw.msg( 'newsletter-unsubscribe-success', data.newslettersubscribe.name ) );
					} );
			} else {
				// Not subscribed currently.
				$link.text( mw.msg( 'newsletter-subscribing' ) );
				// @todo Handle failures as well
				promise = doAPIRequest( 'subscribe', newsletterId )
					.done( function ( data ) {
						updateLinkAttribs( $link, 'unsubscribe' );
						$( 'input#newsletter-' + newsletterId ).get( 0 ).value++;
						mw.notify( mw.msg( 'newsletter-subscribe-success', data.newslettersubscribe.name ) );
					} );
			}

			promise.always( function () {
				$link.data( 'nlDisabled', false ).removeClass( 'newsletter-link-disabled' );
			} );

			event.preventDefault();
		} );
	} );
}( mediaWiki, jQuery ) );
