<?php

/**
 * Special page for subscribing/un-subscribing a newsletter
 */
class SpecialNewsletters extends SpecialPage {

	/**
	 * Array containing all newsletter ids in nl_subscriptions table
	 * @var array
	 * @todo FIXME this is called from other classes
	 */
	public static $allSubscribedNewsletterId = array();

	/**
	 * Array containing all newsletter ids to which the logged in user is subscribed to
	 * @var array
	 * @todo FIXME this is called from other classes
	 */
	public static $subscribedNewsletterId = array();

	/**
	 * Subscriber count
	 * @var array
	 * @todo FIXME this is called from other classes
	 */
	public static $subscriberCount = array();

	public function __construct() {
		parent::__construct( 'Newsletters' );
		self::getSubscribedNewsletters( $this->getUser()->getId() );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->requireLogin();
		$output = $this->getOutput();
		$output->addModules( 'ext.newsletter' );
		$pager = new NewsletterTablePager();

		if ( $pager->getNumRows() > 0 ) {
			$output->addHTML(
				$pager->getNavigationBar() .
				$pager->getBody() .
				$pager->getNavigationBar()
			);
		}
	}

	public static function getSubscribedNewsletters( $userId ) {
		$subscriptionsTable = SubscriptionsTable::newFromGlobalState();
		$userSubscriptions = $subscriptionsTable->getSubscriptionsForUser( $userId );
		self::$subscribedNewsletterId = $userSubscriptions;

		$dbr = wfGetDB( DB_SLAVE );

		$resl = $dbr->select(
			'nl_subscriptions',
			array( 'newsletter_id' ),
			array(),
			__METHOD__
		);

		foreach ( $resl as $row ) {
			$result = $dbr->selectRowCount(
				'nl_subscriptions',
				array(),
				array( 'newsletter_id' => $row->newsletter_id ),
				__METHOD__
			);
			self::$allSubscribedNewsletterId[] = $row->newsletter_id;
			self::$subscriberCount[$row->newsletter_id] = $result;
		}
	}
}
