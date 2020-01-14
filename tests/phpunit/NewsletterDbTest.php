<?php

use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\LoadBalancer;

/**
 * @covers NewsletterDb
 *
 * @author Addshore
 */
class NewsletterDbTest extends PHPUnit\Framework\TestCase {

	/**
	 * @return MockObject|IDatabase
	 */
	private function getMockIDatabase() {
		return $this->getMockBuilder( IDatabase::class )->getMock();
	}

	/**
	 * @return MockObject|LoadBalancer
	 */
	private function getMockLoadBalancer( $db ) {
		$mock = $this->getMockBuilder( LoadBalancer::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			->method( 'getConnection' )
			->will( $this->returnValue( $db ) );
		$mock->expects( $this->any() )
			->method( 'getConnectionRef' )
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

		$this->assertTrue( $result );
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
	 * @covers NewsletterDb::getNewsletterSubscribersCount
	 */
	public function testGetSubscribersCount() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();
		$firstUser = User::newFromName( 'TestUser1' );
		$secondUser = User::newFromName( 'TestUser2' );
		$firstUser->addToDatabase();
		$secondUser->addToDatabase();

		$mockWriteDb
			->expects( $this->once() )
			->method( 'insert' )
			->with(
				'nl_subscriptions',
				[
					[
						'nls_subscriber_id' => $firstUser->getId(),
						'nls_newsletter_id' => $newsletter->getId()
					],
					[
						'nls_subscriber_id' => $secondUser->getId(),
						'nls_newsletter_id' => $newsletter->getId()
					]
				]
			);
		$mockWriteDb->expects( $this->once() )
			->method( 'affectedRows' )
			->will( $this->returnValue( 2 ) );
		$mockWriteDb
			->expects( $this->once() )
			->method( 'update' )
			->with(
				'nl_newsletters',
				// For index reasons, count is negative
				[ 'nl_subscriber_count=nl_subscriber_count-2' ],
				[ 'nl_id' => $newsletter->getId() ]
			);
		$mockWriteDb
			->expects( $this->once() )
			->method( 'selectField' )
			->with(
				'nl_newsletters',
				'nl_subscriber_count',
				[ 'nl_id' => $newsletter->getId() ]
			)->will(
				// For index reasons, count is negative
				$this->returnValue( -2 )
			);

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		// Add two subscribers before checking subscribers count
		$result = $table->addSubscription( $this->getTestNewsletter(), [
			$firstUser->getId(),
			$secondUser->getId()
		] );
		$this->assertTrue( $result );

		$result = $table->getNewsletterSubscribersCount( $newsletter->getId() );
		$this->assertEquals( 2, $result );
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
				[ [ 'nlp_newsletter_id' => 1, 'nlp_publisher_id' => $user->getId() ] ]
			);
		$mockWriteDb
			->expects( $this->once() )
			->method( 'affectedRows' )
			->will( $this->returnValue( 1 ) );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->addPublisher( $this->getTestNewsletter(), [ $user->getId() ] );

		$this->assertTrue( $result );
	}

	/**
	 * @covers NewsletterDb::removePublisher
	 */
	public function testRemovePublisher() {
		$mockWriteDb = $this->getMockIDatabase();
		$user = User::newFromName( 'Test User' );
		$user->addtoDatabase();

		$mockWriteDb
			->expects( $this->once() )
			->method( 'delete' )
			->with(
				'nl_publishers',
				[ 'nlp_newsletter_id' => 1, 'nlp_publisher_id' => [ $user->getId() ] ]
			);
		$mockWriteDb
			->expects( $this->once() )
			->method( 'affectedRows' )
			->will( $this->returnValue( 1 ) );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->removePublisher( $this->getTestNewsletter(), [ $user->getId() ] );

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
				[ 'nl_active' => 0 ],
				[ 'nl_id' => $newsletter->getId(), 'nl_active' => 1 ]
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
					[ 'nl_active' => 0 ],
					[ 'nl_id' => $newsletter->getId(), 'nl_active' => 1 ]
				],
				[
					'nl_newsletters',
					[ 'nl_active' => 1 ],
					[ 'nl_name' => $newsletter->getName(), 'nl_active' => 0 ]
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

	/**
	 * @covers NewsletterDb::getNewsletter
	 */
	public function testGetNewsletter() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();

		$mockResWrapper = $this->getMockBuilder( IResultWrapper::class )
			->disableOriginalConstructor()
			->getMock();
		$mockResWrapper->expects( $this->once() )
			->method( 'current' )
			->will( $this->returnValue(
				(object)[
					'nl_id' => $newsletter->getId(),
					'nl_name' => $newsletter->getName(),
					'nl_desc' => $newsletter->getDescription(),
					'nl_main_page_id' => $newsletter->getPageId(),
				]
			) );

		$mockWriteDb
			->expects( $this->once() )
			->method( 'select' )
			->with(
				'nl_newsletters',
				[ 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ],
				[ 'nl_id' => $newsletter->getId(), 'nl_active' => 1 ]
			)
			->will( $this->returnValue( $mockResWrapper ) );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->getNewsletter( $newsletter->getId() );
		$this->assertEquals( $newsletter, $result );
	}

	/**
	 * @covers NewsletterDb::getNewsletterFromName
	 */
	public function testGetNewsletterFromName() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();

		$mockWriteDb
			->expects( $this->once() )
			->method( 'selectRow' )
			->with(
				'nl_newsletters',
				[ 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ],
				[ 'nl_name' => $newsletter->getName(), 'nl_active' => 1 ]
			)
			->will( $this->returnValue(
				(object)[
					'nl_id' => $newsletter->getId(),
					'nl_name' => $newsletter->getName(),
					'nl_desc' => $newsletter->getDescription(),
					'nl_main_page_id' => $newsletter->getPageId(),
				]
			) );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->getNewsletterFromName( $newsletter->getName() );
		$this->assertEquals( $newsletter, $result );
	}

}
