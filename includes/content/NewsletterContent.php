<?php
/**
 * @license GNU GPL v2+
 * @author tonythomas
 */

use MediaWiki\MediaWikiServices;

class NewsletterContent extends JsonContent {
	/** Subpage actions */
	const NEWSLETTER_ANNOUNCE = 'announce';
	const NEWSLETTER_MANAGE = 'manage';
	const NEWSLETTER_SUBSCRIBE = 'subscribe';
	const NEWSLETTER_UNSUBSCRIBE = 'unsubscribe';

	/**
	 * @var string|null
	 */
	private $description;

	/**
	 * @var string|null
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
	 * @return bool
	 */
	public function isValid() {
		$this->decode();

		if ( !is_string( $this->description ) || !is_string( $this->mainPage ) || !is_array( $this->publishers ) ) {
			return false;
		}

		foreach ( $this->publishers as $publisher ) {
			if ( !User::newFromName( $publisher ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Decode the JSON encoded args
	 */
	protected function decode() {
		if ( $this->decoded ) {
			return true;
		}
		$jsonParse = $this->getData();
		$data = $jsonParse->isGood() ? $jsonParse->getValue() : null;

		if ( $data ) {
			$this->description = isset( $data->description ) ? $data->description : null;
			$this->mainPage = isset( $data->mainpage ) ? $data->mainpage : null;
			if ( isset( $data->publishers )  && is_array( $data->publishers ) ) {
				$this->publishers = [];
				foreach ( $data->publishers as $publisher ) {
					if ( !is_string( $publisher ) ) {
						$this->publishers = null;
						break;
					}
					$this->publishers[] = $publisher;
				}
			} else {
				$data->publishers = null;
			}
		}
		$this->decoded = true;
		return true;
	}

	/**
	 * @param array $publishersList
	 * @return Status|UserArrayFromResult
	 */
	protected function getPublishersFromJSONData( $publishersList ) {
		// Ask for confirmation before removing all the publishers
		if ( count( $publishersList ) === 0 ) {
			return Status::newFatal( 'newsletter-manage-no-publishers' );
		}

		$publishers = [];
		/** @var User[] $newPublishers */
		foreach ( $publishersList as $publisherName ) {
			$user = User::newFromName( $publisherName );
			if ( !$user || !$user->getId() ) {
				// Input contains an invalid username
				return Status::newFatal( 'newsletter-manage-invalid-publisher', $publisherName );
			}
			$publishers[] = $user->getId();
		}

		return UserArray::newFromIDs( $publishers );
	}

	public function onSuccess() {
		// No-op: We have already redirected.
	}

	protected function fillParserOutput( Title $title, $revId, ParserOptions $options, $generateHtml, ParserOutput &$output ) {
		if ( $generateHtml ) {
			//Make sure things are decoded at this point
			$this->decode();

			$this->newsletter = Newsletter::newFromName( $title->getText() );
			$user = $options->getUser();

			$newsletterActionButtons = '';

			if ( $user->isLoggedIn() ) {
				// buttons are only shown for logged-in users
				$newsletterActionButtons = $this->getNewsletterActionButtons( $options );
			}

			$mainTitle = Title::newFromText( $this->mainPage );

			$fields = array(
				'name' => array(
					'type' => 'info',
					'label-message' => 'newsletter-view-name',
					'default' => $this->newsletter->getName(),
				),
				'mainpage' => array(
					'type' => 'info',
					'label-message' => 'newsletter-view-mainpage',
					'default' => MediaWikiServices::getInstance()->getLinkRenderer()->makeLink( $mainTitle ),
					'raw' => true,
				),
				'description' => array(
					'type' => 'info',
					'label-message' => 'newsletter-view-description',
					'default' => $this->description,
					'rows' => 6,
					'readonly' => true,
				),
				'publishers' => array(
					'type' => 'info',
					'label' => wfMessage( 'newsletter-view-publishers' )->inLanguage(
						$options->getUserLangObj() )
						->numParams( count( $this->publishers ) )
						->text(),
				),
				'subscribers' => array(
					'type' => 'info',
					'label-message' => 'newsletter-view-subscriber-count',
					'default' => $options->getUserLangObj()->formatNum( $this->newsletter->getSubscriberCount() ),
				),
			);
			if ( count( $this->getPublishersFromJSONData( $this->publishers ) ) > 1 ) {
				// Have this here to avoid calling unneeded functions
				$this->doLinkCacheQuery( $this->getPublishersFromJSONData( $this->publishers ) );
				$fields['publishers']['default'] = $this->buildUserList( $this->getPublishersFromJSONData( $this->publishers ) );
				$fields['publishers']['raw'] = true;
			} else {
				// Show a message if there are no publishers instead of nothing
				$fields['publishers']['default'] = wfMessage( 'newsletter-view-no-publishers' )
					->inLanguage( $options->getUserLangObj() )
					->escaped();
			}
			// Show the 10 most recent issues if there have been announcements
			$logs = '';
			$logCount = LogEventsList::showLogExtract(
				$logs, // by reference
				'newsletter',
				SpecialPage::getTitleFor( 'Newsletter', $this->newsletter->getId() ),
				'',
				array(
					'lim' => 10,
					'showIfEmpty' => false,
					'conds' => array( 'log_action' => 'issue-added' ),
					'extraUrlParams' => array( 'subtype' => 'issue-added' ),
				)
			);
			if ( $logCount !== 0 ) {
				$fields['issues'] = array(
					'type' => 'info',
					'raw' => true,
					'default' => $logs,
					'label' => wfMessage( 'newsletter-view-issues-log' )
						->inLanguage( $options->getUserLangObj() )
						->numParams( $logCount )->text(),
				);
			}
			$form = $this->getHTMLForm(
				$fields,
				function() {
					return false;
				} // nothing to submit - the buttons on this page are just links
			);

			$form->suppressDefaultSubmit();
			$form->prepareForm();

			if ( $options->getUser()->isLoggedIn() ) {
				$output->setText( $this->getNavigationLinks( $options ) . $newsletterActionButtons .
					"<br><br>" . $form->getBody() );
			} else {
				$output->setText( $this->getNavigationLinks( $options ) . $form->getBody() );
			}
			return $output;
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
	 * @return string HTML for the button group
	 */
	protected function getNewsletterActionButtons( ParserOptions &$options ) {
		global $wgOut;

		$user = $options->getUser();
		$id = $this->newsletter->getId();
		$buttons = array();
		$wgOut->enableOOUI();

		if ( $this->newsletter->canManage( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $wgOut->msg( 'newsletter-manage-button' )->escaped(),
					'icon' => 'settings',
					'href' => Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() )->getEditURL(),

				)
			);
		}
		if ( $this->newsletter->isPublisher( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $wgOut->msg( 'newsletter-announce-button' )->escaped(),
					'icon' => 'comment',
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id. '/' .
						self::NEWSLETTER_ANNOUNCE )->getFullURL()
				)
			);
		}
		if ( $this->newsletter->isSubscribed( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $wgOut->msg( 'newsletter-unsubscribe-button' )->escaped(),
					'flags' => array( 'destructive' ),
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id. '/' .
						self::NEWSLETTER_UNSUBSCRIBE )->getFullURL()

				)
			);
		} else {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $wgOut->msg( 'newsletter-subscribe-button' )->escaped(),
					'flags' => array( 'constructive' ),
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id. '/' .
						self::NEWSLETTER_SUBSCRIBE )->getFullURL()

				)
			);
		}
		$widget = new OOUI\ButtonGroupWidget( array( 'items' =>  $buttons ) );
		return $widget->toString();
	}

	/**
	 * Batch query to determine whether user pages and user talk pages exist
	 * or not and add them to LinkCache
	 *
	 * @param Iterator $users
	 *
	 * @return string
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
				array(),
				Linker::userLink( $user->getId(), $user->getName() ) .
				Linker::userToolLinks( $user->getId(), $user->getName() )
			);
		}
		return Html::rawElement( 'ul', array(), $str );
	}

	protected function getNavigationLinks( ParserOptions $options ) {
		global $wgOut;
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$listLink = $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'Newsletters' ),
			wfMessage( 'backlinksubtitle',
				wfMessage( 'newsletter-subtitlelinks-list' )->text()
			)->text()
		);

		$user = $options->getUser();
		$actions = array();
		if ( $user->isLoggedIn() ) {
			$actions[] = $this->newsletter->isSubscribed( $user ) ? self::NEWSLETTER_UNSUBSCRIBE : self::NEWSLETTER_SUBSCRIBE;

			if ( $this->newsletter->isPublisher( $user ) ) {
				$actions[] = self::NEWSLETTER_ANNOUNCE;
			}
			if ( $this->newsletter->canManage( $user ) ) {
				$actions[] = self::NEWSLETTER_MANAGE;
			}

			$links = array();
			foreach ( $actions as $action ) {
				$title = SpecialPage::getTitleFor( 'Newsletter', $this->newsletter->getId() . '/' . $action );

				// Messages used here: 'newsletter-subtitlelinks-announce',
				// 'newsletter-subtitlelinks-subscribe', 'newsletter-subtitlelinks-unsubscribe'
				$msg = wfMessage( 'newsletter-subtitlelinks-' . $action )->text();
				$link = $linkRenderer->makeKnownLink( $title, $msg );

				if ( $action == self::NEWSLETTER_MANAGE ) {
					$title = Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() );
					$msg = wfMessage( 'newsletter-subtitlelinks-' . $action )->text();
					$link = $linkRenderer->makeKnownLink( $title, $msg, [], ['action'=>'edit'] );
				}
				$links[] = $link;
			}



			$newsletterLinks = Linker::makeSelfLinkObj(
				SpecialPage::getTitleFor( 'Newsletter', $this->newsletter->getId() ), $this->getEscapedName()
			) . ' ' . wfMessage( 'parentheses' )->rawParams( $options->getUserLangObj()->pipeList( $links ) )->escaped();
		} else {
			$newsletterLinks = Linker::makeSelfLinkObj(
				SpecialPage::getTitleFor( 'Newsletter', $this->newsletter->getId() ), $this->getEscapedName()
			);
		}

		return $wgOut->setSubtitle( $options->getUserLangObj()->pipeList( array( $listLink, $newsletterLinks ) ) );
	}

	/**
	 * @param WikiPage $page
	 * @param ParserOutput|null $parserOutput
	 * @return LinksDeletionUpdate[]
	 */
	public function getDeletionUpdates( WikiPage $page, ParserOutput $parserOutput = null ) {
		return array_merge(
			parent::getDeletionUpdates( $page, $parserOutput ),
			array( new NewsletterDeletionUpdate( $page->getTitle()->getText() ) )
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
	 * @return string
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
	 * @param int $maxLength
	 * @return string
	 */
	public function getTextForSummary( $maxLength = 250 ) {
		global $wgContLang;

		$truncatedtext = $wgContLang->truncate(
			preg_replace( "/[\n\r]/", ' ',  $this->getDescription() )
			, max( 0, $maxLength )
		);

		return $truncatedtext;
	}
}