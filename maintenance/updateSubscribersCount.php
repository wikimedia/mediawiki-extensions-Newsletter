<?php

use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ?
	getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';
// @codeCoverageIgnoreEnd

class UpdateSubscribersCount extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			"Regenerate nl_subscribers_count in nl_newsletters from nl_subscriptions table" );
		$this->requireExtension( 'Newsletter' );
	}

	public function execute() {
		$dbw = $this->getDB( DB_PRIMARY );
		$offset = 0;

		while ( true ) {
			$res = $dbw->newSelectQueryBuilder()
				->select( [ 'nl_id', 'subscriber_count' => 'COUNT(nls_subscriber_id)' ] )
				->from( 'nl_newsletters' )
				->leftJoin( 'nl_subscriptions', null, 'nls_newsletter_id=nl_id' )
				->where( $dbw->expr( 'nl_id', '>', $offset ) )
				->groupBy( 'nl_id' )
				->limit( 50 )
				->orderBy( 'nl_id' )
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( $res->numRows() === 0 ) {
				break;
			}

			foreach ( $res as $row ) {
				$dbw->newUpdateQueryBuilder()
					->update( 'nl_newsletters' )
					// This column is negative for index reasons.
					->set( [ 'nl_subscriber_count' => -$row->subscriber_count ] )
					->where( [ 'nl_id' => $row->nl_id ] )
					->caller( __METHOD__ )
					->execute();
			}

			$this->output( "Updated " . $res->numRows() . " rows \n" );

			$this->waitForReplication();

			// We need to get the last element and add to offset.
			// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
			$offset = $row->nl_id;
		}

		$this->output( "Done!\n" );
	}

}

// @codeCoverageIgnoreStart
$maintClass = UpdateSubscribersCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
