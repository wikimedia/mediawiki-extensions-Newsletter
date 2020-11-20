<?php

use MediaWiki\MediaWikiServices;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ?
	getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

class UpdateSubscribersCount extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			"Regenerate nl_subscribers_count in nl_newsletters from nl_subscriptions table" );
		$this->requireExtension( 'Newsletter' );
	}

	public function execute() {
		$dbw = $this->getDB( DB_MASTER );
		$offset = 0;
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		while ( true ) {
			$res = $dbw->select( [ 'nl_newsletters', 'nl_subscriptions' ],
				[ 'nl_id', 'subscriber_count' => 'COUNT(nls_subscriber_id)' ],
				'nl_id > ' . $dbw->addQuotes( $offset ),
				__METHOD__,
				[ 'GROUP BY' => 'nl_id', 'LIMIT' => 50, 'ORDER BY' => 'nl_id' ],
				[ 'nl_subscriptions' => [ 'LEFT JOIN', 'nls_newsletter_id=nl_id' ] ]
			);

			if ( $res->numRows() === 0 ) {
				break;
			}

			foreach ( $res as $row ) {
				$dbw->update(
					'nl_newsletters',
					// This column is negative for index reasons.
					[ 'nl_subscriber_count' => -$row->subscriber_count ],
					[ 'nl_id' => $row->nl_id ],
					__METHOD__
				);
			}

			$this->output( "Updated " . $res->numRows() . " rows \n" );

			$lbFactory->waitForReplication();

			// We need to get the last element and add to offset.
			// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
			$offset = $row->nl_id;
		}

		$this->output( "Done!\n" );
	}

}

$maintClass = UpdateSubscribersCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
