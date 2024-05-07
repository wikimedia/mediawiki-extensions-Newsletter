<?php

use MediaWiki\Extension\Newsletter\Newsletter;
use MediaWiki\Extension\Newsletter\NewsletterDb;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Rdbms\DeleteQueryBuilder;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\InsertQueryBuilder;
use Wikimedia\Rdbms\LoadBalancer;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\UpdateQueryBuilder;

/**
 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb
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
	 * @param IDatabase $db
	 * @return MockObject|LoadBalancer
	 */
	private function getMockLoadBalancer( $db ) {
		$mock = $this->getMockBuilder( LoadBalancer::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			->method( 'getConnection' )
			->willReturn( $db );
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
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::addSubscription
	 */
	public function testAddSubscriber() {
		$mockWriteDb = $this->getMockIDatabase();
		$user = User::newFromName( 'Test User' );
		$user->addToDatabase();

		$iqb = $this->createMock( InsertQueryBuilder::class );
		$iqb->expects( $this->once() )->method( 'insertInto' )->with( 'nl_subscriptions' )->willReturnSelf();
		$iqb->expects( $this->once() )->method( 'ignore' )->willReturnSelf();
		$iqb->expects( $this->once() )->method( 'rows' )
			->with( [ [ 'nls_subscriber_id' => $user->getId(), 'nls_newsletter_id' => 1 ] ] )->willReturnSelf();
		$iqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newInsertQueryBuilder' )
			->willReturn( $iqb );
		$mockWriteDb->expects( $this->once() )
			->method( 'affectedRows' )
			->willReturn( 1 );
		$uqb = $this->createMock( UpdateQueryBuilder::class );
		$uqb->expects( $this->once() )->method( 'update' )
			->with( 'nl_newsletters' )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'set' )
			->with( [ 'nl_subscriber_count=nl_subscriber_count-1' ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'where' )
			->with( [ 'nl_id' => 1 ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newUpdateQueryBuilder' )
			->willReturn( $uqb );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->addSubscription( $this->getTestNewsletter(), [ $user->getId() ] );
		$this->assertNull( $result );
	}

	/**
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::removeSubscription
	 */
	public function testRemoveSubscriber() {
		$mockWriteDb = $this->getMockIDatabase();
		$user = User::newFromName( 'Test User' );
		$user->addToDatabase();

		$dqb = $this->createMock( DeleteQueryBuilder::class );
		$dqb->expects( $this->once() )->method( 'deleteFrom' )->with( 'nl_subscriptions' )->willReturnSelf();
		$dqb->expects( $this->once() )->method( 'where' )
			->with( [ 'nls_subscriber_id' => [ $user->getId() ], 'nls_newsletter_id' => 1 ] )->willReturnSelf();
		$dqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newDeleteQueryBuilder' )
			->willReturn( $dqb );
		$mockWriteDb
			->expects( $this->once() )
			->method( 'affectedRows' )
			->willReturn( 1 );
		$uqb = $this->createMock( UpdateQueryBuilder::class );
		$uqb->expects( $this->once() )->method( 'update' )
			->with( 'nl_newsletters' )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'set' )
			->with( [ 'nl_subscriber_count=nl_subscriber_count+1' ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'where' )
			->with( [ 'nl_id' => 1 ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newUpdateQueryBuilder' )
			->willReturn( $uqb );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->removeSubscription( $this->getTestNewsletter(), [ $user->getId() ] );
		$this->assertNull( $result );
	}

	/**
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::getNewsletterSubscribersCount
	 */
	public function testGetSubscribersCount() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();
		$firstUser = User::newFromName( 'TestUser1' );
		$secondUser = User::newFromName( 'TestUser2' );
		$firstUser->addToDatabase();
		$secondUser->addToDatabase();

		$iqb = $this->createMock( InsertQueryBuilder::class );
		$iqb->expects( $this->once() )->method( 'insertInto' )->with( 'nl_subscriptions' )->willReturnSelf();
		$iqb->expects( $this->once() )->method( 'ignore' )->willReturnSelf();
		$iqb->expects( $this->once() )->method( 'rows' )
			->with( [
				[
					'nls_subscriber_id' => $firstUser->getId(),
					'nls_newsletter_id' => $newsletter->getId()
				],
				[
					'nls_subscriber_id' => $secondUser->getId(),
					'nls_newsletter_id' => $newsletter->getId()
				]
			] )
			->willReturnSelf();
		$iqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newInsertQueryBuilder' )
			->willReturn( $iqb );
		$mockWriteDb
			->expects( $this->once() )
			->method( 'affectedRows' )
			->willReturn( 2 );
		$uqb = $this->createMock( UpdateQueryBuilder::class );
		$uqb->expects( $this->once() )->method( 'update' )
			->with( 'nl_newsletters' )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'set' )
			->with( [ 'nl_subscriber_count=nl_subscriber_count-2' ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'where' )
			->with( [ 'nl_id' => $newsletter->getId() ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newUpdateQueryBuilder' )
			->willReturn( $uqb );
		$sqb = $this->createMock( SelectQueryBuilder::class );
		$sqb->expects( $this->once() )->method( 'select' )
			->with( 'nl_subscriber_count' )->willReturnSelf();
		$sqb->expects( $this->once() )->method( 'from' )
			->with( 'nl_newsletters' )->willReturnSelf();
		$sqb->expects( $this->once() )->method( 'where' )
			->with( [ 'nl_id' => $newsletter->getId() ] )->willReturnSelf();
		$sqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$sqb->expects( $this->once() )->method( 'fetchField' )
			->willReturn(
				// For index reasons, count is negative
				-2
			);
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newSelectQueryBuilder' )
			->willReturn( $sqb );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		// Add two subscribers before checking subscribers count
		$result = $table->addSubscription( $this->getTestNewsletter(), [
			$firstUser->getId(),
			$secondUser->getId()
		] );
		$this->assertNull( $result );

		$result = $table->getNewsletterSubscribersCount( $newsletter->getId() );
		$this->assertEquals( 2, $result );
	}

	/**
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::addPublisher
	 */
	public function testAddPublisher() {
		$mockWriteDb = $this->getMockIDatabase();
		$user = User::newFromName( 'Test User' );
		$user->addToDatabase();

		$iqb = $this->createMock( InsertQueryBuilder::class );
		$iqb->expects( $this->once() )->method( 'insertInto' )->with( 'nl_publishers' )->willReturnSelf();
		$iqb->expects( $this->once() )->method( 'ignore' )->willReturnSelf();
		$iqb->expects( $this->once() )->method( 'rows' )
			->with( [ [ 'nlp_newsletter_id' => 1, 'nlp_publisher_id' => $user->getId() ] ] )->willReturnSelf();
		$iqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newInsertQueryBuilder' )
			->willReturn( $iqb );
		$mockWriteDb
			->expects( $this->once() )
			->method( 'affectedRows' )
			->willReturn( 1 );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->addPublisher( $this->getTestNewsletter(), [ $user->getId() ] );
		$this->assertTrue( $result );
	}

	/**
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::removePublisher
	 */
	public function testRemovePublisher() {
		$mockWriteDb = $this->getMockIDatabase();
		$user = User::newFromName( 'Test User' );
		$user->addtoDatabase();

		$dqb = $this->createMock( DeleteQueryBuilder::class );
		$dqb->expects( $this->once() )->method( 'deleteFrom' )->with( 'nl_publishers' )->willReturnSelf();
		$dqb->expects( $this->once() )->method( 'where' )
			->with( [ 'nlp_newsletter_id' => 1, 'nlp_publisher_id' => [ $user->getId() ] ] )->willReturnSelf();
		$dqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newDeleteQueryBuilder' )
			->willReturn( $dqb );
		$mockWriteDb
			->expects( $this->once() )
			->method( 'affectedRows' )
			->willReturn( 1 );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->removePublisher( $this->getTestNewsletter(), [ $user->getId() ] );
		$this->assertTrue( $result );
	}

	/**
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::addNewsletter
	 */
	public function testAddNewsletter() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();

		$iqb = $this->createMock( InsertQueryBuilder::class );
		$iqb->expects( $this->once() )->method( 'insertInto' )->with( 'nl_newsletters' )->willReturnSelf();
		$iqb->expects( $this->once() )->method( 'row' )
			->with( [
				'nl_name' => $newsletter->getName(),
				'nl_desc' => $newsletter->getDescription(),
				'nl_main_page_id' => $newsletter->getPageId()
			] )
			->willReturnSelf();
		$iqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newInsertQueryBuilder' )
			->willReturn( $iqb );
		$mockWriteDb
			->expects( $this->once() )
			->method( 'insertId' )
			->willReturn( 1 );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->addNewsletter( $newsletter );

		$this->assertSame( 1, $result );
	}

	/**
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::updateName
	 */
	public function testUpdateName() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();
		$newsletterId = $newsletter->getId();

		$newName = 'Foobar name';

		$uqb = $this->createMock( UpdateQueryBuilder::class );
		$uqb->expects( $this->once() )->method( 'update' )
			->with( 'nl_newsletters' )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'set' )
			->with( [ 'nl_name' => $newName ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'where' )
			->with( [ 'nl_id' => $newsletterId ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newUpdateQueryBuilder' )
			->willReturn( $uqb );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );
		$table->updateName( $newsletterId, $newName );
	}

	/**
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::updateDescription
	 */
	public function testUpdateDescription() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();
		$newsletterId = $newsletter->getId();

		$newDescription = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit,'
				. 'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';

		$uqb = $this->createMock( UpdateQueryBuilder::class );
		$uqb->expects( $this->once() )->method( 'update' )
			->with( 'nl_newsletters' )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'set' )
			->with( [ 'nl_desc' => $newDescription ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'where' )
			->with( [ 'nl_id' => $newsletterId ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newUpdateQueryBuilder' )
			->willReturn( $uqb );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );
		$table->updateDescription( $newsletterId, $newDescription );
	}

	/**
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::updateMainPage
	 */
	public function testUpdateMainPage() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();
		$newsletterId = $newsletter->getId();

		$mainpage = Title::newFromText( 'UTPage' );
		$newMainPage = $mainpage->getArticleID();

		$uqb = $this->createMock( UpdateQueryBuilder::class );
		$uqb->expects( $this->once() )->method( 'update' )
			->with( 'nl_newsletters' )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'set' )
			->with( [ 'nl_main_page_id' => $newMainPage ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'where' )
			->with( [ 'nl_id' => $newsletterId ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newUpdateQueryBuilder' )
			->willReturn( $uqb );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->updateMainPage( $newsletterId, $newMainPage );
		$this->assertTrue( $result );
	}

	/**
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::deleteNewsletter
	 */
	public function testDeleteNewsletter() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();

		$uqb = $this->createMock( UpdateQueryBuilder::class );
		$uqb->expects( $this->once() )->method( 'update' )
			->with( 'nl_newsletters' )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'set' )
			->with( [ 'nl_active' => 0 ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'where' )
			->with( [ 'nl_id' => $newsletter->getId(), 'nl_active' => 1 ] )->willReturnSelf();
		$uqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newUpdateQueryBuilder' )
			->willReturn( $uqb );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->deleteNewsletter( $newsletter );
		$this->assertNull( $result );
	}

	/**
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::restoreNewsletter
	 */
	public function testRestoreNewsletter() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();

		$expectedUpdateArgs = [
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
		];
		$mockWriteDb
			->expects( $this->exactly( 2 ) )
			->method( 'newUpdateQueryBuilder' )
			->willReturnCallback( function () use ( &$expectedUpdateArgs ) {
				[ $table, $set, $conds ] = array_shift( $expectedUpdateArgs );
				$uqb = $this->createMock( UpdateQueryBuilder::class );
				$uqb->expects( $this->once() )->method( 'update' )
					->with( $table )->willReturnSelf();
				$uqb->expects( $this->once() )->method( 'set' )
					->with( $set )->willReturnSelf();
				$uqb->expects( $this->once() )->method( 'where' )
					->with( $conds )->willReturnSelf();
				$uqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
				return $uqb;
			} );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->deleteNewsletter( $newsletter );
		$this->assertNull( $result );

		$result = $table->restoreNewsletter( $newsletter->getName() );
		$this->assertNull( $result );
	}

	/**
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::getNewsletter
	 */
	public function testGetNewsletter() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();

		$sqb = $this->createMock( SelectQueryBuilder::class );
		$sqb->expects( $this->once() )->method( 'select' )
			->with( [ 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ] )->willReturnSelf();
		$sqb->expects( $this->once() )->method( 'from' )
			->with( 'nl_newsletters' )->willReturnSelf();
		$sqb->expects( $this->once() )->method( 'where' )
			->with( [ 'nl_id' => $newsletter->getId(), 'nl_active' => 1 ] )->willReturnSelf();
		$sqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$sqb->expects( $this->once() )->method( 'fetchRow' )
			->willReturn(
				(object)[
					'nl_id' => $newsletter->getId(),
					'nl_name' => $newsletter->getName(),
					'nl_desc' => $newsletter->getDescription(),
					'nl_main_page_id' => $newsletter->getPageId(),
				]
			);
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newSelectQueryBuilder' )
			->willReturn( $sqb );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->getNewsletter( $newsletter->getId() );
		$this->assertEquals( $newsletter, $result );
	}

	/**
	 * @covers \MediaWiki\Extension\Newsletter\NewsletterDb::getNewsletterFromName
	 */
	public function testGetNewsletterFromName() {
		$mockWriteDb = $this->getMockIDatabase();
		$newsletter = $this->getTestNewsletter();

		$sqb = $this->createMock( SelectQueryBuilder::class );
		$sqb->expects( $this->once() )->method( 'select' )
			->with( [ 'nl_id', 'nl_name', 'nl_desc', 'nl_main_page_id' ] )->willReturnSelf();
		$sqb->expects( $this->once() )->method( 'from' )
			->with( 'nl_newsletters' )->willReturnSelf();
		$sqb->expects( $this->once() )->method( 'where' )
			->with( [ 'nl_name' => $newsletter->getName(), 'nl_active' => 1 ] )->willReturnSelf();
		$sqb->expects( $this->once() )->method( 'caller' )->willReturnSelf();
		$sqb->expects( $this->once() )->method( 'fetchRow' )
			->willReturn(
				(object)[
					'nl_id' => $newsletter->getId(),
					'nl_name' => $newsletter->getName(),
					'nl_desc' => $newsletter->getDescription(),
					'nl_main_page_id' => $newsletter->getPageId(),
				]
			);
		$mockWriteDb
			->expects( $this->once() )
			->method( 'newSelectQueryBuilder' )
			->willReturn( $sqb );

		$table = new NewsletterDb( $this->getMockLoadBalancer( $mockWriteDb ) );

		$result = $table->getNewsletterFromName( $newsletter->getName() );
		$this->assertEquals( $newsletter, $result );
	}

}
