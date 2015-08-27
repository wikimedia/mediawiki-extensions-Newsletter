<?php

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
