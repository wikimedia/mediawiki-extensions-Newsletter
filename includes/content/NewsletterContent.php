<?php

use MediaWiki\MediaWikiServices;

/**
 * @license GPL-2.0-or-later
 * @author tonythomas
 */
class NewsletterContent extends JsonContent {

	/** Subpage actions */
	private const NEWSLETTER_ANNOUNCE = 'announce';
	private const NEWSLETTER_SUBSCRIBE = 'subscribe';
	private const NEWSLETTER_UNSUBSCRIBE = 'unsubscribe';
	private const NEWSLETTER_SUBSCRIBERS = 'subscribers';

	/**
	 * @var string|null
	 */
	private $description;

	/**
	 * @var Title
	 */
	private $mainPage;

	/**
	 * @var Newsletter|null
	 */
	private $newsletter;

	/**
	 * @var array|null
	 */
	protected $publishers;

	/**
	 * Whether $description and $targets have been populated
	 * @var bool
	 */
	private $decoded = false;

	/**
	 * @param string $text
	 */
	public function __construct( $text ) {
		parent::__construct( $text, 'NewsletterContent' );
	}

	/**
	 * Validate username and make sure it exists
	 *
	 * @param string $userName
	 * @return bool
	 */
	private function validateUserName( $userName ) {
		$user = User::newFromName( $userName );
		if ( !$user ) {
			return false;
		}
		// If this user never existed
		if ( !$user->getId() ) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		$this->decode();

		if ( !is_string( $this->description ) || !( $this->mainPage instanceof Title ) ||
			!is_array( $this->publishers )
		) {
			return false;
		}

		foreach ( $this->publishers as $publisher ) {
			if ( !$this->validateUserName( $publisher ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Decode the JSON encoded args
	 * @return bool
	 */
	protected function decode() {
		if ( $this->decoded ) {
			return true;
		}
		$jsonParse = $this->getData();
		$data = $jsonParse->isGood() ? $jsonParse->getValue() : null;

		if ( $data ) {
			$this->description = $data->description ?? null;
			$this->mainPage = !empty( $data->mainpage ) ? Title::newFromText( $data->mainpage ) :
				Title::makeTitle( NS_SPECIAL, 'Badtitle' );
			if ( isset( $data->publishers ) && is_array( $data->publishers ) ) {
				$this->publishers = [];
				foreach ( $data->publishers as $publisher ) {
					if ( !is_string( $publisher ) ) {
						$this->publishers = null;
						break;
					}
					$this->publishers[] = $publisher;
				}
			} else {
				$this->publishers = null;
			}
		}
		$this->decoded = true;
		return true;
	}

	/**
	 * @param array $publishersList
	 * @return bool|UserArrayFromResult
	 */
	protected function getPublishersFromJSONData( $publishersList ) {
		if ( count( $publishersList ) === 0 ) {
			return false;
		}

		return UserArray::newFromNames( $publishersList );
	}

	public function onSuccess() {
		// No-op: We have already redirected.
	}

	protected function fillParserOutput(
		Title $title,
		$revId,
		ParserOptions $options,
		$generateHtml,
		ParserOutput &$output
	) {
		$output->addModuleStyles( 'ext.newsletter.newsletter.styles' );

		if ( $generateHtml ) {
			$this->newsletter = Newsletter::newFromName( $title->getText() );
			// Make sure things are decoded at this point
			$this->decode();

			$newsletterActionButtons = !$this->newsletter ? '' : $this->getNewsletterActionButtons(
				$options, $output );
			$mainTitle = $this->mainPage;

			$fields = [
				'description' => [
					'type' => 'info',
					'label-message' => 'newsletter-view-description',
					'default' => $this->description,
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
						$options->getUserLangObj() )
						->numParams( count( $this->publishers ) )
						->text(),
					'cssclass' => 'newsletter-headered-element',
				],
				'subscribers' => [
					'type' => 'info',
					'label-message' => 'newsletter-view-subscriber-count',
					'default' => !$this->newsletter ? 0 : $options->getUserLangObj()->formatNum(
						$this->newsletter->getSubscribersCount() ),
					'cssclass' => 'newsletter-headered-element',
				],
			];
			$publishersArray = $this->getPublishersFromJSONData( $this->publishers );
			if ( $publishersArray && count( $publishersArray ) > 0 ) {
				// Have this here to avoid calling unneeded functions
				$this->doLinkCacheQuery( $publishersArray );
				$fields['publishers']['default'] = $this->buildUserList( $publishersArray );
				$fields['publishers']['raw'] = true;
			} else {
				// Show a message if there are no publishers instead of nothing
				$fields['publishers']['default'] = wfMessage( 'newsletter-view-no-publishers' )
					->inLanguage( $options->getUserLangObj() )
					->escaped();
			}
			if ( $this->newsletter ) {
				// Show the 10 most recent issues if there have been announcements
				$logs = '';
				$logCount = LogEventsList::showLogExtract( $logs, // by reference
					'newsletter',
					SpecialPage::getTitleFor( 'Newsletter', (string)$this->newsletter->getId() ), '',
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
							->inLanguage( $options->getUserLangObj() )
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

			if ( !$this->newsletter ) {
				$output->setText( $form->getBody() );
			} else {
				$this->setupNavigationLinks( $options );
				$output->setText( $newsletterActionButtons . "<br><br>" . $form->getBody() );
			}

			return $output;
		} else {
			$output->setText( '' );
		}
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
	 * Build a group of buttons: Manage, Subscribe|Unsubscribe
	 * Buttons will be showed to the user only if they are relevant to the current user.
	 *
	 * @param ParserOptions &$options
	 * @param ParserOutput $output
	 * @return string HTML for the button group
	 */
	protected function getNewsletterActionButtons( ParserOptions &$options, ParserOutput $output
	) {
		// We are building the 'Subscribe' action button for anonymous users as well
		$user = $options->getUser() ? : null;
		$id = $this->newsletter->getId();
		$buttons = [];

		OutputPage::setupOOUI();
		$output->setEnableOOUI( true );
		$output->addModuleStyles( [ 'oojs-ui.styles.icons-interactions' ] );

		if ( !$user || !$this->newsletter->isSubscribed( $user ) ) {
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
		if ( $user && $this->newsletter->canManage( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				[
					'label' => wfMessage( 'newsletter-manage-button' )->text(),
					'icon' => 'settings',
					'href' => Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() )->getEditURL(),

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
		if ( $user && $this->newsletter->isPublisher( $user ) ) {
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

	private function setupNavigationLinks( ParserOptions $options ) {
		global $wgOut;
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$listLink = $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'Newsletters' ),
			wfMessage( 'backlinksubtitle',
				wfMessage( 'newsletter-subtitlelinks-list' )->text()
			)->text()
		);

		$newsletterLink = Linker::makeSelfLinkObj(
			SpecialPage::getTitleFor( 'Newsletter', (string)$this->newsletter->getId() ),
			$this->getEscapedName()
		);

		$wgOut->setSubtitle(
			$options->getUserLangObj()->pipeList( [ $listLink, $newsletterLink ] )
		);
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		$this->decode();
		return $this->description;
	}

	/**
	 * @return Title
	 */
	public function getMainPage() {
		$this->decode();
		return $this->mainPage;
	}

	/**
	 * @return array
	 */
	public function getPublishers() {
		$this->decode();
		return $this->publishers;
	}

	/**
	 * We need the escaped newsletter name several times so
	 * extract the method here.
	 *
	 * @return string
	 */
	protected function getEscapedName() {
		return htmlspecialchars( $this->newsletter->getName() );
	}

	/**
	 * Override TextContent::getTextForSummary
	 * @param int $maxLength Maximum length, in characters (not bytes).
	 * @return string
	 */
	public function getTextForSummary( $maxLength = 250 ) {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();

		$truncatedtext = $contLang->truncateForVisual(
			preg_replace( "/[\n\r]/", ' ',  $this->getDescription() ), max( 0, $maxLength )
		);

		return $truncatedtext;
	}

	/**
	 * @param Title $title Title of the page that is being edited.
	 * @param Content|null $old Content object representing the page's content before the edit.
	 * @param bool $recursive bool indicating whether DataUpdates should trigger recursive
	 * updates (relevant mostly for LinksUpdate).
	 * @param ParserOutput|null $parserOutput ParserOutput representing the rendered version of
	 * the page after the edit.
	 * @return DataUpdate[]
	 *
	 * @see Content::getSecondaryDataUpdates()
	 */
	public function getSecondaryDataUpdates(
		Title $title,
		Content $old = null,
		$recursive = true,
		ParserOutput $parserOutput = null
	) {
		$user = RequestContext::getMain()->getUser();
		// @todo This user object might not be the right one in some cases.
		// but that should be pretty rare in the context of newsletters.
		$mwUpdate = new NewsletterDataUpdate( $this, $title, $user );
		return array_merge(
			parent::getSecondaryDataUpdates( $title, $old, $recursive, $parserOutput ),
			[ $mwUpdate ]
		);
	}

}
