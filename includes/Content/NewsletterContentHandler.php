<?php

namespace MediaWiki\Extension\Newsletter\Content;

use Iterator;
use LogEventsList;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Content\Content;
use MediaWiki\Content\JsonContentHandler;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferrableUpdate;
use MediaWiki\Extension\Newsletter\Newsletter;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Json\FormatJson;
use MediaWiki\Linker\Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Revision\SlotRenderingProvider;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\UserArray;
use MediaWiki\User\UserArrayFromResult;
use MWContentSerializationException;
use OOUI\ButtonGroupWidget;
use OOUI\ButtonWidget;

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
		return NewsletterContent::class;
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
		ParserOutput &$parserOutput
	) {
		'@phan-var NewsletterContent $content';
		$title = Title::castFromPageReference( $cpoParams->getPage() );
		$parserOptions = $cpoParams->getParserOptions();
		$generateHtml = $cpoParams->getGenerateHtml();

		$parserOutput->addModuleStyles( [ 'ext.newsletter.newsletter.styles' ] );

		if ( $generateHtml ) {
			$text = $title->getText();
			$newsletter = Newsletter::newFromName( $text );

			$newsletterActionButtons = !$newsletter
				? ''
				: $this->getNewsletterActionButtons( $newsletter, $parserOptions, $parserOutput );
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
				$logCount = LogEventsList::showLogExtract(
					$logs,
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
					// nothing to submit - the buttons on this page are just links
					return false;
				}
			);

			$form->suppressDefaultSubmit();
			$form->prepareForm();

			if ( !$newsletter ) {
				$parserOutput->setText( $form->getBody() );
			} else {
				$this->setupNavigationLinks( $newsletter, $parserOptions );
				$parserOutput->setText( $newsletterActionButtons . "<br><br>" . $form->getBody() );
			}

		} else {
			$parserOutput->setText( '' );
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

		// FIXME It would be better if this editing directly, instead of
		// invoking the api.
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
			true
		);
		$der->setRequest( $request );

		$status = Status::newGood();
		try {
			$api = new ApiMain( $der, true );
			$api->execute();
			$res = $api->getResult()->getResultData();
			if (
				!isset( $res['edit']['result'] )
				|| $res['edit']['result'] !== 'Success'
			) {
				if ( isset( $res['edit']['message'] ) ) {
					$status->fatal(
						$context->msg(
							$res['edit']['message']['key'],
							$res['edit']['message']['params']
						)
					);
				} else {
					$status->fatal( $context->msg(
						'newsletter-ch-apierror',
						$res['edit']['code'] ?? ''
					) );
				}
			}
		} catch ( ApiUsageException $e ) {
			return Status::wrap( $e->getStatusValue() );
		}
		return $status;
	}

	/** @inheritDoc */
	public function getSlotDiffRendererWithOptions( IContextSource $context, $options = [] ) {
		return new NewsletterSlotDiffRenderer(
			$this->createTextSlotDiffRenderer( $options ),
			$context
		);
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
	 * @param ParserOutput $parserOutput
	 * @return string HTML for the button group
	 */
	private function getNewsletterActionButtons(
		Newsletter $newsletter,
		ParserOptions &$options,
		ParserOutput $parserOutput
	) {
		// We are building the 'Subscribe' action button for anonymous users as well
		$user = $options->getUserIdentity();
		$id = $newsletter->getId();
		$buttons = [];

		OutputPage::setupOOUI();
		$parserOutput->setEnableOOUI( true );
		$parserOutput->addModuleStyles( [ 'oojs-ui.styles.icons-interactions' ] );

		if ( !$newsletter->isSubscribed( $user ) ) {
			$buttons[] = new ButtonWidget(
				[
					'label' => wfMessage( 'newsletter-subscribe-button' )->text(),
					'flags' => [ 'progressive' ],
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id . '/' .
						self::NEWSLETTER_SUBSCRIBE )->getFullURL()

				]
			);
		} else {
			$buttons[] = new ButtonWidget(
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
			$buttons[] = new ButtonWidget(
				[
					'label' => wfMessage( 'newsletter-manage-button' )->text(),
					'icon' => 'settings',
					'href' => Title::makeTitleSafe( NS_NEWSLETTER, $newsletter->getName() )->getEditURL(),

				]
			);
			$buttons[] = new ButtonWidget(
				[
					'label' => wfMessage( 'newsletter-subscribers-button' )->text(),
					'icon' => 'info',
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id . '/' .
						self::NEWSLETTER_SUBSCRIBERS )->getFullURL()

				]
			);
		}
		if ( $newsletter->isPublisher( $user ) ) {
			$buttons[] = new ButtonWidget(
				[
					'label' => wfMessage( 'newsletter-announce-button' )->text(),
					'icon' => 'speechBubble',
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id . '/' .
						self::NEWSLETTER_ANNOUNCE )->getFullURL()
				]
			);
		}

		$widget = new ButtonGroupWidget( [ 'items' => $buttons ] );
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
	private function getHTMLForm( array $fields, $submit ) {
		$form = HTMLForm::factory(
			'ooui',
			$fields,
			RequestContext::getMain()
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
		$batch = MediaWikiServices::getInstance()->getLinkBatchFactory()->newLinkBatch();
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

	/**
	 * @param Newsletter $newsletter
	 * @param ParserOptions $options
	 */
	private function setupNavigationLinks( Newsletter $newsletter, ParserOptions $options ) {
		$context = RequestContext::getMain();
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$listLink = $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'Newsletters' ),
			$context->msg( 'backlinksubtitle',
				$context->msg( 'newsletter-subtitlelinks-list' )->text()
			)->text()
		);

		$newsletterLink = Linker::makeSelfLinkObj(
			SpecialPage::getTitleFor( 'Newsletter', (string)$newsletter->getId() ),
			htmlspecialchars( $newsletter->getName() )
		);

		$context->getOutput()->setSubtitle(
			$options->getUserLangObj()->pipeList( [ $listLink, $newsletterLink ] )
		);
	}
}
