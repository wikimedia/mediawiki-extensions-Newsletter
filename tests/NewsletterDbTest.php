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

	public function testAddSubscriber() {
		$mockWriteDb = $this->getMockIDatabase();
		$mockWriteDb->expects( $this->once() )
			->method( 'insert' )
			->with(
				'nl_subscriptions',
				array( 'nls_subscriber_id' => 1, 'nls_newsletter_id' => 2 )
			)
			->will( $this->returnValue( true ) );

		$table = new NewsletterDb( $this->getMockIDatabase(), $mockWriteDb );
		$result = $table->addSubscription( 1, 2 );

		$this->assertEquals( true, $result );
	}

}
