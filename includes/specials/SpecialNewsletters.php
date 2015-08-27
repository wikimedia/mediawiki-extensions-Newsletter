<?php

/**
 * Special page for subscribing/un-subscribing a newsletter
 *
 * @license GNU GPL v2+
 * @author Tina Johnson
 */
class SpecialNewsletters extends SpecialPage {

	/**
	 * Array containing all newsletter ids to which the logged in user is subscribed to
	 * @var array
	 * @todo FIXME this is called from other classes
	 */
	public static $subscribedNewsletterId = array();

	public function __construct() {
		parent::__construct( 'Newsletters' );
		self::getSubscribedNewsletters( $this->getUser()->getId() );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->requireLogin();
		$out = $this->getOutput();
		$out->addModules( 'ext.newsletter' );
		$out->setSubtitle( LinksGenerator::getSubtitleLinks() );
		$pager = new NewsletterTablePager();

		if ( $pager->getNumRows() > 0 ) {
			$out->addHTML(
				$pager->getNavigationBar() .
				$pager->getBody() .
				$pager->getNavigationBar()
			);
		} else {
			$out->showErrorPage( 'newsletters', 'newsletter-none-found' );
		}
	}

	public static function getSubscribedNewsletters( $userId ) {
		$subscriptionsTable = SubscriptionsTable::newFromGlobalState();
		$userSubscriptions = $subscriptionsTable->getSubscriptionsForUser( $userId );
		self::$subscribedNewsletterId = $userSubscriptions;
	}

}
