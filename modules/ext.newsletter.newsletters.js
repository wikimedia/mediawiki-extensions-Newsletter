/*!
 * Used on Special:Newsletters.
 */
( function ( mw, $ ) {
	'use strict';

	/**
	 * Choosing an option on the dropdown will submit the form without actually clicking
	 * the button. The button is hidden for users on ext.newsletter.newsletters.styles
	 * which is loaded on load time (instead of runtime unlike this module) to prevent FOUCs.
	 */
	OO.ui.infuse( 'mw-newsletter-filter-options' ).on( 'change', function () {
		$( '#mw-newsletter-filter-form' ).submit();
	} );

	/**
	 * Event handler for clicks on 'action' field link. Allows subscribing and unsubscribing
	 * with a single click. The user is notified once the API request is done.
	 */
	function doAPIRequest( action, nlId ) {
		var api = new mw.Api();

		return api.postWithToken( 'csrf', {
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
					.substr( ( $link.prop( 'id' ) ).indexOf( '-' ) + 1 ),
				$subscriberCount = $( 'span#nl-count-' + newsletterId );

			// Avoid double clicks while in progress .newsletter-link-disabled also helps with this
			if ( $link.data( 'nlDisabled' ) ) {
				// For older browsers which doesn't support pointer-events from .newsletter-link-disabled
				return false;
			}

			$link.data( 'nlDisabled', true ).addClass( 'newsletter-link-disabled' );

			if ( $link.hasClass( 'newsletter-subscribed' ) ) {
				// Currently subscribed so let them unsubscribe.
				$link.text( mw.msg( 'newsletter-unsubscribing' ) );
				promise = doAPIRequest( 'unsubscribe', newsletterId )
					.done( function ( data ) {
						updateLinkAttribs( $link, 'subscribe' );
						$subscriberCount.text( parseInt( $subscriberCount.text() ) - 1 );
						mw.notify( mw.msg( 'newsletter-unsubscribe-success', data.newslettersubscribe.name ) );
					} )
					.fail( function () {
						updateLinkAttribs( $link, 'unsubscribe' );
						mw.notify( mw.msg( 'newsletter-unsubscribe-error' ), { type: 'error' } );
					} );
			} else {
				// Not subscribed currently.
				$link.text( mw.msg( 'newsletter-subscribing' ) );
				promise = doAPIRequest( 'subscribe', newsletterId )
					.done( function ( data ) {
						updateLinkAttribs( $link, 'unsubscribe' );
						$subscriberCount.text( parseInt( $subscriberCount.text() ) + 1 );
						mw.notify( mw.msg( 'newsletter-subscribe-success', data.newslettersubscribe.name ) );
					} )
					.fail( function () {
						updateLinkAttribs( $link, 'subscribe' );
						mw.notify( mw.msg( 'newsletter-subscribe-error' ), { type: 'error' } );
					} );

			}

			promise.always( function () {
				$link.data( 'nlDisabled', false ).removeClass( 'newsletter-link-disabled' );
			} );

			event.preventDefault();
		} );
	} );
}( mediaWiki, jQuery ) );
