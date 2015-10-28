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
		// @todo rename event as this is misleading - we're not really subscribing here
		$notifications['subscribe-newsletter'] = array(
			'primary-link' => array(
				'message' => 'newsletter-notification-link-text-new-issue',
				'destination' => 'new-issue'
			),
			'user-locators' => array(
				'EchoNewsletterUserLocator::locateNewsletterSubscribedUsers',
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
	 * Allows to add our own error message to LoginForm
	 *
	 * @param array $messages
	 */
	public static function onLoginFormValidErrorMessages( &$messages ) {
		$messages[] = 'newsletter-subscribe-loginrequired'; // on Special:Newsletter/id/subscribe
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

	/**
	 * Tables that Extension:UserMerge needs to update
	 *
	 * @param array $updateFields
	 * @return bool
	 */
	public static function onUserMergeAccountFields( array &$updateFields ) {
		$updateFields[] = array( 'nl_publishers', 'nlp_publisher_id' );
		$updateFields[] = array( 'nl_subscriptions', 'nls_subscriber_id' );

		return true;
	}
}
