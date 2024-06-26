<?php

namespace MediaWiki\Extension\Newsletter;

use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Permissions\Authority;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

/**
 * Class representing a newsletter
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 * @author Glaisher
 */
class Newsletter {

	public const NEWSLETTER_PUBLISHERS_ADDED = 'added';
	public const NEWSLETTER_PUBLISHERS_REMOVED = 'removed';

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
		return NewsletterStore::getDefaultInstance()
			->getNewsletter( $id );
	}

	/**
	 * Fetch a new newsletter instance from given name
	 *
	 * @param string $name
	 * @param bool $active
	 * @return Newsletter|null
	 */
	public static function newFromName( $name, $active = true ) {
		return NewsletterStore::getDefaultInstance()->getNewsletterFromName( $name, $active );
	}

	/**
	 * @return int|null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param int $id
	 */
	public function setId( $id ) {
		$this->id = $id;
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
	public function getSubscribersCount(): int {
		return NewsletterStore::getDefaultInstance()->getNewsletterSubscribersCount( $this->id );
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
	 * @param UserIdentity $user
	 *
	 * @return bool
	 */
	public function isSubscribed( UserIdentity $user ) {
		$this->loadSubscribers();
		return in_array( $user->getId(), $this->subscribers );
	}

	/**
	 * @param UserIdentity $user
	 *
	 * @return bool
	 */
	public function isPublisher( UserIdentity $user ) {
		$this->loadPublishers();
		return in_array( $user->getId(), $this->publishers );
	}

	/**
	 * Load the publishers from the database if it has not been queried yet
	 */
	private function loadPublishers() {
		if ( $this->publishers === null ) {
			// Not queried yet so let's do it now
			$this->publishers = NewsletterStore::getDefaultInstance()
				->getPublishersFromID( $this->id );
		}
	}

	/**
	 * Load the subscribers from the database if it has not been queried yet
	 */
	private function loadSubscribers() {
		if ( $this->subscribers === null ) {
			// Not queried yet so let's do it now
			$this->subscribers = NewsletterStore::getDefaultInstance()
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

		$store = NewsletterStore::getDefaultInstance();
		$store->addSubscription( $this, [ $user->getId() ] );
		return Status::newGood();
	}

	/**
	 * Unsubscribe the specified user from this newsletter
	 *
	 * @param User $user
	 * @return Status
	 */
	public function unsubscribe( User $user ) {
		$store = NewsletterStore::getDefaultInstance();
		$store->removeSubscription( $this, [ $user->getId() ] );
		return Status::newGood();
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
	 * The user is allowed to manage a newsletter if the user is a publisher of
	 * the newsletter, or if the user has the newsletter-manage right.
	 *
	 * @param Authority $user
	 *
	 * @return bool
	 */
	public function canManage( Authority $user ) {
		return $this->isPublisher( $user->getUser() ) || $user->isAllowed( 'newsletter-manage' );
	}

	/**
	 * Check whether the user is allowed to restore the newsletter.
	 *
	 * @param Authority $performer
	 *
	 * @return bool
	 */
	public function canRestore( Authority $performer ) {
		return $this->isPublisher( $performer->getUser() ) || $performer->isAllowed( 'newsletter-restore' );
	}

	/**
	 * Notify new/removed publishers
	 *
	 * @param array $affectedUsers
	 * @param User $agent the user initiating the request
	 * @param string $event select between added/removed
	 */
	public function notifyPublishers( array $affectedUsers, User $agent, $event ) {
		$notification = [
			'extra' => [
				'newsletter-name' => $this->getName(),
				'newsletter-id' => $this->getId()
			],
			'agent' => $agent
		];

		if ( $event === self::NEWSLETTER_PUBLISHERS_ADDED ) {
			$notification['type'] = 'newsletter-newpublisher';
			$notification['extra']['new-publishers-id'] = $affectedUsers;
		} elseif ( $event === self::NEWSLETTER_PUBLISHERS_REMOVED ) {
			$notification['type'] = 'newsletter-delpublisher';
			$notification['extra']['del-publishers-id'] = $affectedUsers;
		}
		Event::create( $notification );
	}

}
