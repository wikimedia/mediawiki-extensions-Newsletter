<?php

class EchoNewsletterUserLocator {
	/**
	 * Locate all users subscribed to a newsletter.
	 *
	 * @param EchoEvent $event
	 * @return User[]
	 */
	public static function locateNewsletterSubscribedUsers( EchoEvent $event ) {
		$extra = $event->getExtra();
		$ids = NewsletterDb::newFromGlobalState()
			->getSubscribersFromID( $extra['newsletter-id'] );

		return UserArray::newFromIDs( $ids );

	}
}
