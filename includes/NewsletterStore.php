<?php

use Wikimedia\Assert\Assert;

/**
 * @license GNU GPL v2+
 * @author Addshore
 */
class NewsletterStore {

	/**
	 * @var NewsletterDb
	 */
	private $db;

	/**
	 * @var NewsletterLogger
	 */
	private $logger;

	/**
	 * @var static
	 */
	private static $instance;

	public function __construct( NewsletterDb $db, NewsletterLogger $logger ) {
		$this->db = $db;
		$this->logger = $logger;
	}

	public static function getDefaultInstance(){
		if ( !self::$instance ) {
			self::$instance = new self(
				new NewsletterDb( wfGetLB() ),
				new NewsletterLogger()
			);
		}
		return self::$instance;
	}

	/**
	 * @param Newsletter $newsletter
	 * @param User $user
	 *
	 * @return bool success of the action
	 */
	public function addSubscription( Newsletter $newsletter, User $user ) {
		return $this->db->addSubscription( $newsletter, $user );
	}

	/**
	 * @param Newsletter $newsletter
	 * @param User $user
	 *
	 * @return bool success of the action
	 */
	public function removeSubscription( Newsletter $newsletter, User $user ) {
		return $this->db->removeSubscription( $newsletter, $user );
	}

	/**
	 * @param Newsletter $newsletter
	 * @param User $user
	 *
	 * @return bool success of the action
	 */
	public function addPublisher( Newsletter $newsletter, User $user ) {
		$success = $this->db->addPublisher( $newsletter, $user );
		if ( $success ) {
			$this->logger->logPublisherAdded( $newsletter, $user );
		}
		return $success;
	}

	/**
	 * @param Newsletter $newsletter
	 * @param User $user
	 *
	 * @return bool success of the action
	 */
	public function removePublisher( Newsletter $newsletter, User $user ) {
		$success = $this->db->removePublisher( $newsletter, $user );
		if ( $success ) {
			$this->logger->logPublisherRemoved( $newsletter, $user );
		}
		return $success;
	}

	/**
	 * @param Newsletter $newsletter
	 *
	 * @return bool success of the action
	 */
	public function addNewsletter( Newsletter $newsletter ) {
		$success = $this->db->addNewsletter( $newsletter );
		if ( $success ) {
			$newsletter->setId( $success );
			$this->logger->logNewsletterAdded( $newsletter );
		}
		return (bool)$success;
	}

	/**
	 * @param int $id
	 * @param string $description
	 *
	 * @return bool success of the action
	 */
	public function updateDescription( $id, $description ) {
		return $this->db->updateDescription( $id, $description );
	}

	/**
	 * @param int $id
	 * @param string $name
	 *
	 * @return bool success of the action
	 */
	public function updateName( $id, $name ) {
		return $this->db->updateName( $id, $name );
	}

	/**
	 * @param int $id
	 * @param int $pageId
	 *
	 * @return bool success of the action
	 */
	public function updateMainPage( $id, $pageId ) {
		return $this->db->updateMainPage( $id, $pageId );
	}

	/**
	 * @param Newsletter $newsletter
	 * @param string $reason
	 *
	 * @return bool success of the action
	 */
	public function deleteNewsletter( Newsletter $newsletter, $reason ) {
		$success = $this->db->deleteNewsletter( $newsletter );
		if ( $success ) {
			$this->logger->logNewsletterDeleted( $newsletter, $reason );
		}
		return $success;
	}

	/**
	 * @param int $id
	 *
	 * @return Newsletter|null null if no newsletter exists with the provided id
	 */
	public function getNewsletter( $id ) {
		return $this->db->getNewsletter( $id );
	}


	/**
	 * @param int $id
	 *
	 * @return int[]
	 */
	public function getPublishersFromID( $id ) {
		return $this->db->getPublishersFromID( $id );
	}

	/**
	 * @param int $id
	 *
	 * @return string[]
	 */
	public function getSubscribersFromID( $id ) {
		return $this->db->getSubscribersFromID( $id );
	}

	/**
	 * @param int $id
	 *
	 * @return Newsletter
	 */
	public function getNewsletterForPageId( $id ) {
		return $this->db->getNewsletterForPageId( $id );
	}

	/**
	 * @param User $user
	 *
	 * @return Newsletter[]
	 */
	public function getNewslettersUserIsPublisherOf( User $user ) {
		return $this->db->getNewslettersUserIsPublisherOf( $user );
	}

	/**
	 * @return Newsletter[]
	 */
	public function getAllNewsletters() {
		return $this->db->getAllNewsletters();
	}


	/**
	 * Fetch all newsletter names
	 *
	 * @param string $name
	 * @return bool|ResultWrapper
	 * @throws MWException
	 */
	public function newsletterExistsWithName( $name ) {
		return $this->db->newsletterExistsWithName( $name );
	}

	/**
	 * Fetch all newsletter Main Pages
	 *
	 * @param int $mainPageId
	 * @return bool|ResultWrapper
	 * @throws MWException
	 */
	public function newsletterExistsForMainPage( $mainPageId ) {
		return $this->db->newsletterExistsWithName( $mainPageId );
	}

	/**
	 * @param Newsletter $newsletter
	 * @param Title $title
	 * @param User $publisher
	 * @param string $summary
	 *
	 * @return bool
	 */
	public function addNewsletterIssue( Newsletter $newsletter, Title $title, User $publisher, $summary ) {
		$success = $this->db->addNewsletterIssue( $newsletter, $title, $publisher );
		if ( $success ) {
			$this->logger->logNewIssue( $publisher, $newsletter, $title, $success, $summary );
		}
		return (bool)$success;
	}

}
