<?php

/**
 * API to manage newsletters
 *
 * @license GNU GPL v2+
 * @author Tina Johnson
 *
 * @todo Rename this module to newslettermanage
 * @todo Add i18n
 */
class ApiNewsletterManage extends ApiBase {

	public function execute() {
		$user = $this->getUser();

		$params = $this->extractRequestParams();
		$newsletter = Newsletter::newFromID( $params['id'] );

		if ( !$newsletter ) {
			$this->dieUsage( 'Newsletter does not exist', 'notfound' );
		}

		if ( !$newsletter->isPublisher( $user ) && !$user->isAllowed( 'newsletter-manage' ) ) {
			$this->dieUsage( 'You do not have permission to manage newsletters.', 'nopermissions' );
		}

		$publisher = User::newFromId( $params['publisher'] );
		if ( !$publisher || $publisher->getId() === 0 ) {
			$this->dieUsage( 'Publisher is not a registered user.', 'invalidpublisher' );
		}

		$ndb = NewsletterDb::newFromGlobalState();

		switch ( $params['do'] ) {
			case 'addpublisher':
				$status = $ndb->addPublisher( $publisher->getId(), $params['id'] );
				break;
			case 'removepublisher':
				$status = $ndb->removePublisher( $publisher->getId(), $params['id'] );
				break;
		}

		if ( !$status ) {
			$this->dieUsage( 'Manage action failed. Please try again.', 'managefailure' );
		}

		// Success
		$this->getResult()->addValue( null, $this->getModuleName(),
			array(
				'id' => $newsletter->getId(),
				'name' => $newsletter->getName(),
			)
		);
	}

	public function getAllowedParams() {
		return array(
			'id' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true,
			),
			'do' => array(
				ApiBase::PARAM_TYPE => array( 'addpublisher', 'removepublisher' ),
				ApiBase::PARAM_REQUIRED => true,
			),
			'publisher' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
		);
	}

	public function isWriteMode() {
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}

	public function mustBePosted() {
		return true;
	}

}
