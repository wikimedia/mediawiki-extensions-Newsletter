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
		$out = $this->getOutput();
		$this->getOutput()->addModules( 'ext.newsletter' );
		$pager = new NewsletterTablePager();

		if ( $pager->getNumRows() > 0 ) {
			$out->addHTML(
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

class NewsletterTablePager extends TablePager {

	public function getFieldNames() {
		static $headers = null;
		if ( is_null( $headers ) ) {
			$headers = array();
			foreach ( SpecialNewsletters::$fields as $field => $property ) {
				$headers[$field] = $this->msg( "newsletter-header-$property" )->text();
			}
		}

		return $headers;
	}

	public function getQueryInfo() {
		$info = array(
			'tables' => array( 'nl_newsletters' ),
			'fields' => array(
				'nl_name',
				'nl_desc',
				'nl_id',
			),
		);

		return $info;
	}

	public function formatValue( $field, $value ) {
		switch ( $field ) {
			case 'nl_name':
				$dbr = wfGetDB( DB_SLAVE );
				$res = $dbr->select(
					'nl_newsletters',
					array( 'nl_main_page_id' ),
					array( 'nl_name' => $value ),
					__METHOD__
				);

				$mainPageId = '';
				foreach ( $res as $row ) {
					$mainPageId = $row->nl_main_page_id;
				}

				$url = $mainPageId ? Title::newFromID( $mainPageId )->getFullURL() : "#";

				return '<a href="' . $url . '">' . $value . '</a>';
			case 'nl_desc':
				return $value;
			case 'subscriber_count':
				return HTML::element(
					'input',
					array(
						'type' => 'textbox',
						'readonly' => 'true',
						'id' => 'newsletter-' . $this->mCurrentRow->nl_id,
						'value' => in_array(
							$this->mCurrentRow->nl_id,
							SpecialNewsletters::$allSubscribedNewsletterId
						) ?
							SpecialNewsletters::$subscriberCount[$this->mCurrentRow->nl_id] : 0,

					)
				);
			case 'action' :
				$radioSubscribe = Html::element(
						'input',
						array(
							'type' => 'radio',
							'name' => 'nl_id-' . $this->mCurrentRow->nl_id,
							'value' => 'subscribe',
							'checked' => in_array(
								$this->mCurrentRow->nl_id,
								SpecialNewsletters::$subscribedNewsletterId
							) ? true : false,
						)
					) . $this->msg( 'newsletter-subscribe-button-label' );
				$radioUnSubscribe = Html::element(
						'input',
						array(
							'type' => 'radio',
							'name' => 'nl_id-' . $this->mCurrentRow->nl_id,
							'value' => 'unsubscribe',
							'checked' => in_array(
								$this->mCurrentRow->nl_id,
								SpecialNewsletters::$subscribedNewsletterId
							) ? false : true,
						)
					) . $this->msg( 'newsletter-unsubscribe-button-label' );

				return $radioSubscribe . $radioUnSubscribe;
		}
	}

	public function endQuery( $value ) {
		$this->getOutput()->addWikiMsg( 'newsletter-create-confirmation' );
	}

	public function getDefaultSort() {
		return 'nl_name';
	}

	public function isFieldSortable( $field ) {
		return false;
	}

}
