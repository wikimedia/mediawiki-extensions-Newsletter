<?php

namespace MediaWiki\Extension\Newsletter\Notifications;

use EchoEvent;
use MediaWiki\Extension\Newsletter\Newsletter;
use MediaWiki\User\UserArray;
use MediaWiki\User\UserArrayFromResult;

class EchoNewsletterUserLocator {

	/**
	 * Locate all users subscribed to a newsletter.
	 *
	 * @param EchoEvent $event
	 * @return UserArrayFromResult|array empty if the newsletter has been deleted/invalid
	 */
	public static function locateNewsletterSubscribedUsers( EchoEvent $event ) {
		$extra = $event->getExtra();
		$newsletter = Newsletter::newFromID( (int)$extra['newsletter-id'] );
		if ( !$newsletter ) {
			return [];
		}

		return UserArray::newFromIDs( $newsletter->getSubscribers() );
	}

}
