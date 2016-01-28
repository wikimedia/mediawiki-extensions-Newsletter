<?php
/**
 * Special page to handle actions related to specific newsletters
 *
 * @author Glaisher
 * @license GNU GPL v2+
 */
class SpecialNewsletter extends SpecialPage {

	/** Subpage actions */
	const NEWSLETTER_SUBSCRIBE = 'subscribe';
	const NEWSLETTER_UNSUBSCRIBE = 'unsubscribe';
	const NEWSLETTER_DELETE = 'delete';
	const NEWSLETTER_ANNOUNCE = 'announce';

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
				case self::NEWSLETTER_DELETE:
					$this->doDeleteExecute();
					break;
				default:
					$this->doViewExecute();
					break;
			}

		} else {
			// Just show an error message if we couldn't find a newsletter
			$out->showErrorPage( 'newsletter-notfound', 'newsletter-not-found-id' );
		}

		$out->setSubtitle( NewsletterLinksGenerator::getSubtitleLinks( $this->getContext() ) );
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
	 * Build the main form for Special:Newsletter/$id. This is shown
	 * by default when visiting Special:Newsletter/$id
	 */
	protected function doViewExecute() {
		$user = $this->getUser();
		$this->getOutput()->setPageTitle( $this->msg( 'newsletter-view' ) );

		$html = $this->msg( 'newsletter-view-text' )->parseAsBlock();
		if ( $user->isLoggedIn() ) {
			// buttons are only shown for logged-in users
			 $html .= $this->getNewsletterActionButtons();
		}
		$this->getOutput()->addHTML( $html );

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

		if ( $user->isLoggedIn() ) {
			// Tell the current logged-in user whether they are subscribed or not
			$form->addFooterText(
				$this->msg(
					$this->newsletter->isSubscribed( $user )
						? 'newsletter-user-subscribed'
						: 'newsletter-user-notsubscribed'
				)->escaped()
			);
		}
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
					'flags' => array( 'destructive' ),
					'icon' => 'remove',
					'href' => $this->getPageTitle( $id . '/' . self::NEWSLETTER_DELETE )->getFullURL()
				)
			);
		}

		if ( $this->newsletter->canManage( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-manage-button' )->escaped(),
					'flags' => array(),
					'icon' => 'settings',
					'href' => SpecialPage::getTitleFor( 'NewsletterManage' )->getFullURL()
				)
			);
		}

		if ( $this->newsletter->isPublisher( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-announce-button' )->escaped(),
					'flags' => array( 'progressive' ),
					'icon' => 'comment',
					'href' => $this->getPageTitle( $id . '/' . self::NEWSLETTER_ANNOUNCE )->getFullURL()
				)
			);
		}

		if ( $this->newsletter->isSubscribed( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-unsubscribe-button' )->escaped(),
					'flags' => array( 'primary', 'destructive' ),
					'href' => $this->getPageTitle( $id . '/' . self::NEWSLETTER_UNSUBSCRIBE )->getFullURL()
				)
			);
		} else {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-subscribe-button' )->escaped(),
					'flags' => array( 'primary', 'constructive' ),
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
				->rawParams( htmlspecialchars( $this->newsletter->getName() ) )->parse();
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
				->rawParams( htmlspecialchars( $this->newsletter->getName() ) )->parse();
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
				->rawParams( htmlspecialchars( $this->newsletter->getName() ) )
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
					->rawParams( htmlspecialchars( $this->newsletter->getName() ) )
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
		$success = $db->addNewsletterIssue(
			$this->newsletter->getId(),
			$title->getArticleId(),
			$this->getUser()->getId()
		);

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
					->rawParams( htmlspecialchars( $this->newsletter->getName() ) )->parse()
			);
		}

		return $status;
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
				->rawParams( htmlspecialchars( $this->newsletter->getName() ) )->parse()
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
		$id = $this->newsletter->getId();
		NewsletterDb::newFromGlobalState()->deleteNewsletter( $id );
		$this->getOutput()->addWikiMsg( 'newsletter-delete-success', $id );

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
