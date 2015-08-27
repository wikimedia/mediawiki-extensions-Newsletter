<?php

class ApiNewsletter extends ApiBase {

	public function execute() {

		$user = $this->getUser();
		if ( !$user->isLoggedIn() ) {
			$this->dieUsage( 'You must be logged-in to interact with newsletters', 'notloggedin' );
		}

		$subscriptionsTable = SubscriptionsTable::newFromGlobalState();

		if ( $this->getMain()->getVal( 'todo' ) === 'subscribe' ) {
			$subscriptionsTable->addSubscription(
				$user->getId(),
				$this->getMain()->getVal( 'newsletterId' )
			);
			//TODO if failed to add subscription then tell the user somehow
		} elseif ( $this->getMain()->getVal( 'todo' ) === 'unsubscribe' ) {
			$subscriptionsTable->removeSubscription(
				$user->getId(),
				$this->getMain()->getVal( 'newsletterId' )
			);
			//TODO if failed to remove subscription then tell the user somehow
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
