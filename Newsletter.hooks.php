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
	public static function onBeforeCreateEchoEvent( &$notifications, &$notificationCategories  ) {
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
	* @param EchoEvent $event
	* @param User[] $users
	* @return bool
	*/
	public static function onEchoGetDefaultNotifiedUsers( $event, &$users ) {
		$extra = $event->getExtra();
		$eventType = $event->getType();
		if ( $eventType === 'subscribe-newsletter' ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				'nl_subscriptions',
				array( 'subscriber_id' ),
				array( 'newsletter_id' => $extra['newsletterId'] ),
				__METHOD__,
				array()
			);
			foreach( $res as $row ) {
				$recipient = User::newFromId( $row->subscriber_id );
				$id = $row->subscriber_id;
				$users[$id] = $recipient;
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
		$updater->addExtensionTable( 'nl_newsletters', __DIR__ . '/sql/nl_newsletters.sql', true );
		$updater->addExtensionTable( 'nl_issues', __DIR__ . '/sql/nl_issues.sql', true );
		$updater->addExtensionTable( 'nl_subscriptions', __DIR__ . '/sql/nl_subscriptions.sql', true );
		$updater->addExtensionTable( 'nl_publishers', __DIR__ . '/sql/nl_publishers.sql', true );

		return true;
	}

	public static function onUnitTestsList( &$files ) {
		$files = array_merge( $files, glob( __DIR__ . '/tests/*Test.php' ) );

		return true;
	}
}
