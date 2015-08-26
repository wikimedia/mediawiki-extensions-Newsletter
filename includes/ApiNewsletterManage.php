<?php

/**
 * API to manage newsletters
 */
class ApiNewsletterManage extends ApiBase {

	public function execute() {
		$dbw = wfGetDB( DB_MASTER );
		if ( $this->getMain()->getVal( 'todo' ) === 'removepublisher' ) {
			$rowData = array(
				'newsletter_id' => $this->getMain()->getVal( 'newsletterId' ),
				'publisher_id' => $this->getMain()->getVal( 'publisher' ),
			);
			$dbw->delete( 'nl_publishers', $rowData, __METHOD__ );
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
