<?php

namespace MediaWiki\Extension\Newsletter;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use stdClass;
use Wikimedia\Rdbms\DBQueryError;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class NewsletterDb {

	/**
	 * @var ILoadBalancer
	 */
	private $lb;

	public function __construct( ILoadBalancer $lb ) {
		$this->lb = $lb;
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 */
	public function addSubscription( Newsletter $newsletter, array $userIds ): void {
		if ( !$userIds ) {
			return;
		}

		$rowData = [];
		foreach ( $userIds as $userId ) {
			$rowData[] = [
				'nls_newsletter_id' => $newsletter->getId(),
				'nls_subscriber_id' => $userId
			];
		}
		$dbw = $this->lb->getConnection( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		// Tolerate (silently ignore) if it was already there
		$dbw->newInsertQueryBuilder()
			->insertInto( 'nl_subscriptions' )
			->ignore()
			->rows( $rowData )
			->caller( __METHOD__ )
			->execute();

		// But only update the count if there was a change
		if ( $dbw->affectedRows() ) {
			$dbw->newUpdateQueryBuilder()
				->update( 'nl_newsletters' )
				// For index reasons, count is negative
				->set( [ 'nl_subscriber_count=nl_subscriber_count-' . count( $userIds ) ] )
				->where( [ 'nl_id' => $newsletter->getId() ] )
				->caller( __METHOD__ )
				->execute();
		}

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 */
	public function removeSubscription( Newsletter $newsletter, array $userIds ): void {
		$rowData = [
			'nls_newsletter_id' => $newsletter->getId(),
			'nls_subscriber_id' => $userIds
		];

		$dbw = $this->lb->getConnection( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'nl_subscriptions' )
			->where( $rowData )
			->caller( __METHOD__ )
			->execute();

		// Delete query succeeds even if the row already gone
		// But only update the count if there was a change
		if ( $dbw->affectedRows() ) {
			$dbw->newUpdateQueryBuilder()
				->update( 'nl_newsletters' )
				// For index reasons, count is negative
				->set( [ 'nl_subscriber_count=nl_subscriber_count+' . count( $userIds ) ] )
				->where( [ 'nl_id' => $newsletter->getId() ] )
				->caller( __METHOD__ )
				->execute();
		}

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 * @return bool Success of the action
	 */
	public function addPublisher( Newsletter $newsletter, array $userIds ): bool {
		if ( !$userIds ) {
			return false;
		}

		$newsletterId = $newsletter->getId();
		$rowData = [];
		foreach ( $userIds as $userId ) {
			$rowData[] = [
				'nlp_newsletter_id' => $newsletterId,
				'nlp_publisher_id' => $userId
			];
		}

		$dbw = $this->lb->getConnection( DB_PRIMARY );

		// Let the user action appear success even if the row is already there.
		$dbw->newInsertQueryBuilder()
			->insertInto( 'nl_publishers' )
			->ignore()
			->rows( $rowData )
			->caller( __METHOD__ )
			->execute();
		// Provide a bool that reflects actual creation of a row,
		// used for decide whether to create a matching MW log entry.
		return (bool)$dbw->affectedRows();
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 * @return bool
	 */
	public function removePublisher( Newsletter $newsletter, array $userIds ): bool {
		$rowData = [
			'nlp_newsletter_id' => $newsletter->getId(),
			'nlp_publisher_id' => $userIds
		];

		$dbw = $this->lb->getConnection( DB_PRIMARY );

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'nl_publishers' )
			->where( $rowData )
			->caller( __METHOD__ )
			->execute();

		// Delete query succeeds even if the row was already gone.
		// Provide a bool that reflects actual creation of a row,
		// used for decide whether to create a matching MW log entry.
		return (bool)$dbw->affectedRows();
	}

	/**
	 * @param Newsletter $newsletter
	 * @return int|bool The ID of the newsletter added, or false on failure
	 */
	public function addNewsletter( Newsletter $newsletter ) {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		$rowData = [
			'nl_name' => $newsletter->getName(),
			// nl_newsletters.nl_desc is a blob but put some limit
			// here which is less than the max size for blobs
			'nl_desc' => $contLang->truncateForDatabase( $newsletter->getDescription(), 600000 ),
			'nl_main_page_id' => $newsletter->getPageId(),
		];

		$dbw = $this->lb->getConnection( DB_PRIMARY );
		try {
			$dbw->newInsertQueryBuilder()
				->insertInto( 'nl_newsletters' )
				->row( $rowData )
				->caller( __METHOD__ )
				->execute();
			return $dbw->insertId();
		} catch ( DBQueryError ) {
			return false;
		}
	}

	/**
	 * @param int $id
	 * @param string $description
	 * @return bool Success of the action
	 */
	public function updateDescription( int $id, string $description ): bool {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();

		$rowData = [
			// nl_newsletters.nl_desc is a blob but put some limit
			// here, which is less than the max size for blobs
			'nl_desc' => $contLang->truncateForDatabase( $description, 600000 ),
		];
		$conds = [
			'nl_id' => $id,
		];

		$dbw = $this->lb->getConnection( DB_PRIMARY );
		try {
			$dbw->newUpdateQueryBuilder()
				->update( 'nl_newsletters' )
				->set( $rowData )
				->where( $conds )
				->caller( __METHOD__ )
				->execute();
		} catch ( DBQueryError ) {
			return false;
		}

		return true;
	}

	/**
	 * @param int $id
	 * @param string $name
	 * @return bool Success of the action
	 */
	public function updateName( int $id, string $name ): bool {
		$rowData = [
			'nl_name' => $name,
		];
		$conds = [
			'nl_id' => $id,
		];

		$dbw = $this->lb->getConnection( DB_PRIMARY );
		try {
			$dbw->newUpdateQueryBuilder()
				->update( 'nl_newsletters' )
				->set( $rowData )
				->where( $conds )
				->caller( __METHOD__ )
				->execute();
		} catch ( DBQueryError ) {
			return false;
		}

		return true;
	}

	/**
	 * @param int $id
	 * @param int $pageId
	 * @return bool Success of the action
	 */
	public function updateMainPage( int $id, int $pageId ): bool {
		$rowData = [
			'nl_main_page_id' => $pageId,
		];
		$conds = [
			'nl_id' => $id,
		];

		$dbw = $this->lb->getConnection( DB_PRIMARY );
		try {
			$dbw->newUpdateQueryBuilder()
				->update( 'nl_newsletters' )
				->set( $rowData )
				->where( $conds )
				->caller( __METHOD__ )
				->execute();
		} catch ( DBQueryError ) {
			return false;
		}

		return true;
	}

	/**
	 * @param Newsletter $newsletter
	 */
	public function deleteNewsletter( Newsletter $newsletter ): void {
		$dbw = $this->lb->getConnection( DB_PRIMARY );
		$dbw->newUpdateQueryBuilder()
			->update( 'nl_newsletters' )
			->set( [ 'nl_active' => 0 ] )
			->where( [ 'nl_id' => $newsletter->getId(), 'nl_active' => 1 ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Set an inactive newsletter to active again
	 *
	 * @param Newsletter $newsletter
	 */
	public function restoreNewsletter( Newsletter $newsletter ): void {
		$dbw = $this->lb->getConnection( DB_PRIMARY );
		$dbw->newUpdateQueryBuilder()
			->update( 'nl_newsletters' )
			->set( [ 'nl_active' => 1 ] )
			->where( [ 'nl_id' => $newsletter->getId() ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param int $id
	 * @return Newsletter|null null if no newsletter exists with the provided id
	 */
	public function getNewsletter( int $id ) {
		$dbr = $this->lb->getConnection( DB_REPLICA );
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ] )
			->from( 'nl_newsletters' )
			->where( [ 'nl_id' => $id, 'nl_active' => 1 ] )
			->caller( __METHOD__ )
			->fetchRow();

		return $res ? $this->getNewsletterFromRow( $res ) : null;
	}

	/**
	 * Fetch the newsletter matching the given name from the DB
	 *
	 * @param string $name
	 * @param bool $active
	 * @return Newsletter|null
	 */
	public function getNewsletterFromName( string $name, bool $active = true ) {
		$dbr = $this->lb->getConnection( DB_REPLICA );
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ] )
			->from( 'nl_newsletters' )
			->where( [ 'nl_name' => $name, 'nl_active' => $active ] )
			->caller( __METHOD__ )
			->fetchRow();

		return $res ? $this->getNewsletterFromRow( $res ) : null;
	}

	/**
	 * @param int $id
	 * @return int[]
	 */
	public function getPublishersFromID( int $id ): array {
		$dbr = $this->lb->getConnection( DB_REPLICA );
		$result = $dbr->newSelectQueryBuilder()
			->select( 'nlp_publisher_id' )
			->from( 'nl_publishers' )
			->where( [ 'nlp_newsletter_id' => $id ] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return array_map( 'intval', $result );
	}

	/**
	 * @param int $id
	 * @return int
	 */
	public function getNewsletterSubscribersCount( int $id ): int {
		$dbr = $this->lb->getConnection( DB_REPLICA );
		$result = $dbr->newSelectQueryBuilder()
			->select( 'nl_subscriber_count' )
			->from( 'nl_newsletters' )
			->where( [ 'nl_id' => $id ] )
			->caller( __METHOD__ )
			->fetchField();

		// We store nl_subscriber_count as negative numbers so that sorting should work on one
		// direction
		return -(int)$result;
	}

	/**
	 * @param int $id
	 * @return int[]
	 */
	public function getSubscribersFromID( int $id ): array {
		$dbr = $this->lb->getConnection( DB_REPLICA );
		$result = $dbr->newSelectQueryBuilder()
			->select( 'nls_subscriber_id' )
			->from( 'nl_subscriptions' )
			->where( [ 'nls_newsletter_id' => $id ] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return array_map( 'intval', $result );
	}

	/**
	 * Fetch all newsletter Main Pages
	 *
	 * @param int $mainPageId
	 * @return IResultWrapper
	 */
	public function newsletterExistsForMainPage( int $mainPageId ) {
		$dbr = $this->lb->getConnection( DB_REPLICA );
		return $dbr->newSelectQueryBuilder()
			->select( [ 'nl_main_page_id', 'nl_active' ] )
			->from( 'nl_newsletters' )
			->where( [ 'nl_main_page_id' => $mainPageId ] )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * @param stdClass $row
	 * @return Newsletter
	 */
	private function getNewsletterFromRow( $row ): Newsletter {
		return new Newsletter(
			$row->nl_id,
			$row->nl_name,
			$row->nl_desc,
			$row->nl_main_page_id
		);
	}

	/**
	 * @param Newsletter $newsletter
	 * @param Title $title
	 * @param User $publisher
	 * @return bool|int the id of the issue added, false on failure
	 */
	public function addNewsletterIssue( Newsletter $newsletter, Title $title, User $publisher ) {
		// Note: the writeDb is used as this is used in the next insert
		$dbw = $this->lb->getConnection( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		$dbw->newSelectQueryBuilder()
			->table( 'nl_newsletters' )
			->conds( [ 'nl_id' => $newsletter->getId() ] )
			->forUpdate()
			->caller( __METHOD__ )
			->acquireRowLocks();
		$lastIssueId = (int)$dbw->newSelectQueryBuilder()
			->select( 'nli_issue_id' )
			->from( 'nl_issues' )
			->where( [ 'nli_newsletter_id' => $newsletter->getId() ] )
			->orderBy( 'nli_issue_id', SelectQueryBuilder::SORT_DESC )
			->forUpdate()
			->caller( __METHOD__ )
			->fetchField();
		$nextIssueId = $lastIssueId + 1;

		try {
			$dbw->newInsertQueryBuilder()
				->insertInto( 'nl_issues' )
				->row( [
					'nli_issue_id' => $nextIssueId,
					'nli_page_id' => $title->getArticleID(),
					'nli_newsletter_id' => $newsletter->getId(),
					'nli_publisher_id' => $publisher->getId(),
				] )
				->caller( __METHOD__ )
				->execute();
			$dbw->endAtomic( __METHOD__ );
		} catch ( DBQueryError ) {
			$dbw->rollback( __METHOD__ );
			return false;
		}

		return $nextIssueId;
	}

}
