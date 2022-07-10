<?php

namespace MediaWiki\Extension\Newsletter;

use MediaWiki\MediaWikiServices;
use stdClass;
use Title;
use User;
use Wikimedia\Rdbms\DBQueryError;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;

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
		$rowData = [];
		foreach ( $userIds as $userId ) {
			$rowData[] = [
				'nls_newsletter_id' => $newsletter->getId(),
				'nls_subscriber_id' => $userId
			];
		}
		$dbw = $this->lb->getConnectionRef( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		// Tolerate (silently ignore) if it was already there
		$dbw->insert( 'nl_subscriptions', $rowData, __METHOD__, [ 'IGNORE' ] );

		// But only update the count if there was a change
		if ( $dbw->affectedRows() ) {
			$dbw->update(
				'nl_newsletters',
				// For index reasons, count is negative
				[ 'nl_subscriber_count=nl_subscriber_count-' . count( $userIds ) ],
				[ 'nl_id' => $newsletter->getId() ],
				__METHOD__
			);
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

		$dbw = $this->lb->getConnectionRef( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		$dbw->delete( 'nl_subscriptions', $rowData, __METHOD__ );

		// Delete query succeeds even if the row already gone
		// But only update the count if there was a change
		if ( $dbw->affectedRows() ) {
			$dbw->update(
				'nl_newsletters',
				// For index reasons, count is negative
				[ 'nl_subscriber_count=nl_subscriber_count+' . count( $userIds ) ],
				[ 'nl_id' => $newsletter->getId() ],
				__METHOD__
			);
		}

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 * @return bool Success of the action
	 */
	public function addPublisher( Newsletter $newsletter, array $userIds ): bool {
		$newsletterId = $newsletter->getId();
		$rowData = [];
		foreach ( $userIds as $userId ) {
			$rowData[] = [
				'nlp_newsletter_id' => $newsletterId,
				'nlp_publisher_id' => $userId
			];
		}

		$dbw = $this->lb->getConnectionRef( DB_PRIMARY );

		// Let the user action appear success even if the row is already there.
		$dbw->insert( 'nl_publishers', $rowData, __METHOD__, [ 'IGNORE' ] );
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

		$dbw = $this->lb->getConnectionRef( DB_PRIMARY );

		$dbw->delete( 'nl_publishers', $rowData, __METHOD__ );

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

		$dbw = $this->lb->getConnectionRef( DB_PRIMARY );
		try {
			$dbw->insert( 'nl_newsletters', $rowData, __METHOD__ );
			return $dbw->insertId();
		} catch ( DBQueryError $ex ) {
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
			// here which is less than the max size for blobs
			'nl_desc' => $contLang->truncateForDatabase( $description, 600000 ),
		];
		$conds = [
			'nl_id' => $id,
		];

		$dbw = $this->lb->getConnectionRef( DB_PRIMARY );
		try {
			$dbw->update( 'nl_newsletters', $rowData, $conds, __METHOD__ );
		} catch ( DBQueryError $ex ) {
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

		$dbw = $this->lb->getConnectionRef( DB_PRIMARY );
		try {
			$dbw->update( 'nl_newsletters', $rowData, $conds, __METHOD__ );
		} catch ( DBQueryError $ex ) {
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

		$dbw = $this->lb->getConnectionRef( DB_PRIMARY );
		try {
			$dbw->update( 'nl_newsletters', $rowData, $conds, __METHOD__ );
		} catch ( DBQueryError $ex ) {
			return false;
		}

		return true;
	}

	/**
	 * @param Newsletter $newsletter
	 */
	public function deleteNewsletter( Newsletter $newsletter ): void {
		$dbw = $this->lb->getConnectionRef( DB_PRIMARY );
		$dbw->update(
			'nl_newsletters',
			[ 'nl_active' => 0 ],
			[ 'nl_id' => $newsletter->getId(), 'nl_active' => 1 ],
			__METHOD__
		);
	}

	/**
	 * Set an inactive newsletter to active again
	 *
	 * @param string $newsletterName
	 */
	public function restoreNewsletter( string $newsletterName ): void {
		$dbw = $this->lb->getConnectionRef( DB_PRIMARY );
		$dbw->update(
			'nl_newsletters',
			[ 'nl_active' => 1 ],
			[ 'nl_name' => $newsletterName, 'nl_active' => 0 ],
			__METHOD__
		);
	}

	/**
	 * @param int $id
	 * @return Newsletter|null null if no newsletter exists with the provided id
	 */
	public function getNewsletter( int $id ) {
		$dbr = $this->lb->getConnectionRef( DB_REPLICA );
		$res = $dbr->select(
			'nl_newsletters',
			[ 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ],
			[ 'nl_id' => $id, 'nl_active' => 1 ],
			__METHOD__
		);

		if ( $res->numRows() === 0 ) {
			return null;
		}

		return $this->getNewsletterFromRow( $res->current() );
	}

	/**
	 * Fetch the newsletter matching the given name from the DB
	 *
	 * @param string $name
	 * @param bool $active
	 * @return Newsletter|null
	 */
	public function getNewsletterFromName( string $name, bool $active = true ) {
		$dbr = $this->lb->getConnectionRef( DB_REPLICA );
		$res = $dbr->selectRow(
			'nl_newsletters',
			[ 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ],
			[ 'nl_name' => $name, 'nl_active' => $active ],
			__METHOD__
		);

		return $res ? $this->getNewsletterFromRow( $res ) : null;
	}

	/**
	 * @param int $id
	 * @return int[]
	 */
	public function getPublishersFromID( int $id ): array {
		$dbr = $this->lb->getConnectionRef( DB_REPLICA );
		$result = $dbr->selectFieldValues(
			'nl_publishers',
			'nlp_publisher_id',
			[ 'nlp_newsletter_id' => $id ],
			__METHOD__
		);

		return array_map( 'intval', $result );
	}

	/**
	 * @param int $id
	 * @return int
	 */
	public function getNewsletterSubscribersCount( int $id ): int {
		$dbr = $this->lb->getConnectionRef( DB_REPLICA );
		$result = $dbr->selectField(
			'nl_newsletters',
			'nl_subscriber_count',
			[ 'nl_id' => $id ],
			__METHOD__
		);

		// We store nl_subscriber_count as negative numbers so that sorting should work on one
		// direction
		return -(int)$result;
	}

	/**
	 * @param int $id
	 * @return int[]
	 */
	public function getSubscribersFromID( int $id ): array {
		$dbr = $this->lb->getConnectionRef( DB_REPLICA );
		$result = $dbr->selectFieldValues(
			'nl_subscriptions',
			'nls_subscriber_id',
			[ 'nls_newsletter_id' => $id ],
			__METHOD__
		);

		return array_map( 'intval', $result );
	}

	/**
	 * Fetch all newsletter Main Pages
	 *
	 * @param int $mainPageId
	 * @return IResultWrapper
	 */
	public function newsletterExistsForMainPage( int $mainPageId ) {
		$dbr = $this->lb->getConnectionRef( DB_REPLICA );
		return $dbr->select(
			'nl_newsletters',
			[ 'nl_main_page_id', 'nl_active' ],
			[ 'nl_main_page_id' => $mainPageId ],
			__METHOD__
		);
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
		$dbw = $this->lb->getConnectionRef( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		$dbw->lockForUpdate( 'nl_newsletters', [ 'nl_id' => $newsletter->getId() ], __METHOD__ );
		$lastIssueId = (int)$dbw->selectField(
			'nl_issues',
			'nli_issue_id',
			[ 'nli_newsletter_id' => $newsletter->getId() ],
			__METHOD__,
			[
				'ORDER BY' => 'nli_issue_id DESC',
				'FOR UPDATE'
			]
		);
		$nextIssueId = $lastIssueId + 1;

		try {
			$dbw->insert(
				'nl_issues',
				[
					'nli_issue_id' => $nextIssueId,
					'nli_page_id' => $title->getArticleID(),
					'nli_newsletter_id' => $newsletter->getId(),
					'nli_publisher_id' => $publisher->getId(),
				],
				__METHOD__
			);
			$dbw->endAtomic( __METHOD__ );
		} catch ( DBQueryError $ex ) {
			$dbw->rollback( __METHOD__ );
			return false;
		}

		return $nextIssueId;
	}

}
