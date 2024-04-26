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
		$dbw->newInsertQueryBuilder()
			->insertInto( 'nl_newsletters' )
			->row( $rowData )
			->caller( __METHOD__ )
			->execute();
	}

	protected function getNewsletterId() {
		$dbr = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$res = $dbr->newSelectQueryBuilder()
			->select( 'nl_id' )
			->from( 'nl_newsletters' )
			->where( [
				'nl_name' => 'MyNewsletter',
			] )
			->caller( __METHOD__ )
			->fetchField();
		if ( $res !== false ) {
			return $res;
		}
		return null;
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
		$result = $dbr->newSelectQueryBuilder()
			->select( [ 'nls_subscriber_id' ] )
			->from( 'nl_subscriptions' )
			->where( [
				'nls_newsletter_id' => $this->getNewsletterId(),
			] )
			->caller( __METHOD__ )
			->fetchRowCount();

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
		$result = $dbr->newSelectQueryBuilder()
			->select( [ 'nls_subscriber_id' ] )
			->from( 'nl_subscriptions' )
			->where( [
				'nls_newsletter_id' => $this->getNewsletterId(),
			] )
			->caller( __METHOD__ )
			->fetchRowCount();

		$this->assertSame( 0, $result );
	}

}
