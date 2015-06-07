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
		$updater->addExtensionTable( 'newsletters', __DIR__ . '/sql/newsletter.sql', true );

		return true;
	}

}
