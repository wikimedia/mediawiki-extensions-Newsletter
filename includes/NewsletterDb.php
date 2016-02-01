<?php

/**
 * @license GNU GPL v2+
 * @author Addshore
 */
class NewsletterDb {

	private $readDb;
	private $writeDb;

	public function __construct( IDatabase $readDb, IDatabase $writeDb ) {
		$this->readDb = $readDb;
		$this->writeDb = $writeDb;
	}

	public static function newFromGlobalState() {
		return new self( wfGetDB( DB_SLAVE ), wfGetDB( DB_MASTER ) );
	}

	/**
	 * @param int $userId
	 * @param int $newsletterId
	 *
	 * @return bool success of the action
	 */
	public function addSubscription( $userId, $newsletterId ) {
		$rowData = array(
			'nls_newsletter_id' => $newsletterId,
			'nls_subscriber_id' => $userId,
		);

		$this->writeDb->insert( 'nl_subscriptions', $rowData, __METHOD__, array( 'IGNORE' ) );

		return (bool)$this->writeDb->affectedRows();
	}

	/**
	 * @param int $userId
	 * @param int $newsletterId
	 *
	 * @return bool success of the action
	 */
	public function removeSubscription( $userId, $newsletterId ) {
		$rowData = array(
			'nls_newsletter_id' => $newsletterId,
			'nls_subscriber_id' => $userId,
		);
		$this->writeDb->delete( 'nl_subscriptions', $rowData, __METHOD__ );

		return (bool)$this->writeDb->affectedRows();
	}

	/**
	 * @param int $userId
	 * @param int $newsletterId
	 *
	 * @return bool success of the action
	 */
	public function addPublisher( $userId, $newsletterId ) {
		$rowData = array(
			'nlp_newsletter_id' => (int)$newsletterId,
			'nlp_publisher_id' => (int)$userId,
		);
		$this->writeDb->insert( 'nl_publishers', $rowData, __METHOD__, array( 'IGNORE' ) );

		return $this->writeDb->affectedRows() === 1;

	}

	/**
	 * @param int $userId
	 * @param int $newsletterId
	 *
	 * @return bool success of the action
	 */
	public function removePublisher( $userId, $newsletterId ) {
		$rowData = array(
			'nlp_newsletter_id' => (int)$newsletterId,
			'nlp_publisher_id' => (int)$userId,
		);
		$this->writeDb->delete( 'nl_publishers', $rowData, __METHOD__ );

		return $this->writeDb->affectedRows() === 1;
	}

	/**
	 * @param string $name
	 * @param string $description
	 * @param int $pageId
	 *
	 * @return bool success of the action
	 */
	public function addNewsletter( $name, $description, $pageId ) {
		$rowData = array(
			'nl_name' => $name,
			'nl_desc' => $description,
			'nl_main_page_id' => $pageId,
		);
		try {
			return $this->writeDb->insert( 'nl_newsletters', $rowData, __METHOD__ );
		} catch ( DBQueryError $ex ) {
			return false;
		}
	}

	/**
	 * @param int $id
	 *
	 * @todo make this more reliable and scalable
	 */
	public function deleteNewsletter( $id ) {
		$dbw = $this->writeDb;
		$dbw->startAtomic( __METHOD__ );
		$dbw->delete( 'nl_newsletters', array( 'nl_id' => $id ), __METHOD__ );
		$dbw->delete( 'nl_issues', array( 'nli_newsletter_id' => $id ), __METHOD__ );
		$dbw->delete( 'nl_publishers', array( 'nlp_newsletter_id' => $id ), __METHOD__ );
		$dbw->delete( 'nl_subscriptions', array( 'nls_newsletter_id' => $id ), __METHOD__ );
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * @param int $id
	 *
	 * @return Newsletter|null null if no newsletter exists with the provided id
	 */
	public function getNewsletter( $id ) {
		$res = $this->readDb->select(
			'nl_newsletters',
			array( 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ),
			array( 'nl_id' => $id ),
			__METHOD__
		);

		if ( $res->numRows() === 0 ) {
			return null;
		}

		return $this->getNewsletterFromRow( $res->current() );
	}


	/**
	 * @param int $id
	 *
	 * @return string[]
	 */
	public function getPublishersFromID( $id ) {
		return $this->readDb->selectFieldValues(
			'nl_publishers',
			'nlp_publisher_id',
			array( 'nlp_newsletter_id' => $id ),
			__METHOD__
		);
	}

	/**
	 * @param int $id
	 *
	 * @return string[]
	 */
	public function getSubscribersFromID( $id ) {
		return $this->readDb->selectFieldValues(
			'nl_subscriptions',
			'nls_subscriber_id',
			array( 'nls_newsletter_id' => $id ),
			__METHOD__
		);
	}

	/**
	 * @param int $id
	 *
	 * @return Newsletter
	 */
	public function getNewsletterForPageId( $id ) {
		$res = $this->readDb->select(
			'nl_newsletters',
			array( 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ),
			array( 'nl_main_page_id' => $id ),
			__METHOD__
		);

		return $this->getNewsletterFromRow( $res->current() );
	}

	/**
	 * @param User $user
	 *
	 * @return Newsletter[]
	 */
	public function getNewslettersUserIsPublisherOf( User $user ) {
		$res = $this->readDb->select(
			array( 'nl_publishers', 'nl_newsletters' ),
			array( 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ),
			array( 'nlp_publisher_id' => $user->getId() ),
			__METHOD__,
			array(),
			array( 'nl_newsletters' => array( 'LEFT JOIN', 'nl_id=nlp_newsletter_id' ) )
		);

		return $this->getNewslettersFromResult( $res );
	}

	/**
	 * @return Newsletter[]
	 */
	public function getAllNewsletters() {
		$res = $this->readDb->select(
			array( 'nl_newsletters' ),
			array( 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ),
			array(),
			__METHOD__
		);

		return $this->getNewslettersFromResult( $res );
	}

	/**
	 * @param ResultWrapper $result
	 *
	 * @return Newsletter[]
	 */
	private function getNewslettersFromResult( ResultWrapper $result ) {
		$newsletters = array();
		foreach ( $result as $row ) {
			$newsletters[] = $this->getNewsletterFromRow( $row );
		}

		return $newsletters;
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
	 * @param int $newsletterId
	 * @param int $pageId
	 * @param int $publisherId
	 *
	 * @todo this should probably be done in a transaction (even though conflicts are unlikely)
	 *
	 * @return bool
	 */
	public function addNewsletterIssue( $newsletterId, $pageId, $publisherId ) {
		// Note: the writeDb is used as this is used in the next insert
		$lastIssueId = $this->writeDb->selectRowCount(
			'nl_issues',
			array( 'nli_issue_id' ),
			array( 'nli_newsletter_id' => $newsletterId ),
			__METHOD__
		);
		// @todo should probably AUTO INCREMENT here
		$rowData = array(
			'nli_issue_id' => $lastIssueId + 1,
			'nli_page_id' => $pageId,
			'nli_newsletter_id' => $newsletterId,
			'nli_publisher_id' => $publisherId,
		);
		try {
			return $this->writeDb->insert( 'nl_issues', $rowData, __METHOD__ );
		} catch ( DBQueryError $ex ) {
			return false;
		}
	}

}
