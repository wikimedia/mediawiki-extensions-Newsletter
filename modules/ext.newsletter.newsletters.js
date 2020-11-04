/*!
 * Used on Special:Newsletters.
 */
( function () {
	'use strict';

	/**
	 * Choosing an option on the dropdown will submit the form without actually clicking
	 * the button. The button is hidden for users on ext.newsletter.newsletters.styles
	 * which is loaded on load time (instead of runtime unlike this module) to prevent FOUCs.
	 */
	// eslint-disable-next-line no-jquery/no-global-selector
	OO.ui.infuse( $( '#mw-newsletter-filter-options' ) ).on( 'change', function () {
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#mw-newsletter-filter-form' ).trigger( 'submit' );
	} );

	/**
	 * Event handler for clicks on 'action' field link. Allows subscribing and unsubscribing
	 * with a single click. The user is notified once the API request is done.
	 *
	 * @param {string} doAction The action to execute (see newslettersubscribe api
	 *  for possible options)
	 * @param {number} nlId The newsletter id
	 * @return {jQuery.Promise} See #post
	 */
	function doAPIRequest( doAction, nlId ) {
		var api = new mw.Api();

		return api.postWithToken( 'csrf', {
			action: 'newslettersubscribe',
			id: nlId,
			do: doAction
		} );
	}

	function updateLinkAttribs( $link, action ) {
		var inverseAction;

		if ( action === 'subscribe' ) {
			inverseAction = 'unsubscribe';
		} else if ( action === 'unsubscribe' ) {
			inverseAction = 'subscribe';
		} else {
			throw new Error( mw.format(
				'updateLinkAttribs(): `action` must be "subscribe" or "unsubscribe" (got: "$1")',
				action
			) );
		}

		// Invert action name because this is how the class is named.
		// eslint-disable-next-line mediawiki/class-doc
		$link
			.text( mw.msg( 'newsletter-' + action + '-button' ) ) // eslint-disable-line mediawiki/msg-doc
			.removeClass( 'newsletter-' + action + 'd' )
			.addClass( 'newsletter-' + inverseAction + 'd' );
	}

	$( function () {
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '.newsletter-subscription' ).on( 'click', function ( event ) {
			var $link = $( this ),
				newsletterId = $link.data( 'mw-newsletter-id' ),
				$subscriberCount, promise;

			if ( /\D/.test( newsletterId ) ) {
				throw new Error( '"data-mw-newsletter-id" attribute must be numeric' );
			}
			$subscriberCount = $( '#nl-count-' + newsletterId );

			// Avoid double clicks while in progress .newsletter-link-disabled also helps with this
			if ( $link.data( 'nlDisabled' ) ) {
				// For older browsers which doesn't support
				// pointer-events from .newsletter-link-disabled
				return false;
			}

			$link.data( 'nlDisabled', true ).addClass( 'newsletter-link-disabled' );

			// eslint-disable-next-line no-jquery/no-class-state
			if ( $link.hasClass( 'newsletter-subscribed' ) ) {
				// Currently subscribed so let them unsubscribe.
				$link.text( mw.msg( 'newsletter-unsubscribing' ) );
				promise = doAPIRequest( 'unsubscribe', newsletterId )
					.done( function ( data ) {
						updateLinkAttribs( $link, 'subscribe' );
						$subscriberCount.text( parseInt( $subscriberCount.text() ) - 1 );
						mw.notify(
							mw.msg( 'newsletter-unsubscribe-success', data.newslettersubscribe.name )
						);
					} )
					.fail( function () {
						updateLinkAttribs( $link, 'unsubscribe' );
						mw.notify(
							mw.msg( 'newsletter-unsubscribe-error' ),
							{ type: 'error' }
						);
					} );
			} else {
				// Not subscribed currently.
				$link.text( mw.msg( 'newsletter-subscribing' ) );
				promise = doAPIRequest( 'subscribe', newsletterId )
					.done( function ( data ) {
						updateLinkAttribs( $link, 'unsubscribe' );
						$subscriberCount.text( parseInt( $subscriberCount.text() ) + 1 );
						mw.notify(
							mw.msg( 'newsletter-subscribe-success', data.newslettersubscribe.name )
						);
					} )
					.fail( function () {
						updateLinkAttribs( $link, 'subscribe' );
						mw.notify(
							mw.msg( 'newsletter-subscribe-error' ),
							{ type: 'error' }
						);
					} );

			}

			promise.always( function () {
				$link.data( 'nlDisabled', false ).removeClass( 'newsletter-link-disabled' );
			} );

			event.preventDefault();
		} );
	} );
}() );
