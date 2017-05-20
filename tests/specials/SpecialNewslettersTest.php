<?php

/**
 * @covers SpecialNewsletters
 *
 * @group SpecialPage
 * @group Database
 *
 * @author Addshore
 */
class SpecialNewslettersTest extends SpecialPageTestBase{

	protected function newSpecialPage() {
		return new SpecialNewsletters();
	}

	public function testSpecialPageDoesNotFatal() {
		$this->markTestSkipped( 'Unit tests do not support SELF JOIN on temporary unit test 
		tables' );
		$user = new TestUser( 'BlooBlaa' );
		$req = new FauxRequest( [ 'filter' => 'subscribed' ] );
		$this->executeSpecialPage( '', $req, null, $user->getUser() );
		$this->assertTrue( true );
	}
}
