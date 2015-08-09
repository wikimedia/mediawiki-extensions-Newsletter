<?php

class ApiNewsletter extends ApiBase {
	public function execute() {
		$dbw = wfGetDB( DB_MASTER );
		if ( $this->getMain()->getVal( 'todo' ) === 'subscribe' ) {
			$rowData = array(
				'newsletter_id' => $this->getMain()->getVal( 'newsletterId' ),
				'subscriber_id' => $this->getUser()->getId()
			);
			$dbw->insert( 'nl_subscriptions', $rowData, __METHOD__ );
		}

		if ( $this->getMain()->getVal( 'todo' ) === 'unsubscribe' ) {
			$rowData = array(
				'newsletter_id' => $this->getMain()->getVal( 'newsletterId' ),
				'subscriber_id' => $this->getUser()->getId(),
			);
			$dbw->delete( 'nl_subscriptions', $rowData, __METHOD__ );
		}
	}

	public function getAllowedParams() {
		return array_merge( parent::getAllowedParams(), array(
			'newsletterId' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'todo' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			)
		) );
	}
}