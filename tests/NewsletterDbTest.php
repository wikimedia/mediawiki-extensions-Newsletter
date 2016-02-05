<?php

/**
 * @covers NewsletterDb
 *
 * @author Addshore
 */
class NewsletterDbTest extends PHPUnit_Framework_TestCase {

	/**
	 * @return PHPUnit_Framework_MockObject_MockObject|IDatabase
	 */
	private function getMockIDatabase() {
		return $this->getMock( 'IDatabase' );
	}

	/**
	 * @return PHPUnit_Framework_MockObject_MockObject|LoadBalancer
	 */
	private function getMockLoadBalancer( $db ) {
		$mock = $this->getMockBuilder( 'LoadBalancer' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $db ) );
		return $mock;
	}

	public function testAddSubscriber() {
		$mockWriteDb = $this->getMockIDatabase();
		$mockWriteDb->expects( $this->once() )
			->method( 'insert' )
			->with(
				'nl_subscriptions',
				array( 'nls_subscriber_id' => 1, 'nls_newsletter_id' => 2 )
			);
		$mockWriteDb->expects( $this->once() )
			->method( 'affectedRows' )
			->will( $this->returnValue( 1 ) );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );
		$result = $table->addSubscription( 1, 2 );

		$this->assertEquals( true, $result );
	}

}
