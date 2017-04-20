<?php
/**
 * @license GNU GPL v2+
 * @author tonythomas
 */
use \MediaWiki\Logger\LoggerFactory;

class NewsletterDataUpdate extends DataUpdate {

	private $content; /** NewsletterContent */
	private $user; /** @var User Triggering user */
	private $title; /** @var Title */
	protected $newsletter; /** @var Newsletter */
	private $name; /** @var string */

	/**
	 * @param NewsletterContent $content
	 * @param Title $title
	 * @param User $user
	 *
	 * @todo User might be wrong if triggered from template edits etc.
	 */
	function __construct( NewsletterContent $content, Title $title, User $user ) {
		$this->content = $content;
		$this->user = $user;
		$this->title = $title;
	}

	function doUpdate() {
		$logger = LoggerFactory::getInstance( 'newsletter' );

		$store = NewsletterStore::getDefaultInstance();
		// We might have a situation when the newsletter is not created yet. Hence, we should add
		// that to the database, and exit.
		$newsletter = Newsletter::newFromName( $this->title->getText() );

		if ( !$newsletter ) {
			// Possible API edit to create a new newsletter, and the newsletter is not in the
			// database yet.
			$this->name = $this->title->getText();
			$dbr = wfGetDB( DB_SLAVE );
			$rows = $dbr->select(
				'nl_newsletters',
				[ 'nl_name', 'nl_main_page_id', 'nl_active' ],
				$dbr->makeList( [
					'nl_name' => $this->name,
					$dbr->makeList(
						[
							'nl_main_page_id' => $this->content->getMainPage()->getArticleID(),
							'nl_active' => 1
						], LIST_AND )
				], LIST_OR )
			);

			// Check whether another existing newsletter has the same name or main page
			foreach ( $rows as $row ) {
				if ( $row->nl_name === $this->name ) {
					$logger->warning( 'newsletter-exist-error', $this->name );
					return;
				} elseif ( (int)$row->nl_main_page_id === $this->content->getMainPage()->getArticleID()
				           && (int)$row->nl_active === 1 ) {
					$logger->warning( 'newsletter-mainpage-in-use' );
					return;
				}
			}
			$title = Title::makeTitleSafe( NS_NEWSLETTER, $this->name );
			$newsletter = new Newsletter( 0,
				$title->getText(),
				$this->content->getDescription(),
				$this->content->getMainPage()->getArticleID()
			);
			$newsletterCreated = $store->addNewsletter( $newsletter );
			if ( $newsletterCreated ) {
				$newsletter->subscribe( $this->user );
				$store->addPublisher( $newsletter, $this->user );
				return;
			} else {
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

		$updatedPublishers = array_map( 'User::newFromName', $this->content->getPublishers() );
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

		// @todo Do this in a batch..
		foreach ( $added as $auId ) {
			$store->addPublisher( $newsletter, User::newFromId( $auId ) );
		}

		if ( $added ) {
			EchoEvent::create(
				[
					'type' => 'newsletter-newpublisher',
					'extra' => [
						'newsletter-name' => $newsletter->getName(),
						'new-publishers-id' => $added,
						'newsletter-id' => $newsletterId
					],
					'agent' => $this->user
				]
			);
		}
		foreach ( $removed as $ruId ) {
			$store->removePublisher( $newsletter, User::newFromId( $ruId ) );
		}
	}
}
