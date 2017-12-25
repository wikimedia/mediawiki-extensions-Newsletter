<?php

/**
 * @covers SpecialNewsletterCreate
 *
 * @group SpecialPage
 * @group Database
 *
 * @author Addshore
 */
class SpecialNewsletterCreateTest extends SpecialPageTestBase {

	protected function newSpecialPage() {
		return new SpecialNewsletterCreate();
	}

	public function testSpecialPageDoesNotFatal() {
		$user = new TestUser( __METHOD__, __METHOD__, 'foo@bar.com', [ 'sysop' ] );
		$this->executeSpecialPage( '', null, null, $user->getUser() );
		$this->assertTrue( true );
	}

	public function testCreateNewsletterMainPageExists() {
		$input = [
			'name' => 'Test Newsletter',
			'description' => 'This is a test newsletter that should return an error for a bad main page.',
			'mainpage' => Title::newFromText( 'BdaMianPage' )->getBaseText()
		];

		// Mock submission of bad main page
		$res = $this->newSpecialPage()->onSubmit( $input );

		// The main page is nonexistent
		$this->assertEquals(
			$res->getMessage()->getKey(), 'newsletter-mainpage-non-existent'
		);

		// The newsletter was not created
		$store = NewsletterStore::getDefaultInstance();
		$this->assertNull(
			$store->getNewsletterFromName( 'Test Newsletter' )
		);
	}
	public function testCreateNewsletterMainPageAlreadyUsed() {
		// Create 1st newsletter with conflicting main page
		$mainpage = Title::newFromText( 'UTPage' );
		$firstNewsletterTitle = Title::makeTitleSafe( NS_NEWSLETTER, 'First Newsletter' );
		$store = NewsletterStore::getDefaultInstance();
		$firstNewsletter = new Newsletter( 0,
			$firstNewsletterTitle->getText(),
			'This newsletter uses the main page, preventing a second newsletter from using it',
			$mainpage->getArticleID()
		);
		$newsletterCreated = $store->addNewsletter( $firstNewsletter );
		$this->assertTrue( $newsletterCreated );

		// Creation of 2nd newsletter with same main page has to fail
		$input = [
			'name' => 'Second Newsletter',
			'description' => 'The main page of this newsletter is already in use',
			'mainpage' => $mainpage->getBaseText()
		];
		$res = $this->newSpecialPage()->onSubmit( $input );
		$this->assertEquals( $res->getMessage()->getKey(), 'newsletter-mainpage-in-use' );

		// The newsletter was not created
		$this->assertNull(
			$store->getNewsletterFromName( 'Second Newsletter' )
		);
	}
}
