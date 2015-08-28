<?php

/**
 * Class to add Hooks used by Newsletter.
 */
class NewsletterHooks {

	/**
	 * Function to be called before EchoEvent
	 *
	 * @param array $notifications Echo notifications
	 * @param array $notificationCategories Echo notification categories
	 * @return bool
	 */
	public static function onBeforeCreateEchoEvent( &$notifications, &$notificationCategories ) {
		$notificationCategories['newsletter'] = array(
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-newsletter',
		);
		$notifications['subscribe-newsletter'] = array(
			'primary-link' => array(
				'message' => 'newsletter-notification-link-text-new-issue',
				'destination' => 'new-issue'
			),
			'formatter-class' => 'EchoNewsletterFormatter',
			'title-message' => 'newsletter-notification-title',
			'title-params' => array( 'newsletter', 'title' ),
			'flyout-message' => 'newsletter-notification-flyout',
			'flyout-params' => array( 'newsletter', 'title' ),

		);
		return true;
	}

	/**
	 * Add user to be notified on echo event
	 *
	 * @todo Use the JobQueue for this to make sure it scales amazingly
	 *
	 * @param EchoEvent $event
	 * @param User[] $users
	 * @return bool
	 */
	public static function onEchoGetDefaultNotifiedUsers( $event, &$users ) {
		$eventType = $event->getType();
		if ( $eventType === 'subscribe-newsletter' ) {
			$extra = $event->getExtra();

			$db = NewsletterDb::newFromGlobalState();
			$userIds = $db->getUserIdsSubscribedToNewsletter( $extra['newsletterId'] );

			//TODO queries to the user table should be done in batches using UserArray::newFromIds
			foreach ( $userIds as $userId ) {
				$recipient = User::newFromId( $userId );
				$users[$userId] = $recipient;
			}
		}

		return true;
	}

	/**
	 * Add tables to Database
	 *
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'nl_newsletters', __DIR__ . '/sql/nl_newsletters.sql' );
		$updater->addExtensionTable( 'nl_issues', __DIR__ . '/sql/nl_issues.sql' );
		$updater->addExtensionTable( 'nl_subscriptions', __DIR__ . '/sql/nl_subscriptions.sql' );
		$updater->addExtensionTable( 'nl_publishers', __DIR__ . '/sql/nl_publishers.sql' );

		return true;
	}

	public static function onUnitTestsList( &$files ) {
		$files = array_merge( $files, glob( __DIR__ . '/tests/*Test.php' ) );

		return true;
	}

}
