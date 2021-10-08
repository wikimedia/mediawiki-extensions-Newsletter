<?php

use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRenderingProvider;

/**
 * @license GPL-2.0-or-later
 * @author tonythomas
 */
class NewsletterContentHandler extends JsonContentHandler {

	/** Subpage actions */
	private const NEWSLETTER_ANNOUNCE = 'announce';
	private const NEWSLETTER_SUBSCRIBE = 'subscribe';
	private const NEWSLETTER_UNSUBSCRIBE = 'unsubscribe';
	private const NEWSLETTER_SUBSCRIBERS = 'subscribers';

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
	 * @inheritDoc
	 */
	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$output
	) {
		'@phan-var NewsletterContent $content';
		$title = Title::castFromPageReference( $cpoParams->getPage() );
		$parserOptions = $cpoParams->getParserOptions();
		$generateHtml = $cpoParams->getGenerateHtml();

		$output->addModuleStyles( 'ext.newsletter.newsletter.styles' );

		if ( $generateHtml ) {
			$text = $title->getText();
			$newsletter = Newsletter::newFromName( $text );

			$newsletterActionButtons = !$newsletter
				? ''
				: $this->getNewsletterActionButtons( $newsletter, $parserOptions, $output );
			$mainTitle = $content->getMainPage();

			$fields = [
				'description' => [
					'type' => 'info',
					'label-message' => 'newsletter-view-description',
					'default' => $content->getDescription(),
					'cssclass' => 'newsletter-headered-element',
					'rows' => 6,
					'readonly' => true,
				],
				'mainpage' => [
					'type' => 'info',
					'label-message' => 'newsletter-view-mainpage',
					'default' => MediaWikiServices::getInstance()->getLinkRenderer()->makeLink( $mainTitle ),
					'cssclass' => 'newsletter-headered-element',
					'raw' => true,
				],
				'publishers' => [
					'type' => 'info',
					'label' => wfMessage( 'newsletter-view-publishers' )->inLanguage(
						$parserOptions->getUserLangObj() )
						->numParams( count( $content->getPublishers() ) )
						->text(),
					'cssclass' => 'newsletter-headered-element',
				],
				'subscribers' => [
					'type' => 'info',
					'label-message' => 'newsletter-view-subscriber-count',
					'default' => !$newsletter ? 0 : $parserOptions->getUserLangObj()->formatNum(
						$newsletter->getSubscribersCount() ),
					'cssclass' => 'newsletter-headered-element',
				],
			];
			$publishersArray = $this->getPublishersFromJSONData( $content->getPublishers() );
			if ( $publishersArray && count( $publishersArray ) > 0 ) {
				// Have this here to avoid calling unneeded functions
				$this->doLinkCacheQuery( $publishersArray );
				$fields['publishers']['default'] = $this->buildUserList( $publishersArray );
				$fields['publishers']['raw'] = true;
			} else {
				// Show a message if there are no publishers instead of nothing
				$fields['publishers']['default'] = wfMessage( 'newsletter-view-no-publishers' )
					->inLanguage( $parserOptions->getUserLangObj() )
					->escaped();
			}
			if ( $newsletter ) {
				// Show the 10 most recent issues if there have been announcements
				$logs = '';
				$logCount = LogEventsList::showLogExtract( $logs, // by reference
					'newsletter',
					SpecialPage::getTitleFor( 'Newsletter', (string)$newsletter->getId() ), '',
					[
						'lim' => 10,
						'showIfEmpty' => false,
						'conds' => [ 'log_action' => 'issue-added' ],
						'extraUrlParams' => [ 'subtype' => 'issue-added' ],
					]
				);
				if ( $logCount !== 0 ) {
					$fields['issues'] = [
						'type' => 'info',
						'raw' => true,
						'default' => $logs,
						'label' => wfMessage( 'newsletter-view-issues-log' )
							->inLanguage( $parserOptions->getUserLangObj() )
							->numParams( $logCount )
							->text(),
						'cssclass' => 'newsletter-headered-element',
					];
				}
			}
			$form = $this->getHTMLForm(
				$fields,
				static function () {
					return false;
				} // nothing to submit - the buttons on this page are just links
			);

			$form->suppressDefaultSubmit();
			$form->prepareForm();

			if ( !$newsletter ) {
				$output->setText( $form->getBody() );
			} else {
				$this->setupNavigationLinks( $newsletter, $parserOptions );
				$output->setText( $newsletterActionButtons . "<br><br>" . $form->getBody() );
			}

		} else {
			$output->setText( '' );
		}
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

	/**
	 * @param array $publishersList
	 * @return bool|UserArrayFromResult
	 */
	private function getPublishersFromJSONData( $publishersList ) {
		if ( count( $publishersList ) === 0 ) {
			return false;
		}

		return UserArray::newFromNames( $publishersList );
	}

	/**
	 * Build a group of buttons: Manage, Subscribe|Unsubscribe
	 * Buttons will be showed to the user only if they are relevant to the current user.
	 *
	 * @param Newsletter $newsletter
	 * @param ParserOptions &$options
	 * @param ParserOutput $output
	 * @return string HTML for the button group
	 */
	private function getNewsletterActionButtons(
		Newsletter $newsletter,
		ParserOptions &$options,
		ParserOutput $output
	) {
		// We are building the 'Subscribe' action button for anonymous users as well
		$user = $options->getUserIdentity();
		$id = $newsletter->getId();
		$buttons = [];

		OutputPage::setupOOUI();
		$output->setEnableOOUI( true );
		$output->addModuleStyles( [ 'oojs-ui.styles.icons-interactions' ] );

		if ( !$newsletter->isSubscribed( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				[
					'label' => wfMessage( 'newsletter-subscribe-button' )->text(),
					'flags' => [ 'progressive' ],
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id . '/' .
						self::NEWSLETTER_SUBSCRIBE )->getFullURL()

				]
			);
		} else {
			$buttons[] = new OOUI\ButtonWidget(
				[
					'label' => wfMessage( 'newsletter-unsubscribe-button' )->text(),
					'flags' => [ 'destructive' ],
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id . '/' .
						self::NEWSLETTER_UNSUBSCRIBE )->getFullURL()

				]
			);
		}
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		if ( $newsletter->canManage( $userFactory->newFromUserIdentity( $user ) ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				[
					'label' => wfMessage( 'newsletter-manage-button' )->text(),
					'icon' => 'settings',
					'href' => Title::makeTitleSafe( NS_NEWSLETTER, $newsletter->getName() )->getEditURL(),

				]
			);
			$buttons[] = new OOUI\ButtonWidget(
				[
					'label' => wfMessage( 'newsletter-subscribers-button' )->text(),
					'icon' => 'info',
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id . '/' .
						self::NEWSLETTER_SUBSCRIBERS )->getFullURL()

				]
			);
		}
		if ( $newsletter->isPublisher( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				[
					'label' => wfMessage( 'newsletter-announce-button' )->text(),
					'icon' => 'speechBubble',
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id . '/' .
						self::NEWSLETTER_ANNOUNCE )->getFullURL()
				]
			);
		}

		$widget = new OOUI\ButtonGroupWidget( [ 'items' => $buttons ] );
		return $widget->toString();
	}

	/**
	 * Create a common HTMLForm which can be used by specific page actions
	 *
	 * @param array $fields array of form fields
	 * @param callback $submit submit callback
	 *
	 * @return HTMLForm
	 */
	private function getHTMLForm( array $fields, /* callable */ $submit ) {
		global $wgOut;
		$form = HTMLForm::factory(
			'ooui',
			$fields,
			$wgOut->getContext()
		);
		$form->setSubmitCallback( $submit );
		return $form;
	}

	/**
	 * Batch query to determine whether user pages and user talk pages exist
	 * or not and add them to LinkCache
	 *
	 * @param Iterator $users
	 */
	private function doLinkCacheQuery( Iterator $users ) {
		$batch = new LinkBatch();
		foreach ( $users as $user ) {
			$batch->addObj( $user->getUserPage() );
			$batch->addObj( $user->getTalkPage() );
		}
		$batch->execute();
	}

	/**
	 * Get a list of users with user-related links next to each username
	 *
	 * @param Iterator $users
	 *
	 * @return string
	 */
	private function buildUserList( Iterator $users ) {
		$str = '';
		foreach ( $users as $user ) {
			$str .= Html::rawElement(
				'li',
				[],
				Linker::userLink( $user->getId(), $user->getName() ) .
				Linker::userToolLinks( $user->getId(), $user->getName() )
			);
		}
		return Html::rawElement( 'ul', [], $str );
	}

	private function setupNavigationLinks( Newsletter $newsletter, ParserOptions $options ) {
		global $wgOut;
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$listLink = $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'Newsletters' ),
			wfMessage( 'backlinksubtitle',
				wfMessage( 'newsletter-subtitlelinks-list' )->text()
			)->text()
		);

		$newsletterLink = Linker::makeSelfLinkObj(
			SpecialPage::getTitleFor( 'Newsletter', (string)$newsletter->getId() ),
			htmlspecialchars( $newsletter->getName() )
		);

		$wgOut->setSubtitle(
			$options->getUserLangObj()->pipeList( [ $listLink, $newsletterLink ] )
		);
	}
}
