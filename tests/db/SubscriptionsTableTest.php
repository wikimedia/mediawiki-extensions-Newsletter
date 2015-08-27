<?php

/**
 * @covers SubscriptionsTable
 */
class SubscriptionsTableTest extends PHPUnit_Framework_TestCase {

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
				array( 'subscriber_id' => 1, 'newsletter_id' => 2 )
			)
			->will( $this->returnValue( true ) );

		$table = new SubscriptionsTable( $this->getMockIDatabase(), $mockWriteDb );
		$result = $table->addSubscription( 1, 2 );

		$this->assertEquals( true, $result );
	}

	public function testRemoveSubscriber() {
		//TODO implement me
		$this->markTestIncomplete( 'Not yet implemented' );
	}

	public function testGetSubscriptionsForUser() {
		//TODO implement me
		$this->markTestIncomplete( 'Not yet implemented' );
	}

}
