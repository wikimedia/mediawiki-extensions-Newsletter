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
	 * @param int $userId
	 *
	 * @return int[]
	 */
	public function getNewsletterIdsForPublisher( $userId ) {
		$res = $this->readDb->select(
			'nl_publishers',
			array( 'newsletter_id' ),
			array( 'publisher_id' => $userId ),
			__METHOD__
		);

		$newsletterIds = array();
		foreach ( $res as $row ) {
			$newsletterIds[] = $row->newsletter_id;
		}

		return $newsletterIds;
	}

}
