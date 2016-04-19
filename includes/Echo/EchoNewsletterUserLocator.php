<?php

class EchoNewsletterUserLocator {
	/**
	 * Locate all users subscribed to a newsletter.
	 *
	 * @param EchoEvent $event
	 * @return User[]|array empty if the newsletter has beend deleted/invalid
	 */
	public static function locateNewsletterSubscribedUsers( EchoEvent $event ) {
		$extra = $event->getExtra();
		$newsletter = Newsletter::newFromID( (int)$extra['newsletter-id'] );
		if ( !$newsletter ) {
			return array();
		}

		return UserArray::newFromIDs( $newsletter->getSubscribers() );

	}
}
