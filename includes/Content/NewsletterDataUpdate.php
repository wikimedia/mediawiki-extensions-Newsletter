<?php

namespace MediaWiki\Extension\Newsletter\Content;

use DataUpdate;
use MediaWiki\Extension\Newsletter\Newsletter;
use MediaWiki\Extension\Newsletter\NewsletterStore;
use MediaWiki\Extension\Newsletter\NewsletterValidator;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Title\Title;
use User;

/**
 * @license GPL-2.0-or-later
 * @author tonythomas
 */
class NewsletterDataUpdate extends DataUpdate {

	/** @var NewsletterContent */
	private $content;
	/** @var User Triggering user */
	private $user;
	/** @var Title */
	private $title;

	/**
	 * @param NewsletterContent $content
	 * @param Title $title
	 * @param User $user
	 *
	 * @todo User might be wrong if triggered from template edits etc.
	 */
	public function __construct( NewsletterContent $content, Title $title, User $user ) {
		$this->content = $content;
		$this->user = $user;
		$this->title = $title;
	}

	/**
	 * @param string $newNewsletterName
	 * @return int
	 */
	protected function getNewslettersWithNewsletterMainPage( $newNewsletterName ) {
		$dbr = wfGetDB( DB_REPLICA );

		return $dbr->selectRowCount(
			'nl_newsletters',
			'*',
			$dbr->makeList( [
				'nl_name' => $newNewsletterName,
				$dbr->makeList(
					[
						'nl_main_page_id' => $this->content->getMainPage()->getArticleID(),
						'nl_active' => 1
					], LIST_AND )
			], LIST_OR ),
			__METHOD__
		);
	}

	protected function createNewNewsletterWithData( NewsletterStore $store, $formData ) {
		$newNewsletterName = $formData['Name'];
		if ( $this->getNewslettersWithNewsletterMainPage( $newNewsletterName ) ) {
			return false;
		}

		$validator = new NewsletterValidator( $formData );
		$validation = $validator->validate( true );

		if ( !$validation->isGood() ) {
			// Invalid input was entered
			return $validation;
		}

		$title = Title::makeTitleSafe( NS_NEWSLETTER, $newNewsletterName );
		$newsletter = new Newsletter( 0,
			$title->getText(),
			$formData['Description'],
			$formData['MainPage']->getArticleID()
		);

		$newsletterCreated = $store->addNewsletter( $newsletter );
		if ( !$newsletterCreated ) {
			return false;
		}

		$newsletter->subscribe( $this->user );
		$store->addPublisher( $newsletter, [ $this->user->getId() ] );

		return $newsletter;
	}

	public function doUpdate() {
		$logger = LoggerFactory::getInstance( 'newsletter' );
		$store = NewsletterStore::getDefaultInstance();
		// We might have a situation when the newsletter is not created yet. Hence, we should add
		// that to the database, and exit.
		$newsletter = Newsletter::newFromName( $this->title->getText() );

		$formData = [
			'Name' => $this->title->getText(),
			'Description' => $this->content->getDescription(),
			'MainPage' => $this->content->getMainPage()
		];

		if ( !$newsletter ) {
			// Creating a new newsletter that newsletter is not in the
			// database yet.
			$newsletter = $this->createNewNewsletterWithData( $store, $formData );
			if ( !$newsletter ) {
				// Couldn't insert to the DB..
				$logger->warning( 'newsletter-create-error' );
				return;
			}
		}
		// This was a possible edit to an existing newsletter.
		$newsletterId = $newsletter->getId();

		if ( $this->content->getDescription() != $newsletter->getDescription() ) {
			$store->updateDescription( $newsletterId, $this->content->getDescription() );
		}

		$updatedMainPageId = $this->content->getMainPage()->getArticleID();
		if ( $updatedMainPageId != $newsletter->getPageId() ) {
			$store->updateMainPage( $newsletterId, $updatedMainPageId );
		}

		$updatedPublishers = array_map( [ User::class, 'newFromName' ], $this->content->getPublishers() );
		$oldPublishersIds = $newsletter->getPublishers();
		$updatedPublishersIds = [];

		foreach ( $updatedPublishers as $user ) {
			if ( $user instanceof User ) {
				$updatedPublishersIds[] = $user->getId();
			}
		}
		// Do the actual modifications now
		$added = array_diff( $updatedPublishersIds, $oldPublishersIds );
		$removed = array_diff( $oldPublishersIds, $updatedPublishersIds );

		// Check if people have been added
		if ( $added ) {
			// Adds the new publishers to subscription list
			$store->addSubscription( $newsletter, $added );
			$store->addPublisher( $newsletter, $added );
			$newsletter->notifyPublishers(
				$added, $this->user, Newsletter::NEWSLETTER_PUBLISHERS_ADDED
			);
		}

		// Check if people have been removed
		if ( $removed ) {
			$store->removePublisher( $newsletter, $removed );
			$newsletter->notifyPublishers(
				$removed, $this->user, Newsletter::NEWSLETTER_PUBLISHERS_REMOVED
			);
		}
	}

}
