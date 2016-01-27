<?php

/**
 * Class representing a newsletter
 *
 * @license GNU GPL v2+
 * @author Addshore
 * @author Glaisher
 */
class Newsletter {
	/**
	 * @var int
	 */
	private $id;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $description;

	/**
	 * @var int
	 */
	private $pageId;

	/**
	 * @var array
	 */
	private $publishers;

	/**
	 * @var array
	 */
	private $subscribers;

	/**
	 * @param int|null $id
	 * @param string $name
	 * @param string $description
	 * @param int $pageId
	 */
	public function __construct( $id, $name, $description, $pageId ) {
		$this->id = (int)$id;
		$this->name = $name;
		$this->description = $description;
		$this->pageId = (int)$pageId;
	}

	/**
	 * @param int $id
	 *
	 * @return Newsletter|null null if no newsletter exists with the provided id
	 */
	public static function newFromID( $id ) {
		return NewsletterDb::newFromGlobalState()
			->getNewsletter( $id );
	}

	/**
	 * @return int|null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @return int
	 */
	public function getPageId() {
		return $this->pageId;
	}

	/**
	 * @return array
	 */
	public function getSubscribers() {
		$this->loadSubscribers();
		return $this->subscribers;
	}

	/**
	 * @return int
	 */
	public function getSubscriberCount() {
		$this->loadSubscribers();
		return count( $this->subscribers );
	}

	/**
	 * @return array
	 */
	public function getPublishers() {
		$this->loadPublishers();
		return $this->publishers;
	}

	/**
	 * @todo this is probably not scalable...
	 *
	 * @param User $user
	 *
	 * @return bool
	 */
	public function isSubscribed( User $user ) {
		$this->loadSubscribers();
		return in_array( $user->getId(), $this->subscribers );
	}

	/**
	 * @param User $user
	 *
	 * @return bool
	 */
	public function isPublisher( User $user ) {
		$this->loadPublishers();
		return in_array( $user->getId(), $this->publishers );
	}

	/**
	 * Load the publishers from the database if it has not been queried yet
	 */
	private function loadPublishers() {
		if ( $this->publishers === null ) {
			// Not queried yet so let's do it now
			$this->publishers = NewsletterDb::newFromGlobalState()
				->getPublishersFromID( $this->id );
		}
	}

	/**
	 * Load the subscribers from the database if it has not been queried yet
	 */
	private function loadSubscribers() {
		if ( $this->subscribers === null ) {
			// Not queried yet so let's do it now
			$this->subscribers = NewsletterDb::newFromGlobalState()
				->getSubscribersFromID( $this->id );
		}
	}

	/**
	 * Subscribe the specified user to this newsletter
	 *
	 * @param User $user
	 *
	 * @return Status
	 */
	public function subscribe( User $user ) {
		if ( $user->isAnon() ) {
			// IPs are not allowed to subscribe
			return Status::newFatal( 'newsletter-subscribe-ip-notallowed' );
		}

		$ndb = NewsletterDb::newFromGlobalState();

		if ( $ndb->addSubscription( $user->getId(), $this->id ) ) {
			return Status::newGood();
		} else {
			return Status::newFatal( 'newsletter-subscribe-fail', $this->name );
		}
	}

	/**
	 * Unsubscribe the specified user from this newsletter
	 *
	 * @param User $user
	 *
	 * @return Status
	 */
	public function unsubscribe( User $user ) {
		$ndb = NewsletterDb::newFromGlobalState();

		if ( $ndb->removeSubscription( $user->getId(), $this->id ) ) {
			return Status::newGood();
		} else {
			return Status::newFatal( 'newsletter-unsubscribe-fail', $this->name );
		}
	}

	/**
	 * Check whether the user is allowed to delete the newsletter.
	 *
	 * @param User $user
	 *
	 * @return bool
	 */
	public function canDelete( User $user ) {
		return $this->isPublisher( $user ) || $user->isAllowed( 'newsletter-delete' );
	}

	/**
	 * Check whether the user is allowed to manage the newsletter.
	 *
	 * @param User $user
	 *
	 * @return bool
	 */
	public function canManage( User $user ) {
		return $this->isPublisher( $user ) || $user->isAllowed( 'newsletter-manage' );
	}
}
