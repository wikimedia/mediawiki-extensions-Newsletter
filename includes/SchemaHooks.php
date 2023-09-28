<?php
namespace MediaWiki\Extension\Newsletter;

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * Class to add schema hooks used by Newsletter.
 */
class SchemaHooks implements LoadExtensionSchemaUpdatesHook {

	/**
	 * Add tables to Database
	 *
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$updater->addExtensionTable( 'nl_newsletters', __DIR__ . '/../sql/' . $type . '/tables-generated.sql' );
	}
}
