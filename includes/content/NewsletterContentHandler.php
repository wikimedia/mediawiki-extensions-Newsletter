<?php

/**
 * @license GNU GPL v2+
 * @author tonythomas
 */
class NewsletterContentHandler extends JsonContentHandler {

	/**
	 * @param string $modelId
	 */
	public function __construct( $modelId = 'NewsletterContent' ) {
		parent::__construct( $modelId );
	}

	/**
	 * @return NewsletterContent
	 */
	public function makeEmptyContent() {
		return new NewsletterContent( '{"description":"","mainpage":"","publishers":[]}' );
	}

	/**
	 * @param string $text
	 * @param string $format
	 * @return NewsletterContent
	 * @throws MWContentSerializationException
	 */
	public function unserializeContent( $text, $format = null ) {
		$this->checkFormat( $format );
		$content = new NewsletterContent( $text );
		if ( !$content->isValid() ) {
			throw new MWContentSerializationException( 'The Newsletter content is invalid.' );
		}
		return $content;
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return 'NewsletterContent';
	}

	/**
	 * @return bool
	 */
	public function isParserCacheSupported() {
		return false;
	}

	/**
	 * @param Title $title
	 * @param string $description
	 * @param string $mainPage
	 * @param array $publishers
	 * @param string $summary
	 * @param IContextSource $context
	 * @return Status
	 */
	public static function edit( Title $title, $description, $mainPage, $publishers, $summary,
		IContextSource $context
	) {
		$jsonText = FormatJson::encode(
			[ 'description' => $description, 'mainpage' => $mainPage, 'publishers' => $publishers ]
		);
		if ( $jsonText === null ) {
			return Status::newFatal( 'newsletter-ch-tojsonerror' );
		}

		// Ensure that a valid context is provided to the API in unit tests
		$der = new DerivativeContext( $context );
		$request = new DerivativeRequest(
			$context->getRequest(),
			[
				'action' => 'edit',
				'title' => $title->getFullText(),
				'contentmodel' => 'NewsletterContent',
				'text' => $jsonText,
				'summary' => $summary,
				'token' => $context->getUser()->getEditToken(),
			],
			true // Treat data as POSTed
		);
		$der->setRequest( $request );

		try {
			$api = new ApiMain( $der, true );
			$api->execute();
		} catch ( UsageException $e ) {
			return Status::newFatal( $context->msg( 'newsletter-ch-apierror',
				$e->getCodeString() ) );
		}
		return Status::newGood();
	}

	protected function getDiffEngineClass() {
		return 'NewsletterDiffEngine';
	}

}
