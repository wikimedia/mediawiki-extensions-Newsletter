<?php
/**
 * @group API
 * @group Database
 * @group medium
 *
 * @covers NewsletterDataUpdate
 */
class NewsletterAPIEditTest extends ApiTestCase {
	protected function setUp() : void {
		parent::setUp();
		$this->tablesUsed = [ 'nl_newsletters', 'nl_publishers', 'nl_subscriptions' ];
	}

	private const DESCRIPTION = "A description that is at least 30 characters long";

	public function testCreation() {
		$newsletterTitle = "Newsletter:Test";
		$mainPage = "UTPage";
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

		$page = new WikiPage( Title::newFromText( $newsletterTitle ) );
		$content = $page->getContent();
		$newsletter = NewsletterStore::getDefaultInstance()->getNewsletterFromName( "Test" );
		$this->assertNotNull( $newsletter );

		# Check description
		$this->assertEquals( $newsletter->getDescription(), self::DESCRIPTION );
		$this->assertEquals( $content->getDescription(), self::DESCRIPTION );

		# Check main page
		$expectedPageId = Title::newFromText( $mainPage )->getArticleId();
		$this->assertEquals( $newsletter->getPageId(), $expectedPageId );
		$this->assertEquals( $content->getMainPage(), $mainPage );

		# Check publishers and subsrcibers
		$expectedUsers = [ User::newFromname( "UTSysop" )->getId() ];
		$this->assertEquals( $newsletter->getPublishers(), $expectedUsers );
		$this->assertEquals( $newsletter->getSubscribers(), $expectedUsers );
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
		$this->assertEquals( $newsletter->getPublishers(), [] );
		$this->assertEquals( $newsletter->getSubscribers(), [] );

		$firstUser = User::newFromName( 'UTSysop' );
		$secondUser = User::newFromName( 'Second User' );
		$secondUser->addToDatabase();

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
		$this->assertEquals( $newsletter->getPublishers(), $expectedUsers );
		$this->assertEquals( $newsletter->getSubscribers(), $expectedUsers );
	}

	public function testRemovePublisher() {
		# Set up
		$newsletter = $this->createNewsletter();
		$firstUser = User::newFromName( 'UTSysop' );
		$secondUser = User::newFromName( 'Second User' );
		$secondUser->addToDatabase();
		$publisherIds = [ $firstUser->getId(), $secondUser->getId() ];
		NewsletterStore::getDefaultInstance()->addPublisher( $newsletter, $publisherIds );

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
		$this->assertEquals( $newsletter->getPublishers(), [] );
	}
}
