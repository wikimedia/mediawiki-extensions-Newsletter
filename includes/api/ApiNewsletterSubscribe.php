<?php
/**
 * @license GNU GPL v2+
 * @author Glaisher
 */
class ApiNewsletterSubscribe extends ApiBase {

	public function execute() {
		$user = $this->getUser();

		if ( !$user->isLoggedIn() ) {
			$this->dieUsage( 'You must be logged-in to subscribe to newsletters', 'notloggedin' );
		}

		$params = $this->extractRequestParams();
		$newsletter = Newsletter::newFromID( $params['id'] );

		if ( !$newsletter ) {
			$this->dieUsage( 'Newsletter does not exist', 'notfound' );
		}

		switch ( $params['do'] ) {
			case 'subscribe':
				$status = $newsletter->subscribe( $user );
				break;
			case 'unsubscribe':
				$status = $newsletter->unsubscribe( $user );
				break;
		}

		if ( !$status->isGood() ) {
			$this->dieStatus( $status );
		}

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
				ApiBase::PARAM_TYPE => [ 'subscribe', 'unsubscribe' ],
				ApiBase::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return [
			'action=newslettersubscribe&id=1&do=subscribe'
				=> 'apihelp-newslettersubscribe-example-1',
			'action=newslettersubscribe&id=2&do=unsubscribe'
				=> 'apihelp-newslettersubscribe-example-2',
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
