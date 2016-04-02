<?php

use Wikimedia\Assert\Assert;

/**
 * @license GNU GPL v2+
 * @author Addshore
 */
class NewsletterDb {

	/**
	 * @var LoadBalancer
	 */
	private $lb;

	public function __construct( LoadBalancer $lb ) {
		$this->lb = $lb;
	}

	public static function newFromGlobalState() {
		return new self( wfGetLB() );
	}

	/**
	 * @param int $userId
	 * @param int $newsletterId
	 *
	 * @return bool success of the action
	 */
	public function addSubscription( $userId, $newsletterId ) {
		Assert::parameterType( 'integer', $userId, '$userId' );
		Assert::parameterType( 'integer', $newsletterId, '$newsletterId' );

		$rowData = array(
			'nls_newsletter_id' => $newsletterId,
			'nls_subscriber_id' => $userId,
		);

		$dbw = $this->lb->getConnection( DB_MASTER );
		$dbw->insert( 'nl_subscriptions', $rowData, __METHOD__, array( 'IGNORE' ) );
		$success = (bool)$dbw->affectedRows();
		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * @param int $userId
	 * @param int $newsletterId
	 *
	 * @return bool success of the action
	 */
	public function removeSubscription( $userId, $newsletterId ) {
		Assert::parameterType( 'integer', $userId, '$userId' );
		Assert::parameterType( 'integer', $newsletterId, '$newsletterId' );

		$rowData = array(
			'nls_newsletter_id' => $newsletterId,
			'nls_subscriber_id' => $userId,
		);

		$dbw = $this->lb->getConnection( DB_MASTER );
		$dbw->delete( 'nl_subscriptions', $rowData, __METHOD__ );
		$success = (bool)$dbw->affectedRows();
		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * @param int $userId
	 * @param int $newsletterId
	 *
	 * @return bool success of the action
	 */
	public function addPublisher( $userId, $newsletterId ) {
		Assert::parameterType( 'integer', $userId, '$userId' );
		Assert::parameterType( 'integer', $newsletterId, '$newsletterId' );

		$rowData = array(
			'nlp_newsletter_id' => (int)$newsletterId,
			'nlp_publisher_id' => (int)$userId,
		);

		$dbw = $this->lb->getConnection( DB_MASTER );
		$dbw->insert( 'nl_publishers', $rowData, __METHOD__, array( 'IGNORE' ) );
		$success = (bool)$dbw->affectedRows();
		$this->lb->reuseConnection( $dbw );

		return $success;

	}

	/**
	 * @param int $userId
	 * @param int $newsletterId
	 *
	 * @return bool success of the action
	 */
	public function removePublisher( $userId, $newsletterId ) {
		Assert::parameterType( 'integer', $userId, '$userId' );
		Assert::parameterType( 'integer', $newsletterId, '$newsletterId' );

		$rowData = array(
			'nlp_newsletter_id' => (int)$newsletterId,
			'nlp_publisher_id' => (int)$userId,
		);

		$dbw = $this->lb->getConnection( DB_MASTER );
		$dbw->delete( 'nl_publishers', $rowData, __METHOD__ );
		$success = (bool)$dbw->affectedRows();
		$this->lb->reuseConnection( $dbw );

		return $success;
	}

	/**
	 * @param string $name
	 * @param string $description
	 * @param int $pageId
	 *
	 * @return bool success of the action
	 */
	public function addNewsletter( $name, $description, $pageId ) {
		Assert::parameterType( 'string', $name, '$name' );
		Assert::parameterType( 'string', $description, '$description' );
		Assert::parameterType( 'integer', $pageId, '$pageId' );

		$rowData = array(
			'nl_name' => $name,
			'nl_desc' => $description,
			'nl_main_page_id' => $pageId,
		);


		$dbw = $this->lb->getConnection( DB_MASTER );
		try {
			$success = $dbw->insert( 'nl_newsletters', $rowData, __METHOD__ );
		} catch ( DBQueryError $ex ) {
			$success = false;
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
		Assert::parameterType( 'integer', $id, '$id' );
		Assert::parameterType( 'string', $description, '$description' );

		$rowData = array(
			'nl_desc' => $description,
		);

		$conds = array(
			'nl_id' => $id,
		);

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

		$rowData = array(
			'nl_name' => $name,
		);

		$conds = array(
			'nl_id' => $id,
		);

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

		$rowData = array(
			'nl_main_page_id' => $pageId,
		);

		$conds = array(
			'nl_id' => $id,
		);

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
	 */
	public function deleteNewsletter( $id ) {
		Assert::parameterType( 'integer', $id, '$id' );

		$dbw = $this->lb->getConnection( DB_MASTER );

		$dbw->update(
			'nl_newsletters',
			array( 'nl_active' => 0 ),
			array( 'nl_id' => $id ),
			__METHOD__
		);
	}

	/**
	 * @param int $id
	 *
	 * @return Newsletter|null null if no newsletter exists with the provided id
	 */
	public function getNewsletter( $id ) {
		Assert::parameterType( 'integer', $id, '$id' );

		$dbr = $this->lb->getConnection( DB_SLAVE );
		$res = $dbr->select(
			'nl_newsletters',
			array( 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ),
			array( 'nl_id' => $id, 'nl_active' => 1 ),
			__METHOD__
		);
		$this->lb->reuseConnection( $dbr );

		if ( $res->numRows() === 0 ) {
			return null;
		}

		return $this->getNewsletterFromRow( $res->current() );
	}


	/**
	 * @param int $id
	 *
	 * @return int[]
	 */
	public function getPublishersFromID( $id ) {
		Assert::parameterType( 'integer', $id, '$id' );

		$dbr = $this->lb->getConnection( DB_SLAVE );

		$result = $dbr->selectFieldValues(
			'nl_publishers',
			'nlp_publisher_id',
			array( 'nlp_newsletter_id' => $id ),
			__METHOD__
		);
		$this->lb->reuseConnection( $dbr );

		return array_map( 'intval', $result );
	}

	/**
	 * @param int $id
	 *
	 * @return string[]
	 */
	public function getSubscribersFromID( $id ) {
		Assert::parameterType( 'integer', $id, '$id' );

		$dbr = $this->lb->getConnection( DB_SLAVE );

		$result = $dbr->selectFieldValues(
			'nl_subscriptions',
			'nls_subscriber_id',
			array( 'nls_newsletter_id' => $id ),
			__METHOD__
		);
		$this->lb->reuseConnection( $dbr );

		return $result;
	}

	/**
	 * @param int $id
	 *
	 * @return Newsletter
	 */
	public function getNewsletterForPageId( $id ) {
		Assert::parameterType( 'integer', $id, '$id' );

		$dbr = $this->lb->getConnection( DB_SLAVE );

		$res = $dbr->select(
			'nl_newsletters',
			array( 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ),
			array( 'nl_main_page_id' => $id, 'nl_active' => 1 ),
			__METHOD__
		);
		$this->lb->reuseConnection( $dbr );

		return $this->getNewsletterFromRow( $res->current() );
	}

	/**
	 * @param User $user
	 *
	 * @return Newsletter[]
	 */
	public function getNewslettersUserIsPublisherOf( User $user ) {
		$dbr = $this->lb->getConnection( DB_SLAVE );

		$res = $dbr->select(
			array( 'nl_publishers', 'nl_newsletters' ),
			array( 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ),
			array( 'nlp_publisher_id' => $user->getId(), 'nl_active' => 1 ),
			__METHOD__,
			array(),
			array( 'nl_newsletters' => array( 'LEFT JOIN', 'nl_id=nlp_newsletter_id' ) )
		);
		$this->lb->reuseConnection( $dbr );

		return $this->getNewslettersFromResult( $res );
	}

	/**
	 * @return Newsletter[]
	 */
	public function getAllNewsletters() {
		$dbr = $this->lb->getConnection( DB_SLAVE );

		$res = $dbr->select(
			array( 'nl_newsletters' ),
			array( 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ),
			array( 'nl_active' => 1 ),
			__METHOD__
		);
		$this->lb->reuseConnection( $dbr );

		return $this->getNewslettersFromResult( $res );
	}


	/**
	 * Fetch all newsletter names
	 *
	 * @param string $name
	 * @return bool|ResultWrapper
	 * @throws DBUnexpectedError
	 * @throws MWException
	 */
	public function newsletterExistsWithName( $name ) {
		Assert::parameterType( 'string', $name, '$name' );
		$dbr = $this->lb->getConnection( DB_SLAVE );

		$res = $dbr->select(
			'nl_newsletters',
			array( 'nl_name' ),
			array( 'nl_name' => $name )
		);

		$this->lb->reuseConnection( $dbr );
		return $res;
	}

	/**
	 * Fetch all newsletter Main Pages
	 *
	 * @param int $mainPageId
	 * @return bool|ResultWrapper
	 * @throws DBUnexpectedError
	 * @throws MWException
	 */
	public function newsletterExistsForMainPage( $mainPageId ) {
		Assert::parameterType( 'integer', $mainPageId , '$mainPageId' );
		$dbr = $this->lb->getConnection( DB_SLAVE );

		$res = $dbr->select(
			'nl_newsletters',
			array( 'nl_main_page_id' ),
			array( 'nl_main_page_id' => $mainPageId )
		);

		$this->lb->reuseConnection( $dbr );
		return $res;
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
		Assert::parameterType( 'integer', $newsletterId, '$newsletterId' );
		Assert::parameterType( 'integer', $pageId, '$pageId' );
		Assert::parameterType( 'integer', $publisherId, '$publisherId' );

		// Note: the writeDb is used as this is used in the next insert
		$dbw = $this->lb->getConnection( DB_MASTER );

		$lastIssueId = $dbw->selectRowCount(
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
			$success = $dbw->insert( 'nl_issues', $rowData, __METHOD__ );
			$this->lb->reuseConnection( $dbw );
			return $success;
		} catch ( DBQueryError $ex ) {
			$this->lb->reuseConnection( $dbw );
			return false;
		}
	}

}
