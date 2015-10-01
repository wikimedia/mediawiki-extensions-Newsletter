<?php

/**
 * API to manage newsletters
 *
 * @license GNU GPL v2+
 * @author Tina Johnson
 */
class ApiNewsletterManage extends ApiBase {

	public function execute() {

		$user = $this->getUser();
		if ( !$user->isLoggedIn() ) {
			$this->dieUsage( 'You must be logged-in to interact with newsletters', 'notloggedin' );
		}

		if ( !$user->isAllowed( 'newsletter-addpublisher' ) ) {
			$this->dieUsage( 'You do not have the necessary rights to interact with the newsletter', 'notnewsletteradmin' );
		}

		//TODO should probably do something here depending on the result..
		if ( $this->getMain()->getVal( 'todo' ) === 'removepublisher' ) {
			$db = NewsletterDb::newFromGlobalState();
			$db->removePublisher(
				$this->getMain()->getVal( 'publisher' ),
				$this->getMain()->getVal( 'newsletterId' )
			);
		}
	}

	public function getAllowedParams() {
		return array_merge(
			parent::getAllowedParams(),
			array(
				'newsletterId' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true,
				),
				'todo' => array(
					ApiBase::PARAM_TYPE => array( 'removepublisher' ),
					ApiBase::PARAM_REQUIRED => true,
				),
				'publisher' => array(
					ApiBase::PARAM_TYPE => 'string',
					ApiBase::PARAM_REQUIRED => true,
				),
			)
		);
	}

	public function needsToken() {
		return 'csrf';
	}

	public function mustBePosted() {
		return true;
	}

}
