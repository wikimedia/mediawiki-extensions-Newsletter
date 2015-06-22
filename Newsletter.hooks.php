<?php
/**
 * Class to add Hooks used by Newsletter.
 */
class NewsletterHooks {
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

		return true;
	}

}
