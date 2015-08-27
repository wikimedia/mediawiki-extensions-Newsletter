<?php

class SpecialNewsletterManageTest extends SpecialPageTestBase{

	protected function newSpecialPage() {
		return new SpecialNewsletterManage();
	}

	public function testSpecialPageDoesNotFatal() {
		$user = new TestUser( 'BlooBlaa' );
		$this->executeSpecialPage( '', null, null, $user->getUser() );
		$this->assertTrue( true );
	}
}
