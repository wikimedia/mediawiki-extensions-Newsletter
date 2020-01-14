<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\DBQueryError;
use Wikimedia\Rdbms\DBUnexpectedError;
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
	 *
	 * @return bool success of the action
	 */
	public function addSubscription( Newsletter $newsletter, $userIds ) {
		$rowData = [];
		foreach ( $userIds as $userId ) {
			$rowData[] = [
				'nls_newsletter_id' => $newsletter->getId(),
				'nls_subscriber_id' => $userId
			];
		}
		$dbw = $this->lb->getConnection( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );
		$dbw->insert( 'nl_subscriptions', $rowData, __METHOD__, [ 'IGNORE' ] );
		$success = (bool)$dbw->affectedRows();

		if ( $success ) {
			$dbw->update(
				'nl_newsletters',
				// For index reasons, count is negative
				[ 'nl_subscriber_count=nl_subscriber_count-' . count( $userIds ) ],
				[ 'nl_id' => $newsletter->getId() ],
				__METHOD__
			);
		}

		$dbw->endAtomic( __METHOD__ );
		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 *
	 * @return bool success of the action
	 */
	public function removeSubscription( Newsletter $newsletter, $userIds ) {
		$rowData = [
			'nls_newsletter_id' => $newsletter->getId(),
			'nls_subscriber_id' => $userIds
		];

		$dbw = $this->lb->getConnection( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );
		$dbw->delete( 'nl_subscriptions', $rowData, __METHOD__ );
		$success = (bool)$dbw->affectedRows();
		if ( $success ) {
			$dbw->update(
				'nl_newsletters',
				// For index reasons, count is negative
				[ 'nl_subscriber_count=nl_subscriber_count+' . count( $userIds ) ],
				[ 'nl_id' => $newsletter->getId() ],
				__METHOD__
			);
		}

		$dbw->endAtomic( __METHOD__ );
		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 *
	 * @return bool success of the action
	 */
	public function addPublisher( Newsletter $newsletter, $userIds ) {
		$newsletterId = $newsletter->getId();
		$rowData = [];
		foreach ( $userIds as $userId ) {
			$rowData[] = [
				'nlp_newsletter_id' => $newsletterId,
				'nlp_publisher_id' => $userId
			];
		}

		$dbw = $this->lb->getConnection( DB_MASTER );
		$dbw->insert( 'nl_publishers', $rowData, __METHOD__, [ 'IGNORE' ] );
		$success = (bool)$dbw->affectedRows();

		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 *
	 * @return bool success of the action
	 */
	public function removePublisher( Newsletter $newsletter, $userIds ) {
		$rowData = [
			'nlp_newsletter_id' => $newsletter->getId(),
			'nlp_publisher_id' => $userIds
		];

		$dbw = $this->lb->getConnection( DB_MASTER );
		$dbw->delete( 'nl_publishers', $rowData, __METHOD__ );
		$success = (bool)$dbw->affectedRows();

		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * @param Newsletter $newsletter
	 *
	 * @return bool|int the id of the newsletter added, false on failure
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

		$dbw = $this->lb->getConnection( DB_MASTER );
		try {
			$success = $dbw->insert( 'nl_newsletters', $rowData, __METHOD__ );
		} catch ( DBQueryError $ex ) {
			$success = false;
		}

		if ( $success ) {
			$success = $dbw->insertId();
		}

		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * @param int $id
	 * @param string $description
	 *
	 * @return bool success of the action
	 */
	public function updateDescription( $id, $description ) {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();

		Assert::parameterType( 'integer', $id, '$id' );
		Assert::parameterType( 'string', $description, '$description' );

		$rowData = [
			// nl_newsletters.nl_desc is a blob but put some limit
			// here which is less than the max size for blobs
			'nl_desc' => $contLang->truncateForDatabase( $description, 600000 ),
		];

		$conds = [
			'nl_id' => $id,
		];

		$dbw = $this->lb->getConnection( DB_MASTER );
		try {
			$success = $dbw->update( 'nl_newsletters', $rowData, $conds, __METHOD__ );

		} catch ( DBQueryError $ex ) {
			$success = false;
		}
		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * @param int $id
	 * @param string $name
	 *
	 * @return bool success of the action
	 */
	public function updateName( $id, $name ) {
		Assert::parameterType( 'integer', $id, '$id' );
		Assert::parameterType( 'string', $name, '$name' );

		$rowData = [
			'nl_name' => $name,
		];

		$conds = [
			'nl_id' => $id,
		];

		$dbw = $this->lb->getConnection( DB_MASTER );
		try {
			$success = $dbw->update( 'nl_newsletters', $rowData, $conds, __METHOD__ );

		} catch ( DBQueryError $ex ) {
			$success = false;
		}
		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * @param int $id
	 * @param int $pageId
	 *
	 * @return bool success of the action
	 */
	public function updateMainPage( $id, $pageId ) {
		Assert::parameterType( 'integer', $id, '$id' );
		Assert::parameterType( 'integer', $pageId, '$pageId' );

		$rowData = [
			'nl_main_page_id' => $pageId,
		];

		$conds = [
			'nl_id' => $id,
		];

		$dbw = $this->lb->getConnection( DB_MASTER );
		try {
			$success = $dbw->update( 'nl_newsletters', $rowData, $conds, __METHOD__ );

		} catch ( DBQueryError $ex ) {
			$success = false;
		}
		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * @param Newsletter $newsletter
	 *
	 * @return bool success of the action
	 */
	public function deleteNewsletter( Newsletter $newsletter ) {
		$dbw = $this->lb->getConnection( DB_MASTER );

		$dbw->update(
			'nl_newsletters',
			[ 'nl_active' => 0 ],
			[ 'nl_id' => $newsletter->getId(), 'nl_active' => 1 ],
			__METHOD__
		);
		$success = (bool)$dbw->affectedRows();

		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * Set an inactive newsletter to active again
	 *
	 * @param string $newsletterName
	 *
	 * @return bool success of the action
	 */
	public function restoreNewsletter( $newsletterName ) {
		$dbw = $this->lb->getConnection( DB_MASTER );

		$dbw->update(
			'nl_newsletters',
			[ 'nl_active' => 1 ],
			[ 'nl_name' => $newsletterName, 'nl_active' => 0 ],
			__METHOD__
		);
		$success = (bool)$dbw->affectedRows();

		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * @param int $id
	 *
	 * @return Newsletter|null null if no newsletter exists with the provided id
	 */
	public function getNewsletter( $id ) {
		Assert::parameterType( 'integer', $id, '$id' );

		$dbr = $this->lb->getConnection( DB_REPLICA );
		$res = $dbr->select(
			'nl_newsletters',
			[ 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ],
			[ 'nl_id' => $id, 'nl_active' => 1 ],
			__METHOD__
		);
		$this->lb->reuseConnection( $dbr );

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
	public function getNewsletterFromName( $name, $active = true ) {
		Assert::parameterType( 'string', $name, '$name' );

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
	 *
	 * @return int[]
	 */
	public function getPublishersFromID( $id ) {
		Assert::parameterType( 'integer', $id, '$id' );

		$dbr = $this->lb->getConnection( DB_REPLICA );

		$result = $dbr->selectFieldValues(
			'nl_publishers',
			'nlp_publisher_id',
			[ 'nlp_newsletter_id' => $id ],
			__METHOD__
		);
		$this->lb->reuseConnection( $dbr );

		return array_map( 'intval', $result );
	}

	/**
	 * @param int $id
	 * @return int
	 */
	public function getNewsletterSubscribersCount( $id ) {
		Assert::parameterType( 'integer', $id, '$id' );

		$dbr = $this->lb->getConnection( DB_REPLICA );

		$result = $dbr->selectField(
			'nl_newsletters',
			'nl_subscriber_count',
			[ 'nl_id' => $id ],
			__METHOD__
		);

		$this->lb->reuseConnection( $dbr );

		// We store nl_subscriber_count as negative numbers so that sorting should work on one
		// direction
		return -(int)$result;
	}

	/**
	 * @param int $id
	 *
	 * @return int[]
	 */
	public function getSubscribersFromID( $id ) {
		Assert::parameterType( 'integer', $id, '$id' );

		$dbr = $this->lb->getConnection( DB_REPLICA );

		$result = $dbr->selectFieldValues(
			'nl_subscriptions',
			'nls_subscriber_id',
			[ 'nls_newsletter_id' => $id ],
			__METHOD__
		);
		$this->lb->reuseConnection( $dbr );

		return array_map( 'intval', $result );
	}

	/**
	 * Fetch all newsletter Main Pages
	 *
	 * @param int $mainPageId
	 * @return bool|IResultWrapper
	 * @throws DBUnexpectedError
	 * @throws MWException
	 */
	public function newsletterExistsForMainPage( $mainPageId ) {
		Assert::parameterType( 'integer', $mainPageId, '$mainPageId' );
		$dbr = $this->lb->getConnection( DB_REPLICA );

		$res = $dbr->select(
			'nl_newsletters',
			[ 'nl_main_page_id', 'nl_active' ],
			[ 'nl_main_page_id' => $mainPageId ]
		);

		$this->lb->reuseConnection( $dbr );
		return $res;
	}

	/**
	 * @param stdClass $row
	 *
	 * @return Newsletter
	 */
	private function getNewsletterFromRow( $row ) {
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
	 *
	 * @return bool|int the id of the issue added, false on failure
	 */
	public function addNewsletterIssue( Newsletter $newsletter, Title $title, User $publisher ) {
		// Note: the writeDb is used as this is used in the next insert
		$dbw = $this->lb->getConnection( DB_MASTER );

		$dbw->startAtomic( __METHOD__ );
		$lastIssueId = (int)$dbw->selectField(
			'nl_issues',
			'MAX(nli_issue_id)',
			[ 'nli_newsletter_id' => $newsletter->getId() ],
			__METHOD__,
			[ 'FOR UPDATE' ]
		);
		$nextIssueId = $lastIssueId + 1;

		try {
			$success = $dbw->insert(
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
			$success = false;
		}

		if ( $success ) {
			$success = $nextIssueId;
		}

		$this->lb->reuseConnection( $dbw );

		return $success;
	}

}
