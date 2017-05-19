<?php
/**
 * Special page to handle actions related to specific newsletters
 *
 * @author Glaisher
 * @license GNU GPL v2+
 */
class SpecialNewsletter extends SpecialPage {

	/** Subpage actions */
	const NEWSLETTER_MANAGE = 'manage';
	const NEWSLETTER_ANNOUNCE = 'announce';
	const NEWSLETTER_SUBSCRIBE = 'subscribe';
	const NEWSLETTER_UNSUBSCRIBE = 'unsubscribe';
	const NEWSLETTER_SUBSCRIBERS = 'subscribers';

	/**
	 * @var Newsletter|null
	 */
	protected $newsletter;

	public function __construct() {
		parent::__construct( 'Newsletter' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @param string|null $par subpage parameter
	 */
	public function execute( $par ) {

		if ( $par == '' ) {
			// If no subpage was specified - only [[Special:Newsletter]] - redirect to Special:Newsletters
			$this->getOutput()->redirect(
					SpecialPage::getTitleFor( 'Newsletters' )->getFullURL(),
					'303'
			);
			return;
		}

		$this->setHeaders();

		// Separate out newsletter id and action from subpage
		$params = explode( '/', $par );
		$params[1] = isset( $params[1] ) ? $params[1] : null;
		list( $id, $action ) = $params;

		$out = $this->getOutput();
		$this->newsletter = Newsletter::newFromID( (int)$id );

		if ( $this->newsletter ) {
			// Newsletter exists for the given subpage id - let's check what they want to do
			switch ( $action ) {
				case self::NEWSLETTER_SUBSCRIBE:
				case self::NEWSLETTER_UNSUBSCRIBE:
					$this->doSubscribeExecute();
					break;
				case self::NEWSLETTER_ANNOUNCE:
					$this->doAnnounceExecute();
					break;
				case self::NEWSLETTER_SUBSCRIBERS:
					$this->doSubscribersExecute();
					break;
				default:
					$this->getOutput()->redirect(
						Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() )->getFullURL() );
					return;
			}

			$out->addSubtitle( $this->getNavigationLinks( $action ) );

		} else {
			// Show an error message (with delete log entry) if we couldn't find a newsletter
			$out->showErrorPage( 'newsletter-notfound', 'newsletter-not-found-id' );
			$num = LogEventsList::showLogExtract(
				$out,
				'newsletter',
				$this->getPageTitle( (int)$id ),
				'',
				[
					'showIfEmpty' => false,
					'conds' => [ 'log_action' => 'newsletter-removed' ],
					'msgKey' => 'newsletter-deleted-log'
				]
			);
		}

	}

	/**
	 * Get the navigation links shown in the subtitle
	 *
	 * @param string|null $current subpage currently being shown, null if default "view" page
	 */
	protected function getNavigationLinks( $current ) {
		$linkRenderer = $this->getLinkRenderer();
		$listLink = $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'Newsletters' ),
			$this->msg( 'backlinksubtitle',
				$this->msg( 'newsletter-subtitlelinks-list' )->text()
			)->text()
		);
		if ( $current === null ) {
			// We've the fancy buttons on the default "view" page so don't
			// add redundant navigation links and fast return here
			return $listLink;
		}

		// Build the links taking the current user's access levels into account
		$user = $this->getUser();
		$actions = [];
		if ( $user->isLoggedIn() ) {
			$actions[] = $this->newsletter->isSubscribed( $user )
				? self::NEWSLETTER_UNSUBSCRIBE
				: self::NEWSLETTER_SUBSCRIBE;
		}
		if ( $this->newsletter->isPublisher( $user ) ) {
			$actions[] = self::NEWSLETTER_ANNOUNCE;
		}
		if ( $this->newsletter->canManage( $user ) ) {
			$actions[] = self::NEWSLETTER_MANAGE;
			$actions[] = self::NEWSLETTER_SUBSCRIBERS;
		}

		$links = [];
		foreach ( $actions as $action ) {
			$title = $this->getPageTitle( $this->newsletter->getId() . '/' . $action );
			// Messages used here: 'newsletter-subtitlelinks-announce',
			// 'newsletter-subtitlelinks-subscribe', 'newsletter-subtitlelinks-unsubscribe'
			// 'newsletter-subtitlelinks-manage'
			$msg = $this->msg( 'newsletter-subtitlelinks-' . $action )->text();
			$link = $linkRenderer->makeKnownLink( $title, $msg );
			if ( $action == self::NEWSLETTER_MANAGE ) {
				$title = Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() );
				$msg = $this->msg( 'newsletter-subtitlelinks-' . $action )->text();
				$link = $linkRenderer->makeKnownLink( $title, $msg, [], [ 'action'=>'edit' ] );
			}
			if ( $current === $action ) {
				$links[] = Linker::makeSelfLinkObj( $title, htmlspecialchars( $msg ) );
			} else {

				$links[] = $link;
			}
		}

		$newsletterLinks = $linkRenderer->makeKnownLink(
			Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() ),
			$this->getName()
		) . ' ' . $this->msg( 'parentheses' )
			->rawParams( $this->getLanguage()->pipeList( $links ) )
			->text();

		return $this->getLanguage()->pipeList( [ $listLink, $newsletterLinks ] );
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
		$form = HTMLForm::factory(
			'ooui',
			$fields,
			$this->getContext()
		);
		$form->setSubmitCallback( $submit );

		return $form;
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
	 * Build the (un)subscribe form for Special:Newsletter/$id/(un)subscribe
	 * The actual form showed will be switched depending on whether the current
	 * user is subscribed or not.
	 */
	protected function doSubscribeExecute() {
		// IPs shouldn't be able to subscribe to newsletters
		$this->requireLogin( 'newsletter-subscribe-loginrequired' );
		$this->checkReadOnly();
		$this->getOutput()->setPageTitle( $this->msg( 'newsletter-subscribe' ) );

		if ( $this->newsletter->isSubscribed( $this->getUser() ) ) {
			// User is subscribed so show the unsubscribe form
			$txt = $this->msg( 'newsletter-unsubscribe-text' )
				->rawParams( $this->getEscapedName() )->parse();
			$button = [
				'unsubscribe' => [
					'type' => 'submit',
					'name' => 'unsubscribe',
					'default' => $this->msg( 'newsletter-do-unsubscribe' )->text(),
					'id' => 'mw-newsletter-unsubscribe',
					'flags' => [ 'primary', 'destructive' ],
				]
			];
		} else {
			// Show the subscribe form if the user is not subscribed currently
			$txt = $this->msg( 'newsletter-subscribe-text' )
				->rawParams( $this->getEscapedName() )->parse();
			$button = [
				'subscribe' => [
					'type' => 'submit',
					'name' => 'subscribe',
					'default' => $this->msg( 'newsletter-do-subscribe' )->text(),
					'id' => 'mw-newsletter-subscribe',
					'flags' => [ 'primary', 'constructive' ],
				]
			];
		}

		$form = $this->getHTMLForm( $button, [ $this, 'submitSubscribeForm' ] );
		$form->addHeaderText( $txt );
		$form->suppressDefaultSubmit();
		$form->show();
		$this->getOutput()->addReturnTo( Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() ) );
	}

	/**
	 * Submit callback for subscribe form.
	 * @throws Exception
	 * @return Status
	 */
	public function submitSubscribeForm() {
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( $request->getCheck( 'subscribe' ) ) {
			$status = $this->newsletter->subscribe( $user );
			$action = 'subscribe';
		} elseif ( $request->getCheck( 'unsubscribe' ) ) {
			$status = $this->newsletter->unsubscribe( $user );
			$action = 'unsubscribe';
		} else {
			throw new Exception( 'POST data corrupted or required parameter missing from request' );
		}

		if ( $status->isGood() ) {
			// @todo We could probably do this in a better way
			// Add the success message if the action was successful
			// Messages used: 'newsletter-subscribe-success', 'newsletter-unsubscribe-success'
			$this->getOutput()->addHTML(
				$this->msg( "newsletter-$action-success" )
					->rawParams( $this->getEscapedName() )->parse()
			);
		}

		return $status;
	}

	/**
	 * Build the announce form for Special:Newsletter/$id/announce. This does
	 * permissions and read-only check as well and handles showing error and
	 * success pages.
	 *
	 * @throws UserBlockedError
	 */
	protected function doAnnounceExecute() {
		$user = $this->getUser();
		$out = $this->getOutput();

		// Echo handles read-only mode on their own but we'll now let the user know
		// that wiki is currently in read-only mode and stop from here.
		$this->checkReadOnly();

		if ( $user->isBlocked() ) {
			// Blocked users should just stay blocked.
			throw new UserBlockedError( $user->getBlock() );
		}

		if ( !$this->newsletter->isPublisher( $user ) ) {
			$out->showPermissionsErrorPage(
				[ [ 'newsletter-announce-nopermission' ] ]
			);
			return;
		}

		$out->setPageTitle(
			$this->msg( 'newsletter-announce' )
				->rawParams( $this->getEscapedName() )
		);

		$fields = [
			'issuepage' => [
				'type' => 'title',
				'name' => 'issuepage',
				'creatable' => true,
				'required' => true,
				'label-message' => 'newsletter-announce-issuetitle',
				'default' => '',
			],
			'summary' => [
				// @todo add a help message explaining what this does
				'type' => 'text',
				'name' => 'summary',
				'label-message' => 'newsletter-announce-summary',
				'maxlength' => '160',
				'autofocus' => true,
			],
		];

		$form = $this->getHTMLForm(
			$fields,
			[ $this, 'submitAnnounceForm' ]
		);
		$form->setSubmitTextMsg( 'newsletter-announce-submit' );

		$status = $form->show();
		if ( $status === true ) {
			// Success!
			$out->addHTML(
				$this->msg( 'newsletter-announce-success' )
					->rawParams( $this->getEscapedName() )
					->numParams( $this->newsletter->getSubscriberCount() )
					->parseAsBlock()
			);
			$out->addReturnTo( Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() ) );
		}
	}

	/**
	 * Submit callback for the announce form (validate, add to issues table and create
	 * Echo event). This assumes that permissions check etc has been done already.
	 *
	 * @param array $data
	 *
	 * @return Status|bool true on success, Status fatal otherwise
	 * @throws Exception if Echo is not installed
	 */
	public function submitAnnounceForm( array $data ) {
		$title = Title::newFromText( $data['issuepage'] );

		// Do some basic validation on the issue page
		if ( !$title ) {
			return Status::newFatal( 'newsletter-announce-invalid-page' );
		}

		if ( !$title->exists() ) {
			return Status::newFatal( 'newsletter-announce-nonexistent-page' );
		}

		if ( $title->inNamespace( NS_FILE ) ) {
			// Eh..
			return Status::newFatal( 'newsletter-announce-invalid-page' );
		}

		// Validate summary
		$reasonSpamMatch = EditPage::matchSummarySpamRegex( $data['summary'] );
		if ( $reasonSpamMatch ) {
			return Status::newFatal( 'spamprotectionmatch', $reasonSpamMatch );
		}

		if ( !class_exists( 'EchoEvent' ) ) {
			throw new Exception( 'Echo extension is not installed.' );
		}

		$user = $this->getUser();
		if ( $user->pingLimiter( 'newsletter-announce' ) ) {
			// Prevent people from spamming
			throw new ThrottledError;
		}

		$summary = trim( $data['summary'] );

		// Everything seems okay. Let's try to do it for real now.
		$store = NewsletterStore::getDefaultInstance();
		$success = $store->addNewsletterIssue( $this->newsletter, $title, $user, $summary );

		if ( !$success ) {
			// DB insert failed. :( so don't create an Echo event and stop from here
			return Status::newFatal( 'newsletter-announce-failure' );
		}

		EchoEvent::create(
			[
				'type' => 'newsletter-announce',
				'title' => $title,
				'extra' => [
					'newsletter-name' => $this->newsletter->getName(),
					'newsletter-id' => $this->newsletter->getId(),
					'section-text' => $summary,
					'notifyAgent' => true,
				],
				'agent' => $user,
			]
		);

		// Yay!
		return true;
	}
	/**
	 * Build the form for displaying the subscribers to a newsletter. This includes
	 * a permission check, and then lists them all in a textarea.
	 */
	protected function doSubscribersExecute() {
		$user = $this->getUser();
		$out = $this->getOutput();

		if ( !$this->newsletter->canManage( $user ) ) {
			$out->showPermissionsErrorPage(
				[ [ 'newsletter-subscribers-nopermission' ] ]
			);
			return;
		}

		$out->setPageTitle( $this->msg( 'newsletter-subscribers' )->text() );
		$subscribers = UserArray::newFromIDs( $this->newsletter->getSubscribers() );
		$subscribersNames = [];
		foreach ( $subscribers as $subscriber ) {
			$subscribersNames[] = $subscriber->getName();
		}

		$fields = [
			'subscribers' => [
				'type' => 'textarea',
				'raw' => true,
				'rows' => 10,
				'default' => implode( "\n", $subscribersNames )
			],
		];

		$form = $this->getHTMLForm(
			$fields,
			[ $this, 'submitSubscribersForm' ]
		);
		if ( $form->show() ) {
			$out->addReturnTo( Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() ) );
		}

	}
	/**
	 * Submit callback for the subscribers form (validate, edit subscribers table).
	 * This assumes that permissions check etc has been done already.
	 *
	 * @param array $data
	 *
	 * @return Status|bool true on success, Status fatal otherwise
	 */
	public function submitSubscribersForm( array $data ) {
		$subscriberNames = explode( "\n", $data['subscribers'] );
		// Strip whitespace, then remove blank lines and duplicates
		$subscriberNames = array_unique( array_filter( array_map( 'trim', $subscriberNames ) ) );

		$oldSubscribersIds = $this->newsletter->getSubscribers();
		$newSubscribersIds = [];
		foreach ( $subscriberNames as $subscriberName ) {
			$user = User::newFromName( $subscriberName );

			if ( !$user || !$user->getId() ) {
				// Input contains an invalid username
				return Status::newFatal( 'newsletter-subscribers-invalid', $subscriberName );
			}

			$newSubscribersIds[] = $user->getId();

		}

		// Do the actual modifications now
		$added = array_diff( $newSubscribersIds, $oldSubscribersIds );
		$removed = array_diff( $oldSubscribersIds, $newSubscribersIds );

		$store = NewsletterStore::getDefaultInstance();
		$store->addSubscription( $this->newsletter, $added );
		if ( $removed ) {
			$store->removeSubscription( $this->newsletter, $removed );
		}
		$out = $this->getOutput();
		// Now report to the user
		if ( $added || $removed ) {
			if ( !class_exists( 'EchoEvent' ) ) {
				throw new Exception( 'Echo extension is not installed.' );
			}
			if ( $added ) {
				EchoEvent::create(
					[
						'type' => 'newsletter-subscribed',
						'extra' => [
							'newsletter-name' => $this->newsletter->getName(),
							'new-subscribers-id' => $added,
							'newsletter-id' => $this->newsletter->getId()
						],
						'agent' => $this->getUser()
					]
				);
			}
			if ( $removed ) {
				EchoEvent::create(
					[
						'type' => 'newsletter-unsubscribed',
						'extra' => [
							'newsletter-name' => $this->newsletter->getName(),
							'removed-subscribers-id' => $removed,
							'newsletter-id' => $this->newsletter->getId()
						],
						'agent' => $this->getUser()
					]
				);
			}
			$out->addWikiMsg( 'newsletter-edit-subscribers-success' );
		} else {
			// Submitted without any changes to the existing subscribers
			$out->addWikiMsg( 'newsletter-edit-subscribers-nochanges' );
		}
		return true;
	}
	/**
	 * Don't list this page in Special:SpecialPages as we just redirect to
	 * Special:Newsletters if no ID was provided.
	 *
	 * @return bool
	 */
	public function isListed() {
		return false;
	}

}
