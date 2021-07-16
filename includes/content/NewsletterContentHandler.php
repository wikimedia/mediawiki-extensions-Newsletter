<?php

use MediaWiki\Revision\SlotRenderingProvider;

/**
 * @license GPL-2.0-or-later
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
	 * @param string|null $format
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
	 * @param Title $title The title of the page to supply the updates for.
	 * @param Content $content The content to generate data updates for.
	 * @param string $role The role (slot) in which the content is being used.
	 * @param SlotRenderingProvider $slotOutput A provider that can be used to gain access to
	 *        a ParserOutput of $content by calling $slotOutput->getSlotParserOutput( $role, false ).
	 * @return DeferrableUpdate[] A list of DeferrableUpdate objects for putting information
	 *        about this content object somewhere.
	 */
	public function getSecondaryDataUpdates(
		Title $title,
		Content $content,
		$role,
		SlotRenderingProvider $slotOutput
	) {
		$user = RequestContext::getMain()->getUser();
		// @todo This user object might not be the right one in some cases.
		// but that should be pretty rare in the context of newsletters.
		/** @var NewsletterContent $content */
		'@phan-var NewsletterContent $content';
		$newsletterUpdate = new NewsletterDataUpdate( $content, $title, $user );
		return array_merge(
			parent::getSecondaryDataUpdates( $title, $content, $role, $slotOutput ),
			[ $newsletterUpdate ]
		);
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
		} catch ( ApiUsageException $e ) {
			return Status::wrap( $e->getStatusValue() );
		}
		return Status::newGood();
	}

	protected function getDiffEngineClass() {
		return 'NewsletterDiffEngine';
	}

}
