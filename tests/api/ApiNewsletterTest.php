<?php

/**
 * Unit test to test Api module - ApiNewsletter
 *
 * @group API
 * @group medium
 * @group Database
 *
 * @covers ApiNewsletter
 *
 * @author Tina Johnson
 */
class ApiNewsletterTest extends ApiTestCase {

	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->tablesUsed[] = 'nl_newsletters';
		$this->tablesUsed[] = 'nl_subscriptions';
	}

	protected function setUp() {
		parent::setUp();
		$dbw = wfGetDB( DB_MASTER );

		$user = self::$users['sysop']->getUser();
		$this->doLogin( 'sysop' );

		$rowData = array(
			'nl_name' => 'MyNewsletter',
			'nl_desc' => 'This is a newsletter',
			'nl_main_page_id' => 1,
			'nl_frequency' => 'monthly',
		);
		$dbw->insert( 'nl_newsletters', $rowData, __METHOD__ );
	}

	protected function getNewsletterId() {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'nl_newsletters',
			array( 'nl_id' ),
			array(
				'nl_name' => 'MyNewsletter',
			),
			__METHOD__
		);
		$newsletterId = null;
		foreach ( $res as $row ) {
			$newsletterId = $row->nl_id;
		}

		return $newsletterId;
	}

	public function testApiNewsletterForSubscribingNewsletter() {
		$this->doApiRequestWithToken(
			array(
				'action' => 'newsletterapi',
				'newsletterId' => $this->getNewsletterId(),
				'todo' => 'subscribe',
			)
		);

		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->selectRowCount(
			'nl_subscriptions',
			array( 'subscriber_id' ),
			array(
				'newsletter_id' => $this->getNewsletterId(),
			),
			__METHOD__
		);

		$this->assertEquals( $result, 1 );
	}

	public function testApiNewsletterForUnsubscribingNewsletter() {
		$this->doApiRequestWithToken(
			array(
				'action' => 'newsletterapi',
				'newsletterId' => $this->getNewsletterId(),
				'todo' => 'unsubscribe',
			)
		);

		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->selectRowCount(
			'nl_subscriptions',
			array( 'subscriber_id' ),
			array(
				'newsletter_id' => $this->getNewsletterId(),
			),
			__METHOD__
		);

		$this->assertEquals( $result, 0 );
	}

}
