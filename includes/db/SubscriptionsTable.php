<?php

/**
 * @license GNU GPL v2+
 * @author Adam Shorland
 */
class SubscriptionsTable {

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

}
