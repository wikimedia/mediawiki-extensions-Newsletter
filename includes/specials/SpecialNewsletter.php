<?php
/**
 * Special page to handle actions related to specific newsletters
 *
 * @author Glaisher
 * @license GNU GPL v2+
 */
class SpecialNewsletter extends SpecialPage {

	/** Subpage actions */
	const NEWSLETTER_ANNOUNCE = 'announce';
	const NEWSLETTER_DELETE = 'delete';
	const NEWSLETTER_MANAGE = 'manage';
	const NEWSLETTER_SUBSCRIBE = 'subscribe';
	const NEWSLETTER_UNSUBSCRIBE = 'unsubscribe';

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
				case self::NEWSLETTER_MANAGE:
					$this->doManageExecute();
					break;
				case self::NEWSLETTER_DELETE:
					$this->doDeleteExecute();
					break;
				default:
					$this->doViewExecute();
					$action = null;
					break;
			}

			$out->addSubtitle( $this->getNavigationLinks( $action ) );

		} else {
			// Just show an error message if we couldn't find a newsletter
			$out->showErrorPage( 'newsletter-notfound', 'newsletter-not-found-id' );
		}

	}

	/**
	 * Get the navigation links shown in the subtitle
	 *
	 * @param string|null $current subpage currently being shown, null if default "view" page
	 */
	protected function getNavigationLinks( $current ) {
		$listLink = Linker::linkKnown(
			SpecialPage::getTitleFor( 'Newsletters' ),
			$this->msg( 'backlinksubtitle',
				$this->msg( 'newsletter-subtitlelinks-list' )->text()
			)->escaped()
		);
		if ( $current === null ) {
			// We've the fancy buttons on the default "view" page so don't
			// add redundant navigation links and fast return here
			return $listLink;
		}

		// Build the links taking the current user's access levels into account
		$user = $this->getUser();
		$actions = array();
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
		}
		if ( $this->newsletter->canDelete( $user ) ) {
			$actions[] = self::NEWSLETTER_DELETE;
		}

		$links = array();
		foreach ( $actions as $action ) {
			$title = $this->getPageTitle( $this->newsletter->getId() . '/' . $action );
			// Messages used here: 'newsletter-subtitlelinks-announce',
			// 'newsletter-subtitlelinks-subscribe', 'newsletter-subtitlelinks-unsubscribe'
			// 'newsletter-subtitlelinks-manage', 'newsletter-subtitlelinks-delete'
			$msg = $this->msg( 'newsletter-subtitlelinks-' . $action )->escaped();
			if ( $current === $action ) {
				$links[] = Linker::makeSelfLinkObj( $title, $msg );
			} else {
				$links[] = Linker::linkKnown( $title, $msg );
			}
		}

		$newsletterLinks = Linker::linkKnown(
			$this->getPageTitle( $this->newsletter->getId() ),
			$this->getEscapedName()
		) . ' ' . $this->msg( 'parentheses' )
			->rawParams( $this->getLanguage()->pipeList( $links ) )
			->escaped();

		return $this->getLanguage()->pipeList( array( $listLink, $newsletterLinks ) );
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
	 * Build the main form for Special:Newsletter/$id. This is shown
	 * by default when visiting Special:Newsletter/$id
	 */
	protected function doViewExecute() {
		$user = $this->getUser();
		$this->getOutput()->setPageTitle( $this->msg( 'newsletter-view' ) );

		if ( $user->isLoggedIn() ) {
			// buttons are only shown for logged-in users
			 $html = $this->getNewsletterActionButtons();
			 $this->getOutput()->addHTML( $html );
		}

		$publishers = UserArray::newFromIDs( $this->newsletter->getPublishers() );
		$mainTitle = Title::newFromID( $this->newsletter->getPageId() );
		$fields = array(
			'name' => array(
				'type' => 'info',
				'label-message' => 'newsletter-view-name',
				'default' => $this->newsletter->getName(),
			),
			'mainpage' => array(
				'type' => 'info',
				'label-message' => 'newsletter-view-mainpage',
				'default' => Linker::link( $mainTitle, htmlspecialchars( $mainTitle->getPrefixedText() ) ),
				'raw' => true,
			),
			'description' => array(
				'type' => 'textarea',
				'label-message' => 'newsletter-view-description',
				'default' => $this->newsletter->getDescription(),
				'rows' => 6,
				'readonly' => true,
			),
			'publishers' => array(
				'type' => 'info',
				'label' => $this->msg( 'newsletter-view-publishers' )
					->numParams( count( $publishers ) )
					->parse(),
			),
			'subscribe' => array(
				'type' => 'info',
				'label-message' => 'newsletter-view-subscriber-count',
				'default' => $this->getLanguage()->formatNum( $this->newsletter->getSubscriberCount() ),
			),
		);

		if ( count( $publishers ) > 0 ) {
			// Have this here to avoid calling unneeded functions
			$this->doLinkCacheQuery( $publishers );
			$fields['publishers']['default'] = $this->buildUserList( $publishers );
			$fields['publishers']['raw'] = true;
		} else {
			// Show a message if there are no publishers instead of nothing
			$fields['publishers']['default'] = $this->msg( 'newsletter-view-no-publishers' )->escaped();
		}

		$form = $this->getHTMLForm(
			$fields,
			function() {
				return false;
			} // nothing to submit - the buttons on this page are just links
		);

		$form->suppressDefaultSubmit();
		$form->show();
	}

	/**
	 * Build a group of buttons: Delete, Manage, Subscribe|Unsubscribe
	 * Buttons will be showed to the user only if they are relevant to the current user.
	 *
	 * @return string HTML for the button group
	 */
	protected function getNewsletterActionButtons() {
		$user = $this->getUser();
		$id = $this->newsletter->getId();
		$buttons = array();
		$this->getOutput()->enableOOUI();

		if ( $this->newsletter->canDelete( $user ) ) {
			// This is visible to publishers and users with 'newsletter-delete' right
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-delete-button' )->escaped(),
					'icon' => 'remove',
					'href' => $this->getPageTitle( $id . '/' . self::NEWSLETTER_DELETE )->getFullURL()
				)
			);
		}

		if ( $this->newsletter->canManage( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-manage-button' )->escaped(),
					'icon' => 'settings',
					'href' =>  $this->getPageTitle( $id . '/' . self::NEWSLETTER_MANAGE )->getFullURL()
				)
			);
		}

		if ( $this->newsletter->isPublisher( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-announce-button' )->escaped(),
					'icon' => 'comment',
					'href' => $this->getPageTitle( $id . '/' . self::NEWSLETTER_ANNOUNCE )->getFullURL()
				)
			);
		}

		if ( $this->newsletter->isSubscribed( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-unsubscribe-button' )->escaped(),
					'flags' => array( 'destructive' ),
					'href' => $this->getPageTitle( $id . '/' . self::NEWSLETTER_UNSUBSCRIBE )->getFullURL()
				)
			);
		} else {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-subscribe-button' )->escaped(),
					'flags' => array( 'constructive' ),
					'href' => $this->getPageTitle( $id . '/' . self::NEWSLETTER_SUBSCRIBE )->getFullURL()
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
			$txt = $this->msg( 'newsletter-subscribe-text' )
				->rawParams( $this->getEscapedName() )->parse();
			$button = array(
				'unsubscribe' => array(
					'type' => 'submit',
					'name' => 'unsubscribe',
					'default' => $this->msg( 'newsletter-do-unsubscribe' )->escaped(),
					'id' => 'mw-newsletter-unsubscribe',
					'flags' => array( 'primary', 'destructive' ),
				)
			);
		} else {
			// Show the subscribe form if the user is not subscribed currently
			$txt = $this->msg( 'newsletter-subscribe-text' )
				->rawParams( $this->getEscapedName() )->parse();
			$button = array(
				'subscribe' => array(
					'type' => 'submit',
					'name' => 'subscribe',
					'default' => $this->msg( 'newsletter-do-subscribe' )->escaped(),
					'id' => 'mw-newsletter-subscribe',
					'flags' => array( 'primary', 'constructive' ),
				)
			);
		}

		$form = $this->getHTMLForm( $button, array( $this, 'submitSubscribeForm' ) );
		$form->addHeaderText( $txt );
		$form->suppressDefaultSubmit();
		$form->show();
		$this->getOutput()->addReturnTo( $this->getPageTitle( $this->newsletter->getId() ) );
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
				array( array( 'newsletter-announce-nopermission' ) )
			);
			return;
		}

		$out->setPageTitle(
			$this->msg( 'newsletter-announce' )
				->rawParams( $this->getEscapedName() )
		);

		$fields = array(
			'issuepage' => array(
				'type' => 'title',
				'name' => 'issuepage',
				'creatable' => true,
				'required' => true,
				'label-message' => 'newsletter-announce-issuetitle',
				'default' => '',
			),
			'summary' => array(
				// @todo add a help message explaining what this does
				'type' => 'text',
				'name' => 'summary',
				'label-message' => 'newsletter-announce-summary',
				'maxlength' => '160',
				'autofocus' => true,
			),
		);

		$form = $this->getHTMLForm(
			$fields,
			array( $this, 'submitAnnounceForm' )
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
			$out->addReturnTo( $this->getPageTitle( $this->newsletter->getId() ) );
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

		// Everything seems okay. Let's try to do it for real now.
		$db = NewsletterDb::newFromGlobalState();
		$success = $db->addNewsletterIssue( $this->newsletter, $title, $this->getUser() );

		if ( !$success ) {
			// DB insert failed. :( so don't create an Echo event and stop from here
			return Status::newFatal( 'newsletter-announce-failure' );
		}

		EchoEvent::create(
			array(
				'type' => 'newsletter-announce',
				'title' => $title,
				'extra' => array(
					'newsletter-name' => $this->newsletter->getName(),
					'newsletter-id' => $this->newsletter->getId(),
					'section-text' => trim( $data['summary'] ),
					'notifyAgent' => true,
				),
				'agent' => $this->getUser(),
				'name' => $this->getUser()->getName()
			)
		);

		// Yay!
		return true;
	}

	/**
	 * Build the delete form for Special:Newsletter/$id/delete
	 * Only newsletter publishers have access to this form currently.
	 */
	protected function doDeleteExecute() {
		$user = $this->getUser();
		$out = $this->getOutput();

		$this->checkReadOnly();

		if ( $user->isBlocked() ) {
			// Blocked users shouldn't be deleting newsletters..
			throw new UserBlockedError( $user->getBlock() );
		}

		if ( !$this->newsletter->canDelete( $user ) ) {
			throw new PermissionsError( 'newsletter-delete' );
		}

		$out->setPageTitle( $this->msg( 'newsletter-delete' ) );
		$out->addModules( 'ext.newsletter.delete' ); // Adds confirmation dialog box

		// @todo add reason field when logging is implemented
		$form = $this->getHTMLForm( array(), array( $this, 'submitDeleteForm' ) );
		$form->addHeaderText(
			$this->msg( 'newsletter-delete-text' )
				->rawParams( $this->getEscapedName() )->parse()
		);
		$form->setId( 'newsletter-delete-form' );
		$form->setSubmitID( 'newsletter-delete-button' );
		$form->setSubmitTextMsg( 'newsletter-deletenewsletter-button' );
		$form->setSubmitDestructive();

		if ( !$form->show() ) {
			// After submission, no point in showing the return to link if the newsletter was just deleted
			$out->addReturnTo( $this->getPageTitle( $this->newsletter->getId() ) );
		}
	}

	/**
	 * @todo make sure that the newsletter was actually deleted before outputting the result!
	 *
	 * @return bool
	 */
	public function submitDeleteForm() {
		NewsletterDb::newFromGlobalState()->deleteNewsletter( $this->newsletter );
		$this->getOutput()->addWikiMsg( 'newsletter-delete-success', $this->newsletter->getId() );

		return true;
	}

	/**
	 * Implement logging for newsletter actions
	 * Build the manage form for Special:Newsletter/$id/manage. This does
	 * permissions and read-only checks too.
	 *
	 * @throws UserBlockedError
	 * @throws PermissionsError
	 */
	protected function doManageExecute() {
		$user = $this->getUser();
		$out = $this->getOutput();

		$this->checkReadOnly();

		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		if ( !$this->newsletter->canManage( $user ) ) {
			throw new PermissionsError( 'newsletter-manage' );
		}

		$out->setPageTitle(
			$this->msg( 'newsletter-manage' )
				->rawParams( $this->getEscapedName() )
		);

		$publishers = UserArray::newFromIDs( $this->newsletter->getPublishers() );
		$publishersNames = array();
		$mainTitle = Title::newFromID( $this->newsletter->getPageId() );
		foreach ( $publishers as $publisher ) {
			$publishersNames[] = $publisher->getName();
		}

		$fields['Name'] = array(
			'type' => 'text',
			'label-message' => 'newsletter-manage-name',
			'default' => $this->newsletter->getName(),
			'required' => true,
		);
		$fields['MainPage'] = array(
			'type' => 'title',
			'label-message' => 'newsletter-manage-title',
			'default' =>  $mainTitle->getPrefixedText(),
			'required' => true,
		);
		$fields['Description'] = array(
			'type' => 'textarea',
			'label-message' => 'newsletter-manage-description',
			'rows' => 6,
			'default' => $this->newsletter->getDescription(),
			'required' => true,
		);
		$fields['Publishers'] = array(
			'type' => 'textarea',
			'label-message' => 'newsletter-manage-publishers',
			'rows' => 10,
			'default' => implode( "\n", $publishersNames ),
		);
		$fields['Confirm'] = array(
			'type' => 'hidden',
			'default' => false,
		);
		if ( $this->getRequest()->wasPosted() ) {
			// @todo Make this work properly for double submissions
			$fields['Confirm']['default'] = true;
		}
		$form = $this->getHTMLForm(
			$fields,
			array( $this, 'submitManageForm' )
		);
		$form->addHeaderText(
			$this->msg( 'newsletter-manage-text' )
				->rawParams( $this->getEscapedName() )->parse()
		);
		$form->setId( 'newsletter-manage-form' );
		$form->setSubmitID( 'newsletter-manage-button' );
		$form->setSubmitTextMsg( 'newsletter-managenewsletter-button' );
		$form->show();
	}

	/**
	 * Submit callback for the manage form.
	 *
	 * @todo Move most of this code out of SpecialNewsletter class
	 * @param array $data
	 *
	 * @return Status|bool true on success, Status fatal otherwise
	 */
	public function submitManageForm( array $data ) {
		$confirmed = (bool)$data['Confirm'];
		$modified = false;

		$oldName = $this->newsletter->getName();
		$oldDescription = $this->newsletter->getDescription();
		$oldMainPage = $this->newsletter->getPageId();

		$name = trim( $data['Name'] );
		$description = trim( $data['Description'] );
		$mainPage = Title::newFromText( $data['MainPage'] );

		if ( !$mainPage ) {
			return Status::newFatal( 'newsletter-create-mainpage-error' );
		}

		$formData = array(
			'Name' => $name,
			'Description' => $description,
			'MainPage' => $mainPage,
		);

		$validator = new NewsletterValidator( $formData );
		$validation = $validator->validate();
		if ( !$validation->isGood() ) {
			// Invalid input was entered
			return $validation;
		}

		$mainPageId = $mainPage->getArticleID();

		$ndb = NewsletterDb::newFromGlobalState();
		$newsletterId = $this->newsletter->getId();

		if ( $name != $oldName ) {
			$rows = $ndb->newsletterExistsWithName( $name );
			foreach ( $rows as $row ) {
				if ( $row->nl_name === $name ) {
					return Status::newFatal( 'newsletter-exist-error', $name );
				}
			}
			$ndb->updateName( $newsletterId, $name );
			$modified = true;
		}
		if ( $description != $oldDescription ) {
			$ndb->updateDescription( $newsletterId, $description );
			$modified = true;
		}
		if ( $oldMainPage != $mainPageId ) {
			$rows = $ndb->newsletterExistsForMainPage( $mainPageId );
			foreach ( $rows as $row ) {
				if ( (int)$row->nl_main_page_id === $mainPageId  ) {
					return Status::newFatal( 'newsletter-mainpage-in-use' );
				}
			}
			$ndb->updateMainPage( $newsletterId, $mainPageId );
			$modified = true;
		}

		$lines = explode( "\n", $data['Publishers'] );
		// Strip whitespace, then remove blank lines and duplicates
		$lines = array_unique( array_filter( array_map( 'trim', $lines ) ) );

		// Ask for confirmation before removing all the publishers
		if ( !$confirmed && count( $lines ) === 0 ) {
			return Status::newFatal( 'newsletter-manage-no-publishers' );
		}

		/** @var User[] $newPublishers */
		$newPublishers = array();
		foreach ( $lines as $publisherName ) {
			$user = User::newFromName( $publisherName );
			if ( !$user || !$user->getId() ) {
				// Input contains an invalid username
				return Status::newFatal( 'newsletter-manage-invalid-publisher', $publisherName );
			}
			$newPublishers[] = $user;
		}

		$oldPublishersIds = $this->newsletter->getPublishers();
		$newPublishersIds = self::getIdsFromUsers( $newPublishers );

		// Confirm whether the current user (if already a publisher)
		// wants to be removed from the publishers group
		$user = $this->getUser();
		if ( !$confirmed
			&& $this->newsletter->isPublisher( $user )
			&& !in_array( $user->getId(), $newPublishersIds )
		) {
			return Status::newFatal( 'newsletter-manage-remove-self-publisher' );
		}

		// Do the actual modifications now
		$added = array_diff( $newPublishersIds, $oldPublishersIds );
		$removed = array_diff( $oldPublishersIds, $newPublishersIds );

		$ndb = NewsletterDb::newFromGlobalState();
		// @todo Do this in a batch..
		foreach ( $added as $auId ) {
			$ndb->addPublisher( $this->newsletter, User::newFromId( $auId ) );
		}

		if ( $added ) {
			EchoEvent::create(
				array(
					'type' => 'newsletter-newpublisher',
					'extra' => array(
						'newsletter-name' => $this->newsletter->getName(),
						'new-publishers-id' => $added,
						'newsletter-id' => $newsletterId
					),
					'agent' => $user
				)
			);
		}

		foreach ( $removed as $ruId ) {
			$ndb->removePublisher( $this->newsletter, User::newFromId( $ruId ) );
		}

		// Now report to the user
		$out = $this->getOutput();
		if ( $added || $removed || $modified ) {
			$out->addWikiMsg( 'newsletter-manage-newsletter-success' );
		} else {
			// Submitted without any changes to the existing publishers
			$out->addWikiMsg( 'newsletter-manage-newsletter-nochanges' );
		}
		$out->addReturnTo( $this->getPageTitle( $newsletterId ) );

		return true;
	}

	/**
	 * Helper function for submitManageForm() to get user IDs from an array
	 * of User objects because we need to do comparison. This is not related
	 * to this class at all. :-/
	 *
	 * @param User[] $users
	 * @return int[]
	 */
	private static function getIdsFromUsers( $users ) {
		$ids = array();
		foreach ( $users as $user ) {
			$ids[] = $user->getId();
		}
		return $ids;
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
