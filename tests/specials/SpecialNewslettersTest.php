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
		$user = new TestUser( 'BlooBlaa' );
		$this->executeSpecialPage( '', null, null, $user->getUser() );
		$this->assertTrue( true );
	}
}
