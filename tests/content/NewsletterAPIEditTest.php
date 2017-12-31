<?php
/**
 * @group API
 * @group Database
 * @group medium
 *
 * @covers NewsletterDataUpdate
 */
class NewsletterAPIEditTest extends ApiTestCase {
	protected function setUp() {
		parent::setUp();
		$this->doLogin();
	}
	public function testCreation() {
		$description = "A description that is at least 30 characters long";
		$newsletterTitle = "Newsletter:Test";
		$mainPage = "UTPage";
		$text = "{
			\"description\": \"$description\",
			\"mainpage\": \"$mainPage\",
			\"publishers\": [
				\"UTSysop\"
			]
		}";

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
		$this->assertEquals( $newsletter->getDescription(), $description );
		$this->assertEquals( $content->getDescription(), $description );

		# Check main page
		$expectedPageId = Title::newFromText( $mainPage )->getArticleId();
		$this->assertEquals( $newsletter->getPageId(), $expectedPageId );
		$this->assertEquals( $content->getMainPage(), $mainPage );

		# Check publishers and subsrcibers
		$expectedUsers = [ User::newFromname( "UTSysop" )->getId() ];
		$this->assertEquals( $newsletter->getPublishers(), $expectedUsers );
		$this->assertEquals( $newsletter->getSubscribers(), $expectedUsers );
	}

	public function testUpdateDescription() {
		# Set up by creating newsletter
		$initialDescription = "A description that is at least 30 characters long";
		$finalDescription = "A description that is still at least 30 characters long";
		$mainPage = 'UTPage';
		$mainPageId = Title::newFromText( $mainPage )->getArticleId();
		$newsletter = new Newsletter( 0, 'Test', $initialDescription, $mainPageId );
		NewsletterStore::getDefaultInstance()->addNewsletter( $newsletter );

		# Modify the description
		$newText = "{
			\"description\": \"$finalDescription\",
			\"mainpage\": \"$mainPage\",
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
		$this->assertEquals( $newsletter->getDescription(), $finalDescription );
	}

	public function testUpdateMainPage() {
		# Set up by creating newsletter
		$description = "A description that is at least 30 characters long";
		$oldMainPage = 'UTPage';
		$oldMainPageId = Title::newFromText( $oldMainPage )->getArticleId();
		$newsletter = new Newsletter( 0, 'Test', $description, $oldMainPageId );
		$newMainPage = "SecondPage";
		$newMainPageId = $this->insertPage( $newMainPage )["id"];
		NewsletterStore::getDefaultInstance()->addNewsletter( $newsletter );

		$newsletter = Newsletter::newFromName( "Test" );
		$this->assertNotNull( $newsletter );

		# Modify the main page
		$newText = "{
			\"description\": \"$description\",
			\"mainpage\": \"$newMainPage\",
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

		# Check the main page
		$newsletter = Newsletter::newFromName( "Test" );
		$this->assertEquals( $newsletter->getPageId(), $newMainPageId );
	}
}
