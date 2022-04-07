<?php

use MediaWiki\Extension\Newsletter\Specials\SpecialNewsletters;

/**
 * @covers \MediaWiki\Extension\Newsletter\Specials\SpecialNewsletters
 *
 * @group SpecialPage
 * @group Database
 *
 * @author Addshore
 */
class SpecialNewslettersTest extends SpecialPageTestBase {

	protected function newSpecialPage() {
		return new SpecialNewsletters();
	}

	public function testSpecialPageDoesNotFatal() {
		$user = new TestUser( 'BlooBlaa' );
		$req = new FauxRequest( [ 'filter' => 'subscribed' ] );
		$this->executeSpecialPage( '', $req, null, $user->getUser() );
		$this->assertTrue( true );
	}

}
