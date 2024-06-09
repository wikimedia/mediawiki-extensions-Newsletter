<?php

namespace MediaWiki\Extension\Newsletter\Notifications;

use MediaWiki\Extension\Newsletter\Newsletter;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\User\UserArray;
use MediaWiki\User\UserArrayFromResult;

class EchoNewsletterUserLocator {

	/**
	 * Locate all users subscribed to a newsletter.
	 *
	 * @param Event $event
	 * @return UserArrayFromResult|array empty if the newsletter has been deleted/invalid
	 */
	public static function locateNewsletterSubscribedUsers( Event $event ) {
		$extra = $event->getExtra();
		$newsletter = Newsletter::newFromID( (int)$extra['newsletter-id'] );
		if ( !$newsletter ) {
			return [];
		}

		return UserArray::newFromIDs( $newsletter->getSubscribers() );
	}

}
