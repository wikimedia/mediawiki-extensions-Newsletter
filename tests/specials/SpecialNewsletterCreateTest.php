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
}
