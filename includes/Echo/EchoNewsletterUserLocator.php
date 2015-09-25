<?php

class EchoNewsletterUserLocator {
	/**
	 * Locate all users subscribed to a newsletter.
	 *
	 * @param EchoEvent $event
	 * @return UserArray
	 */
	public static function locateNewsletterSubscribedUsers( EchoEvent $event ) {
		$extra = $event->getExtra();
		$ids = NewsletterDb::newFromGlobalState()
			->getUserIdsSubscribedToNewsletter( $extra['newsletterId'] );

		return UserArray::newFromIDs( $ids );

	}
}
