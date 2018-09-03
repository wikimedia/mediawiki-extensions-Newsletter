<?php

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ?
	getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

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
		$dbw = $this->getDB( DB_MASTER );

		if ( !$this->hasOption( 'delete' ) ) {
			$count = $dbw->selectField(
				'nl_newsletters',
				'COUNT(*)',
				[ 'nl_active' => 0 ],
				__METHOD__
			);
			$this->output( "Found $count inactive newsletters to delete.\n" );
			$this->output( "Please run the script again with the --delete option "
				. "to really delete the revisions.\n" );
			return;
		}

		$this->output( "Getting inactive newsletters...\n" );
		$idsToDelete = $dbw->selectFieldValues(
			'nl_newsletters',
			'nl_id',
			[ 'nl_active' => 0 ],
			__METHOD__
		);

		if ( !$idsToDelete ) {
			$this->output( "No newsletters found to be deleted" );
			return;
		}

		$dbw->delete(
			'nl_newsletters',
			'nl_id = ' . $dbw->makeList( $idsToDelete, LIST_OR ),
			__METHOD__
		);
		$count = $dbw->affectedRows();
		$this->output( "Deleted $count inactive newsletters.\n" );

		$dbw->delete(
			'nl_issues',
			'nli_newsletter_id = ' . $dbw->makeList( $idsToDelete, LIST_OR ),
			__METHOD__
		);
		$count = $dbw->affectedRows();
		$this->output( "Deleted $count inactive newsletter issues.\n" );

		$dbw->delete(
			'nl_publishers',
			'nlp_newsletter_id = ' . $dbw->makeList( $idsToDelete, LIST_OR ),
			__METHOD__
		);
		$count = $dbw->affectedRows();
		$this->output( "Deleted $count inactive newsletter publishers.\n" );

		$dbw->delete(
			'nl_subscriptions',
			'nls_newsletter_id = ' . $dbw->makeList( $idsToDelete, LIST_OR ),
			__METHOD__
		);
		$count = $dbw->affectedRows();
		$this->output( "Deleted $count inactive newsletter subscriptions.\n" );

		$this->output( "Done!\n" );
	}

}

$maintClass = "DeleteInactiveNewsletters";
require_once RUN_MAINTENANCE_IF_MAIN;
