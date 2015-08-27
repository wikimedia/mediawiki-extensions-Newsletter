<?php

/**
 * Special page for subscribing/un-subscribing a newsletter
 */
class SpecialNewsletters extends SpecialPage {

	public static $fields = array(
		'nl_name' => 'name',
		'nl_desc' => 'description',
		'subscriber_count' => 'subscriber_count',
		'action' => 'action',
	);

	# Array containing all newsletter ids in nl_subscriptions table
	public static $allSubscribedNewsletterId = array();

	# Array containing all newsletter ids to which the logged in user is subscribed to
	public static $subscribedNewsletterId = array();

	# Subscriber count
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

	public static function getSubscribedNewsletters( $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'nl_subscriptions',
			array( 'newsletter_id' ),
			array( 'subscriber_id' => $id ),
			__METHOD__
		);
		foreach ( $res as $row ) {
			self::$subscribedNewsletterId[] = $row->newsletter_id;
		}

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
