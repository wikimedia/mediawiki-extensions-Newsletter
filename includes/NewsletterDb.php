<?php

/**
 * @license GNU GPL v2+
 * @author Adam Shorland
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
			'newsletter_id' => $newsletterId,
			'subscriber_id' =>$userId,
		);
		try{
			return $this->writeDb->insert( 'nl_subscriptions', $rowData, __METHOD__ );
		} catch ( DBQueryError $ex ) {
			return false;
		}
	}

	/**
	 * @param int $userId
	 * @param int $newsletterId
	 *
	 * @return bool success of the action
	 */
	public function removeSubscription( $userId, $newsletterId ) {
		$rowData = array(
			'newsletter_id' => $newsletterId,
			'subscriber_id' => $userId,
		);
		return $this->writeDb->delete( 'nl_subscriptions', $rowData, __METHOD__ );
	}

	/**
	 * @param int $newsletterId
	 *
	 * @return int[]
	 */
	public function getUserIdsSubscribedToNewsletter( $newsletterId ) {
		$res = $this->readDb->select(
			'nl_subscriptions',
			array( 'subscriber_id' ),
			array( 'newsletter_id' => $newsletterId ),
			__METHOD__,
			array()
		);

		$subscriberIds = array();
		foreach ( $res as $row ) {
			$subscriberIds[] = $row->subscriber_id;
		}
		return $subscriberIds;
	}

	/**
	 * @param int $userId
	 * @param int $newsletterId
	 *
	 * @return bool success of the action
	 */
	public function addPublisher( $userId, $newsletterId ) {
		$rowData = array(
			'newsletter_id' => $newsletterId,
			'publisher_id' =>$userId,
		);
		try{
			return $this->writeDb->insert( 'nl_publishers', $rowData, __METHOD__ );
		} catch ( DBQueryError $ex ) {
			return false;
		}
	}

	/**
	 * @param int $userId
	 * @param int $newsletterId
	 *
	 * @return bool success of the action
	 */
	public function removePublisher( $userId, $newsletterId ) {
		$rowData = array(
			'newsletter_id' => $newsletterId,
			'publisher_id' => $userId,
		);
		return $this->writeDb->delete( 'nl_publishers', $rowData, __METHOD__ );
	}

	/**
	 * @param string $name
	 * @param string $description
	 * @param int $pageId
	 * @param string $frequency
	 * @param int $ownerId
	 *
	 * @return bool success of the action
	 */
	public function addNewsletter( $name, $description, $pageId, $frequency, $ownerId ) {
		$rowData = array(
			'nl_name' => $name,
			'nl_desc' => $description,
			'nl_main_page_id' => $pageId,
			'nl_frequency' => $frequency,
			'nl_owner_id' => $ownerId,
		);
		try{
			return $this->writeDb->insert( 'nl_newsletters', $rowData, __METHOD__ );
		} catch ( DBQueryError $ex ) {
			return false;
		}
	}

	/**
	 * @param int $id
	 *
	 * @return Newsletter
	 */
	public function getNewsletter( $id ) {
		$res = $this->readDb->select(
			'nl_newsletters',
			array( 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id', 'nl_frequency', 'nl_owner_id' ),
			array( 'nl_id' => $id ),
			__METHOD__
		);

		return $this->getNewsletterFromRow( $res->current() );
	}

	/**
	 * @param int $id
	 *
	 * @return Newsletter
	 */
	public function getNewsletterForPageId( $id ) {
		$res = $this->readDb->select(
			'nl_newsletters',
			array( 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id', 'nl_frequency', 'nl_owner_id' ),
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
	public function getNewslettersUserIsOwnerOf( User $user ) {
		$res = $this->readDb->select(
			array( 'nl_newsletters' ),
			array( 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id', 'nl_frequency', 'nl_owner_id' ),
			array( 'nl_owner_id' => $user->getId() ),
			__METHOD__
		);

		return $this->getNewslettersFromResult( $res );
	}

	/**
	 * @param User $user
	 *
	 * @return Newsletter[]
	 */
	public function getNewslettersUserIsPublisherOf( User $user ) {
		$res = $this->readDb->select(
			array( 'nl_publishers', 'nl_newsletters' ),
			array( 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id', 'nl_frequency', 'nl_owner_id' ),
			array( 'publisher_id' => $user->getId() ),
			__METHOD__,
			array(),
			array( 'nl_newsletters' => array( 'LEFT JOIN', 'nl_id=newsletter_id' ) )
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
			$row->nl_main_page_id,
			$row->nl_frequency,
			$row->nl_owner_id
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
		//Note: the writeDb is used as this is used in the next insert
		$lastIssueId = $this->writeDb->selectRowCount(
			'nl_issues',
			array( 'issue_id' ),
			array( 'issue_newsletter_id' => $newsletterId ),
			__METHOD__
		);

		$rowData = array(
			'issue_id' => $lastIssueId + 1,
			'issue_page_id' => $pageId,
			'issue_newsletter_id' => $newsletterId,
			'issue_publisher_id' => $publisherId,
		);
		try{
			return $this->writeDb->insert( 'nl_issues', $rowData, __METHOD__ );
		} catch ( DBQueryError $ex ) {
			return false;
		}
	}

}
