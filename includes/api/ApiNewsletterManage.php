<?php

/**
 * API to manage newsletters
 *
 * @license GNU GPL v2+
 * @author Tina Johnson
 *
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

		if ( !$newsletter->canManage( $user ) ) {
			$this->dieUsage( 'You do not have permission to manage newsletters.', 'nopermissions' );
		}

		$publisher = User::newFromId( $params['publisher'] );
		if ( !$publisher || $publisher->getId() === 0 ) {
			$this->dieUsage( 'Publisher is not a registered user.', 'invalidpublisher' );
		}

		$ndb = NewsletterDb::newFromGlobalState();

		$success =false;
		$action = $params['do'];
		if ( $action === 'addpublisher' ) {
			$success = $ndb->addPublisher( $publisher->getId(), $params['id'] );
		} elseif ( $action === 'removepublisher' ) {
			$success = $ndb->removePublisher( $publisher->getId(), $params['id'] );
		}

		if ( !$success ) {
			$this->dieUsage( "Manage action: $action failed. Please try again.", 'managefailure' );
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

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=newslettermanage&id=1&do=addpublisher&publisher=3'
				=> 'apihelp-newslettermanage-example-1',
			'action=newslettermanage&id=2&do=removepublisher&publisher=5'
				=> 'apihelp-newslettermanage-example-2',
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
