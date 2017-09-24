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

	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->tablesUsed[] = 'nl_newsletters';
		$this->tablesUsed[] = 'nl_subscriptions';
	}

	protected function setUp() {
		parent::setUp();
		$dbw = wfGetDB( DB_MASTER );

		$user = self::$users['sysop']->getUser();
		$this->doLogin( 'sysop' );

		$rowData = [
			'nl_name' => 'MyNewsletter',
			'nl_desc' => 'This is a newsletter',
			'nl_main_page_id' => 1
		];
		$dbw->insert( 'nl_newsletters', $rowData, __METHOD__ );
	}

	protected function getNewsletterId() {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'nl_newsletters',
			[ 'nl_id' ],
			[
				'nl_name' => 'MyNewsletter',
			],
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
			[
				'action' => 'newslettersubscribe',
				'id' => $this->getNewsletterId(),
				'do' => 'subscribe',
			]
		);

		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->selectRowCount(
			'nl_subscriptions',
			[ 'subscriber_id' ],
			[
				'nls_newsletter_id' => $this->getNewsletterId(),
			],
			__METHOD__
		);

		$this->assertEquals( $result, 1 );
	}

	public function testApiNewsletterForUnsubscribingNewsletter() {
		$this->doApiRequestWithToken(
			[
				'action' => 'newslettersubscribe',
				'id' => $this->getNewsletterId(),
				'do' => 'subscribe',
			]
		);

		$this->doApiRequestWithToken(
			[
				'action' => 'newslettersubscribe',
				'id' => $this->getNewsletterId(),
				'do' => 'unsubscribe',
			]
		);

		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->selectRowCount(
			'nl_subscriptions',
			[ 'subscriber_id' ],
			[
				'nls_newsletter_id' => $this->getNewsletterId(),
			],
			__METHOD__
		);

		$this->assertEquals( $result, 0 );
	}

}
