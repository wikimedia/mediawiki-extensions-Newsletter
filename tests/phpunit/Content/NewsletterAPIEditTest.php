<?php

use MediaWiki\Extension\Newsletter\Newsletter;
use MediaWiki\Extension\Newsletter\NewsletterStore;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extension\Newsletter\Content\NewsletterDataUpdate
 */
class NewsletterAPIEditTest extends ApiTestCase {
	private const DESCRIPTION = "A description that is at least 30 characters long";

	public function setUp(): void {
		parent::setUp();
		// Make sure the context user is set to a named user account, otherwise
		// ::createList will fail when temp accounts are enabled, because
		// that generates a log entry which requires a named or temp account actor
		RequestContext::getMain()->setUser( $this->getTestUser()->getUser() );
	}

	public function testCreation() {
		$newsletterTitle = "Newsletter:Test";
		$mainPage = $this->getExistingTestPage( 'UTPage' );
		$text = '{
			"description": "' . self::DESCRIPTION . '",
			"mainpage": "UTPage",
			"publishers": [
				"UTSysop"
			]
		}';

		# Create the newsletter
		$this->doApiRequestWithToken(
			[
				'action' => 'edit',
				'title' => $newsletterTitle,
				'text' => $text,
			]
		);

		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $newsletterTitle ) );
		$content = $page->getContent();
		$newsletter = NewsletterStore::getDefaultInstance()->getNewsletterFromName( "Test" );
		$this->assertNotNull( $newsletter );

		# Check description
		$this->assertEquals( self::DESCRIPTION, $newsletter->getDescription() );
		$this->assertEquals( self::DESCRIPTION, $content->getDescription() );

		# Check main page
		$expectedPageId = $mainPage->getId();
		$this->assertEquals( $expectedPageId, $newsletter->getPageId() );
		$this->assertEquals( $content->getMainPage(), $mainPage->getTitle()->getText() );

		# Check publishers and subsrcibers
		$expectedUsers = [ User::newFromname( "UTSysop" )->getId() ];
		$this->assertEquals( $expectedUsers, $newsletter->getPublishers() );
		$this->assertEquals( $expectedUsers, $newsletter->getSubscribers() );
	}

	private function createNewsletter() {
		$mainPageId = Title::newFromText( "UTPage" )->getArticleId();
		$newsletter = new Newsletter( 0, 'Test', self::DESCRIPTION, $mainPageId );
		NewsletterStore::getDefaultInstance()->addNewsletter( $newsletter );

		$newsletter = Newsletter::newFromName( "Test" );
		$this->assertNotNull( $newsletter );
		return $newsletter;
	}

	public function testUpdateDescription() {
		$this->createNewsletter();
		$newDescription = "A description that is still at least 30 characters long";

		$this->getExistingTestPage( 'UTPage' );
		# Modify the description
		$newText = "{
			\"description\": \"$newDescription\",
			\"mainpage\": \"UTPage\",
			\"publishers\": [
				\"UTSysop\"
			]
		}";
		$this->doApiRequestWithToken(
			[
				'action' => 'edit',
				'title' => "Newsletter:Test",
				'text' => $newText,
			]
		);

		# Check the description
		$newsletter = Newsletter::newFromName( "Test" );
		$this->assertEquals( $newsletter->getDescription(), $newDescription );
	}

	public function testUpdateMainPage() {
		# Set up
		$this->createNewsletter();
		$newMainPage = "SecondPage";
		$newMainPageId = $this->insertPage( $newMainPage )["id"];

		# Modify the main page
		$newText = '{
			"description":"' . self::DESCRIPTION . '",
			"mainpage": "SecondPage",
			"publishers": [
				"UTSysop"
			]
		}';
		$this->doApiRequestWithToken(
			[
				'action' => 'edit',
				'title' => "Newsletter:Test",
				'text' => $newText,
			]
		);

		# Check the main page
		$newsletter = Newsletter::newFromName( "Test" );
		$this->assertEquals( $newsletter->getPageId(), $newMainPageId );
	}

	public function testAddPublisher() {
		$newsletter = $this->createNewsletter();

		# Newsletter should initially have no publishers and no subscribers
		$this->assertEquals( [], $newsletter->getPublishers() );
		$this->assertEquals( [], $newsletter->getSubscribers() );

		$firstUser = User::newFromName( 'UTSysop' );
		if ( !$firstUser->isRegistered() ) {
			$firstUser->addToDatabase();
		}
		$secondUser = User::newFromName( 'Second User' );
		$secondUser->addToDatabase();

		$this->getExistingTestPage( 'UTPage' );
		# Modify the publishers
		$newText = '{
			"description": "' . self::DESCRIPTION . '",
			"mainpage": "UTPage",
			"publishers": [
				"UTSysop",
				"Second User"
			]
		}';
		$this->doApiRequestWithToken(
			[
				'action' => 'edit',
				'title' => "Newsletter:Test",
				'text' => $newText,
			]
		);

		# Check that user was correctly added
		$expectedUsers = [
			$firstUser->getId(),
			$secondUser->getId()
		];
		$newsletter = Newsletter::newFromName( "Test" );
		$this->assertEquals( $expectedUsers, $newsletter->getPublishers() );
		$this->assertEquals( $expectedUsers, $newsletter->getSubscribers() );
	}

	public function testRemovePublisher() {
		# Set up
		$newsletter = $this->createNewsletter();
		$firstUser = User::newFromName( 'UTSysop' );
		$secondUser = User::newFromName( 'Second User' );
		$secondUser->addToDatabase();
		$publisherIds = [ $firstUser->getId(), $secondUser->getId() ];
		NewsletterStore::getDefaultInstance()->addPublisher( $newsletter, $publisherIds );

		$this->getExistingTestPage( 'UTPage' );
		# Modify the publishers
		$newText = '{
			"description": "' . self::DESCRIPTION . '",
			"mainpage": "UTPage",
			"publishers": [
			]
		}';
		$this->doApiRequestWithToken(
			[
				'action' => 'edit',
				'title' => "Newsletter:Test",
				'text' => $newText,
			]
		);

		# Check that users were correctly removed
		$newsletter = Newsletter::newFromName( "Test" );
		$this->assertEquals( [], $newsletter->getPublishers() );
	}
}
