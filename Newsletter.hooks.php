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

		$notifications['newsletter-announce'] = array(
			'category' => 'newsletter',
			'section' => 'alert',
			'primary-link' => array(
				'message' => 'newsletter-notification-link-text-new-issue',
				'destination' => 'new-issue'
			),
			'secondary-link' => array(
				'message' => 'newsletter-notification-link-text-view-newsletter',
				'destination' => 'newsletter'
			),
			'user-locators' => array(
				'EchoNewsletterUserLocator::locateNewsletterSubscribedUsers',
			),
			'presentation-model' => 'EchoNewsletterPresentationModel',
			// 'formatter-class' => 'EchoNewsletterFormatter',
			'title-message' => 'newsletter-notification-title',
			'title-params' => array( 'newsletter', 'title', 'agent' ),
			'flyout-message' => 'newsletter-notification-flyout',
			'payload' => array( 'summary' ),
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

	/**
	 * Handler for UnitTestsList hook.
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 * @param &$files Array of unit test files
	 * @return bool true in all cases
	 */
	public static function onUnitTestsList( &$files ) {
		// @codeCoverageIgnoreStart
		$directoryIterator = new RecursiveDirectoryIterator( __DIR__ . '/tests/' );

		/**
		 * @var SplFileInfo $fileInfo
		 */
		$ourFiles = array();
		foreach ( new RecursiveIteratorIterator( $directoryIterator ) as $fileInfo ) {
			if ( substr( $fileInfo->getFilename(), -8 ) === 'Test.php' ) {
				$ourFiles[] = $fileInfo->getPathname();
			}
		}

		$files = array_merge( $files, $ourFiles );

		return true;
		// @codeCoverageIgnoreEnd
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
