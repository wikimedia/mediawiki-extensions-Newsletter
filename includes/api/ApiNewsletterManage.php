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

		$params = $this->extractRequestParams();
		$newsletter = Newsletter::newFromID( $params['id'] );

		if ( !$newsletter ) {
			$this->dieWithError( 'newsletter-api-error-notfound', 'notfound' );
		}

		if ( !$newsletter->canManage( $user ) ) {
			$this->dieWithError( 'newsletter-api-error-nopermissions', 'nopermissions' );
		}

		$publisher = User::newFromId( $params['publisher'] );
		if ( !$publisher || $publisher->getId() === 0 ) {
			$this->dieWithError( 'newsletter-api-error-invalidpublisher-registered', 'invalidpublisher' );
		}

		$store = NewsletterStore::getDefaultInstance();

		$success = false;
		$action = $params['do'];
		if ( $action === 'addpublisher' ) {
			$success = $store->addPublisher( $newsletter, $publisher );
		} elseif ( $action === 'removepublisher' ) {
			$success = $store->removePublisher( $newsletter, $publisher );
		}

		if ( !$success ) {
			$this->dieWithError(
				new Message( 'newsletter-api-error-managefailure', [ $action ] ),
					'managefailure'
			);
		}

		// Success
		$this->getResult()->addValue( null, $this->getModuleName(),
			[
				'id' => $newsletter->getId(),
				'name' => $newsletter->getName(),
			]
		);
	}

	public function getAllowedParams() {
		return [
			'id' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true,
			],
			'do' => [
				ApiBase::PARAM_TYPE => [ 'addpublisher', 'removepublisher' ],
				ApiBase::PARAM_REQUIRED => true,
			],
			'publisher' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=newslettermanage&id=1&do=addpublisher&publisher=3'
				=> 'apihelp-newslettermanage-example-1',
			'action=newslettermanage&id=2&do=removepublisher&publisher=5'
				=> 'apihelp-newslettermanage-example-2',
		];
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
