<?php

use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ?
	getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';
// @codeCoverageIgnoreEnd

/**
 * @author Addshore
 */
class DeleteInactiveNewsletters extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			"Deletes all inactive newsletters\nThese newsletters will no longer be restorable" );
		$this->addOption( 'delete', 'Performs the deletion' );
		$this->requireExtension( 'Newsletter' );
	}

	public function execute() {
		$dbw = $this->getDB( DB_PRIMARY );

		if ( !$this->hasOption( 'delete' ) ) {
			$count = $dbw->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'nl_newsletters' )
				->where( [ 'nl_active' => 0 ] )
				->caller( __METHOD__ )
				->fetchField();
			$this->output( "Found $count inactive newsletters to delete.\n" );
			$this->output( "Please run the script again with the --delete option "
				. "to really delete the revisions.\n" );
			return;
		}

		$this->output( "Getting inactive newsletters...\n" );
		$idsToDelete = $dbw->newSelectQueryBuilder()
			->select( 'nl_id' )
			->from( 'nl_newsletters' )
			->where( [ 'nl_active' => 0 ] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		if ( !$idsToDelete ) {
			$this->output( "No newsletters found to be deleted" );
			return;
		}

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'nl_newsletters' )
			->where( [ 'nl_id' => $idsToDelete ] )
			->caller( __METHOD__ )
			->execute();
		$count = $dbw->affectedRows();
		$this->output( "Deleted $count inactive newsletters.\n" );

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'nl_issues' )
			->where( [ 'nli_newsletter_id' => $idsToDelete ] )
			->caller( __METHOD__ )
			->execute();
		$count = $dbw->affectedRows();
		$this->output( "Deleted $count inactive newsletter issues.\n" );

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'nl_publishers' )
			->where( [ 'nlp_newsletter_id' => $idsToDelete ] )
			->caller( __METHOD__ )
			->execute();
		$count = $dbw->affectedRows();
		$this->output( "Deleted $count inactive newsletter publishers.\n" );

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'nl_subscriptions' )
			->where( [ 'nls_newsletter_id' => $idsToDelete ] )
			->caller( __METHOD__ )
			->execute();
		$count = $dbw->affectedRows();
		$this->output( "Deleted $count inactive newsletter subscriptions.\n" );

		$this->output( "Done!\n" );
	}

}

// @codeCoverageIgnoreStart
$maintClass = DeleteInactiveNewsletters::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
