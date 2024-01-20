<?php

use MediaWiki\Extension\Newsletter\Newsletter;
use MediaWiki\Extension\Newsletter\NewsletterStore;
use MediaWiki\Extension\Newsletter\Specials\SpecialNewsletterCreate;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\Newsletter\Specials\SpecialNewsletterCreate
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

	public function testCreateNewsletterMinimumDescriptionValidation() {
		$input = [
			'name' => 'Test Newsletter',
			'description' => 'Test description',
			'mainpage' => Title::newFromText( 'TestPage' )->getBaseText()
		];

		// Mock the submission of this text
		$res = $this->newSpecialPage()->onSubmit( $input );

		// The description is too small
		$this->assertEquals(
			'newsletter-create-short-description-error', $res->getMessage()->getKey()
		);
	}

	public function testCreateNewsletterMainPageExists() {
		$input = [
			'name' => 'Test Newsletter',
			'description' => 'This newsletter has a nonexistent main page',
			'mainpage' => Title::newFromText( 'BdaMianPage' )->getBaseText()
		];

		// Mock submission of bad main page
		$res = $this->newSpecialPage()->onSubmit( $input );

		// The main page is nonexistent
		$this->assertEquals(
			'newsletter-mainpage-non-existent', $res->getMessage()->getKey()
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
		$this->assertEquals( 'newsletter-mainpage-in-use', $res->getMessage()->getKey() );

		// The newsletter was not created
		$this->assertNull(
			$store->getNewsletterFromName( 'Second Newsletter' )
		);
	}

	public function testCreateNewsletterNameUnique() {
		// Create 1st newsletter that will have a duplicated name
		$newsletterTitle = Title::makeTitleSafe( NS_NEWSLETTER, 'Duplicated Newsletter' );
		$firstMainPage = Title::newFromText( 'Test Page' );
		$store = NewsletterStore::getDefaultInstance();

		$firstNewsletter = new Newsletter( 0,
			$newsletterTitle->getText(),
			'This is a test newsletter that will have its name duplicated',
			$firstMainPage->getArticleID()
		);
		$newsletterCreated = $store->addNewsletter( $firstNewsletter );
		$this->assertTrue( $newsletterCreated );

		// Create 2nd newsletter with a duplicated name
		$secondMainPage = Title::newFromText( 'UTPage' );
		$input = [
			'name' => $newsletterTitle->getText(),
			'description' => 'This newsletter duplicates a name, returning an error',
			'mainpage' => $secondMainPage->getBaseText()
		];
		$res = $this->newSpecialPage()->onSubmit( $input );
		$this->assertEquals( 'newsletter-exist-error', $res->getMessage()->getKey() );

		// The second newsletter was not created
		$this->assertNull(
			$store->getNewsletter( $firstNewsletter->getID() + 1 )
		);
	}
}
