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
}
