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
		$user = User::newFromName( 'Test User' );
		$user->addToDatabase();

		$mockWriteDb->expects( $this->once() )
			->method( 'insert' )
			->with(
				'nl_subscriptions',
				array( array( 'nls_subscriber_id' => $user->getId(), 'nls_newsletter_id' => 1 ) )
			);
		$mockWriteDb->expects( $this->once() )
			->method( 'affectedRows' )
			->will( $this->returnValue( 1 ) );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$mainPage = Title::newFromText( "Test content" );
		$newsletter = new Newsletter( 1, 'Test name', 'This is a test description. This is a more test description',
			$mainPage->getArticleID() );
		$result = $table->addSubscription( $newsletter, array( $user->getId() ) );

		$this->assertEquals( true, $result );
	}

}
