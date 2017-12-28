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

	/**
	 * @return Newsletter
	 */
	private function getTestNewsletter() {
		$mainPage = Title::newFromText( 'Test content' );

		return new Newsletter(
			1,
			'Test name',
			'Test description',
			$mainPage->getArticleID()
		);
	}

	/**
	 * @covers NewsletterDb::addSubscription
	 */
	public function testAddSubscriber() {
		$mockWriteDb = $this->getMockIDatabase();
		$user = User::newFromName( 'Test User' );
		$user->addToDatabase();

		$mockWriteDb
			->expects( $this->once() )
			->method( 'insert' )
			->with(
				'nl_subscriptions',
				[ [ 'nls_subscriber_id' => $user->getId(), 'nls_newsletter_id' => 1 ] ]
			);
		$mockWriteDb->expects( $this->once() )
			->method( 'affectedRows' )
			->will( $this->returnValue( 1 ) );
		$mockWriteDb
			->expects( $this->once() )
			->method( 'update' )
			->with(
				'nl_newsletters',
				// For index reasons, count is negative
				[ 'nl_subscriber_count=nl_subscriber_count-1' ], [ 'nl_id' => 1 ]
			);

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->addSubscription( $this->getTestNewsletter(), [ $user->getId() ] );

		$this->assertEquals( true, $result );
	}

	/**
	 * @covers NewsletterDb::removeSubscription
	 */
	public function testRemoveSubscriber() {
		$mockWriteDb = $this->getMockIDatabase();
		$user = User::newFromName( 'Test User' );
		$user->addToDatabase();

		$mockWriteDb
			->expects( $this->once() )
			->method( 'delete' )
			->with(
				'nl_subscriptions',
				[ 'nls_subscriber_id' => [ $user->getId() ], 'nls_newsletter_id' => 1 ]
			);
		$mockWriteDb
			->expects( $this->once() )
			->method( 'affectedRows' )
			->will( $this->returnValue( 1 ) );
		$mockWriteDb
			->expects( $this->once() )
			->method( 'update' )
			->with(
				'nl_newsletters',
				// For index reasons, count is negative
				[ 'nl_subscriber_count=nl_subscriber_count+1' ], [ 'nl_id' => 1 ]
			);

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->removeSubscription( $this->getTestNewsletter(), [ $user->getId() ] );

		$this->assertTrue( $result );
	}

	/**
	 * @covers NewsletterDb::addPublisher
	 */
	public function testAddPublisher() {
		$mockWriteDb = $this->getMockIDatabase();
		$user = User::newFromName( 'Test User' );
		$user->addToDatabase();

		$mockWriteDb
			->expects( $this->once() )
			->method( 'insert' )
			->with(
				'nl_publishers',
				[ 'nlp_newsletter_id' => 1, 'nlp_publisher_id' => $user->getId() ]
			);
		$mockWriteDb
			->expects( $this->once() )
			->method( 'affectedRows' )
			->will( $this->returnValue( 1 ) );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->addPublisher( $this->getTestNewsletter(), $user );

		$this->assertTrue( $result );
	}

	/**
	 * @covers NewsletterDb::addNewsletter
	 */
	public function testAddNewsletter() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();

		$mockWriteDb
			->expects( $this->once() )
			->method( 'insert' )
			->with(
				'nl_newsletters',
				[
					'nl_name' => $newsletter->getName(),
					'nl_desc' => $newsletter->getDescription(),
					'nl_main_page_id' => $newsletter->getPageId()
				]
			)
			->will( $this->returnValue( true ) );
		$mockWriteDb
			->expects( $this->once() )
			->method( 'insertId' )
			->will( $this->returnValue( 1 ) );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->addNewsletter( $newsletter );

		$this->assertEquals( 1, $result );
	}

	/**
	 * @covers NewsletterDb::updateName
	 */
	public function testUpdateName() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();
		$newsletterId = $newsletter->getId();

		$newName = 'Foobar name';

		$mockWriteDb
			->expects( $this->once() )
			->method( 'update' )
			->with(
				'nl_newsletters',
				[ 'nl_name' => $newName ], [ 'nl_id' => $newsletterId ]
			);

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );
		$table->updateName( $newsletterId, $newName );
	}

	/**
	 * @covers NewsletterDb::updateDescription
	 */
	public function testUpdateDescription() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();
		$newsletterId = $newsletter->getId();

		$newDescription = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit,'
				. 'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';

		$mockWriteDb
			->expects( $this->once() )
			->method( 'update' )
			->with(
				'nl_newsletters',
				[ 'nl_desc' => $newDescription ], [ 'nl_id' => $newsletterId ]
			);

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );
		$table->updateDescription( $newsletterId, $newDescription );
	}

	/**
	 * @covers NewsletterDb::updateMainPage
	 */
	public function testUpdateMainPage() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();
		$newsletterId = $newsletter->getId();

		$mainpage = Title::newFromText( 'UTPage' );
		$newMainPage = $mainpage->getArticleID();

		$mockWriteDb
			->expects( $this->once() )
			->method( 'update' )
			->will( $this->returnValue( true ) )
			->with(
				'nl_newsletters',
				[ 'nl_main_page_id' => $newMainPage ], [ 'nl_id' => $newsletterId ]
		);

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->updateMainPage( $newsletterId, $newMainPage );
		$this->assertTrue( $result );
	}

	/**
	 * @covers NewsletterDb::deleteNewsletter
	 */
	public function testDeleteNewsletter() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();

		$mockWriteDb
			->expects( $this->once() )
			->method( 'update' )
			->with(
				'nl_newsletters',
				[ 'nl_active' => 0 ], [ 'nl_id' => $newsletter->getId() ]
			);
		$mockWriteDb
			->expects( $this->once() )
			->method( 'affectedRows' )
			->will( $this->returnValue( 1 ) );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->deleteNewsletter( $newsletter );

		$this->assertTrue( $result );
	}

	/**
	 * @covers NewsletterDb::restoreNewsletter
	 */
	public function testRestoreNewsletter() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();

		$mockWriteDb
			->expects( $this->exactly( 2 ) )
			->method( 'update' )
			->withConsecutive(
				[
					'nl_newsletters',
					[ 'nl_active' => 0 ], [ 'nl_id' => $newsletter->getId() ]
				],
				[
					'nl_newsletters',
					[ 'nl_active' => 1 ], [ 'nl_name' => $newsletter->getName() ]
				]
			);
		$mockWriteDb
			->expects( $this->exactly( 2 ) )
			->method( 'affectedRows' )
			->will( $this->returnValue( 1 ) );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->deleteNewsletter( $newsletter );
		$this->assertTrue( $result );

		$result = $table->restoreNewsletter( $newsletter->getName() );
		$this->assertTrue( $result );
	}

}
