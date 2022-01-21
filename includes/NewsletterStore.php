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
	 */
	public function addSubscription( Newsletter $newsletter, array $userIds ): void {
		$this->db->addSubscription( $newsletter, $userIds );
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 */
	public function removeSubscription( Newsletter $newsletter, array $userIds ): void {
		$this->db->removeSubscription( $newsletter, $userIds );
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 */
	public function addPublisher( Newsletter $newsletter, array $userIds ): void {
		$success = $this->db->addPublisher( $newsletter, $userIds );
		if ( $success ) {
			foreach ( $userIds as $userId ) {
				$this->logger->logPublisherAdded( $newsletter, User::newFromId( $userId ) );
			}
		}
	}

	/**
	 * @param Newsletter $newsletter
	 * @param array $userIds
	 */
	public function removePublisher( Newsletter $newsletter, array $userIds ): void {
		$success = $this->db->removePublisher( $newsletter, $userIds );
		if ( $success ) {
			foreach ( $userIds as $userId ) {
				$this->logger->logPublisherRemoved( $newsletter, User::newFromId( $userId ) );
			}
		}
	}

	/**
	 * @param Newsletter $newsletter
	 * @return bool Success of the action
	 */
	public function addNewsletter( Newsletter $newsletter ): bool {
		$id = $this->db->addNewsletter( $newsletter );
		if ( $id ) {
			$newsletter->setId( $id );
			$this->logger->logNewsletterAdded( $newsletter );
		}
		return (bool)$id;
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
	 * @return bool Success of the action
	 */
	public function updateMainPage( int $id, int $pageId ): bool {
		return $this->db->updateMainPage( $id, $pageId );
	}

	/**
	 * @param Newsletter $newsletter
	 */
	public function deleteNewsletter( Newsletter $newsletter ): void {
		$this->db->deleteNewsletter( $newsletter );
	}

	/**
	 * Restore a newsletter from the delete logs
	 *
	 * @param string $newsletterName
	 */
	public function restoreNewsletter( string $newsletterName ): void {
		$this->db->restoreNewsletter( $newsletterName );
	}

	/**
	 * Roll back a newsletter addition silently due to a failure in creating a
	 * content model for it
	 *
	 * @param Newsletter $newsletter
	 */
	public function rollBackNewsletterAddition( Newsletter $newsletter ): void {
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
	public function getNewsletterFromName( string $name, bool $active = true ) {
		return $this->db->getNewsletterFromName( $name, $active );
	}

	/**
	 * @param int $id
	 * @return int[]
	 */
	public function getPublishersFromID( int $id ): array {
		return $this->db->getPublishersFromID( $id );
	}

	/**
	 * @param int $id
	 * @return int
	 */
	public function getNewsletterSubscribersCount( int $id ): int {
		return $this->db->getNewsletterSubscribersCount( $id );
	}

	/**
	 * @param int $id
	 * @return int[]
	 */
	public function getSubscribersFromID( int $id ): array {
		return $this->db->getSubscribersFromID( $id );
	}

	/**
	 * Fetch all newsletter Main Pages
	 *
	 * @param int $mainPageId
	 * @return IResultWrapper
	 */
	public function newsletterExistsForMainPage( int $mainPageId ) {
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
