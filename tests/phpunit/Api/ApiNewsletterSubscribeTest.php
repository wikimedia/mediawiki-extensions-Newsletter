<?php

/**
 * @group API
 * @group medium
 * @group Database
 *
 * @covers \MediaWiki\Extension\Newsletter\Api\ApiNewsletterSubscribe
 *
 * @author Tina Johnson
 */
class ApiNewsletterSubscribeTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();
		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_PRIMARY );

		$rowData = [
			'nl_name' => 'MyNewsletter',
			'nl_desc' => 'This is a newsletter',
			'nl_main_page_id' => 1
		];
		$dbw->insert( 'nl_newsletters', $rowData, __METHOD__ );
	}

	protected function getNewsletterId() {
		$dbr = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_REPLICA );
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

		$dbr = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$result = $dbr->selectRowCount(
			'nl_subscriptions',
			[ 'nls_subscriber_id' ],
			[
				'nls_newsletter_id' => $this->getNewsletterId(),
			],
			__METHOD__
		);

		$this->assertSame( 1, $result );
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

		$dbr = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$result = $dbr->selectRowCount(
			'nl_subscriptions',
			[ 'nls_subscriber_id' ],
			[
				'nls_newsletter_id' => $this->getNewsletterId(),
			],
			__METHOD__
		);

		$this->assertSame( 0, $result );
	}

}
