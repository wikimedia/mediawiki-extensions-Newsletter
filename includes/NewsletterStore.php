<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * @license GPL-2.0-or-later
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
	 * @var self
	 */
	private static $instance;

	/**
	 * @param NewsletterDb $db
	 * @param NewsletterLogger $logger
	 */
	public function __construct( NewsletterDb $db, NewsletterLogger $logger ) {
		$this->db = $db;
		$this->logger = $logger;
	}

	/**
	 * @return self
	 */
	public static function getDefaultInstance() {
		if ( !self::$instance ) {
			self::$instance = new self(
				new NewsletterDb( MediaWikiServices::getInstance()->getDBLoadBalancer() ),
				new NewsletterLogger()
			);
		}
		return self::$instance;
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 *
	 * @return bool success of the action
	 */
	public function addSubscription( Newsletter $newsletter, $userIds ) {
		return $this->db->addSubscription( $newsletter, $userIds );
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 *
	 * @return bool success of the action
	 */
	public function removeSubscription( Newsletter $newsletter, $userIds ) {
		return $this->db->removeSubscription( $newsletter, $userIds );
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 *
	 * @return bool success of the action
	 */
	public function addPublisher( Newsletter $newsletter, $userIds ) {
		$success = $this->db->addPublisher( $newsletter, $userIds );
		if ( $success ) {
			foreach ( $userIds as $userId ) {
				$this->logger->logPublisherAdded( $newsletter, User::newFromId( $userId ) );
			}
		}
		return $success;
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 *
	 * @return bool success of the action
	 */
	public function removePublisher( Newsletter $newsletter, $userIds ) {
		$success = $this->db->removePublisher( $newsletter, $userIds );
		if ( $success ) {
			foreach ( $userIds as $userId ) {
				$this->logger->logPublisherRemoved( $newsletter, User::newFromId( $userId ) );
			}
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
	 *
	 * @return bool success of the action
	 */
	public function deleteNewsletter( Newsletter $newsletter ) {
		return $this->db->deleteNewsletter( $newsletter );
	}

	/**
	 * Restore a newsletter from the delete logs
	 *
	 * @param string $newsletterName
	 *
	 * @return bool success of the action
	 */
	public function restoreNewsletter( $newsletterName ) {
		return $this->db->restoreNewsletter( $newsletterName );
	}

	/**
	 * Roll back a newsletter addition silently due to a failure in creating a
	 * content model for it
	 *
	 * @param Newsletter $newsletter
	 */
	public function rollBackNewsletterAddition( Newsletter $newsletter ) {
		$this->db->deleteNewsletter( $newsletter );
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
	 * @param string $name
	 * @param bool $active
	 * @return Newsletter|null
	 */
	public function getNewsletterFromName( $name, $active = true ) {
		return $this->db->getNewsletterFromName( $name, $active );
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
	 * @return int
	 */
	public function getNewsletterSubscribersCount( $id ) {
		return $this->db->getNewsletterSubscribersCount( $id );
	}

	/**
	 * @param int $id
	 *
	 * @return int[]
	 */
	public function getSubscribersFromID( $id ) {
		return $this->db->getSubscribersFromID( $id );
	}

	/**
	 * Fetch all newsletter Main Pages
	 *
	 * @param int $mainPageId
	 * @return bool|IResultWrapper
	 * @throws MWException
	 */
	public function newsletterExistsForMainPage( $mainPageId ) {
		return $this->db->newsletterExistsForMainPage( $mainPageId );
	}

	/**
	 * @param Newsletter $newsletter
	 * @param Title $title
	 * @param User $publisher
	 * @param string $summary
	 *
	 * @return bool
	 */
	public function addNewsletterIssue(
		Newsletter $newsletter,
		Title $title,
		User $publisher,
		$summary
	) {
		$success = $this->db->addNewsletterIssue( $newsletter, $title, $publisher );
		if ( $success ) {
			$this->logger->logNewIssue( $publisher, $newsletter, $title, $success, $summary );
		}
		return (bool)$success;
	}

}
