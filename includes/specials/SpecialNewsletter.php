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

	/**
	 * @var Newsletter|null
	 */
	protected $newsletter;

	public function __construct() {
		parent::__construct( 'Newsletter' );
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
		$out->setSubtitle( LinksGenerator::getSubtitleLinks() );
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
			$this->getContext(),
			''
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
		$this->doLinkCacheQuery( $publishers );
		$mainTitle = Title::newFromID( $this->newsletter->getPageId() );
		$fields = array(
			'id' => array(
				'type' => 'info',
				'label-message' => 'newsletter-view-id',
				'default' => (string)$this->newsletter->getId(),
			),
			'name' => array(
				'type' => 'info',
				'label-message' => 'newsletter-view-name',
				'default' => $this->newsletter->getName(),
			),
			'mainpage' => array(
				'type' => 'info',
				'label-message' => 'newsletter-view-mainpage',
				'default' => Linker::link( $mainTitle, htmlspecialchars( $mainTitle->getPrefixedText() ) )
					. ' '
					. $this->msg( 'parentheses' )->rawParams(
						Linker::link( $mainTitle, 'hist', array(), array( 'action' => 'history' ) )
					)->escaped(),
				'raw' => true,
			),
			'frequency' => array(
				'type' => 'info',
				'label-message' => 'newsletter-view-frequency',
				'default' => $this->newsletter->getFrequency(),
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
				'label' => $this->msg( 'newsletter-view-publishers' )->numParams( count( $publishers ) )->parse(),
				'default' => $this->buildUserList( $publishers ),
				'raw' => true,
			),
			'subscribe' => array(
				'type' => 'info',
				'label-message' => 'newsletter-view-subscriber-count',
				'raw' => true,
				'default' => $this->getLanguage()->formatNum( $this->newsletter->getSubscriberCount() ),
			),
		);

		if ( count( $publishers ) === 0 ) {
			// Show another message if there are no publishers instead of nothing
			$fields['publishers']['default'] = $this->msg( 'newsletter-view-no-publishers' )->escaped();
		}

		$form = $this->getHTMLForm(
			$fields,
			function() { return false; } // nothing to submit - the buttons on this page are just links
		);

		if ( $user->isLoggedIn() ) {
			// Tell the current logged-in user whether they are subscribed or not
			$form->addFooterText(
				$this->msg(
					$this->newsletter->isSubscribed( $user )
						? 'newsletter-user-subscribed'
						: 'newsletter-user-notsubscribed'
				)->text()
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

		// Only publishers can manage and delete newsletters
		if ( $this->newsletter->isPublisher( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-delete-button' )->escaped(),
					'flags' => array( 'destructive' ),
					'icon' => 'remove',
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id . '/' . self::NEWSLETTER_DELETE )->getFullURL()
				)
			);

			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-manage-button' )->escaped(),
					'flags' => array(),
					'icon' => 'settings',
					'href' => SpecialPage::getTitleFor( 'NewsletterManage' )->getFullURL()
				)
			);
		}

		if ( $this->newsletter->isSubscribed( $user ) ) {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-unsubscribe-button' )->escaped(),
					'flags' => array( 'primary', 'destructive' ),
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id . '/' . self::NEWSLETTER_UNSUBSCRIBE )->getFullURL()
				)
			);
		} else {
			$buttons[] = new OOUI\ButtonWidget(
				array(
					'label' => $this->msg( 'newsletter-subscribe-button' )->escaped(),
					'flags' => array( 'primary', 'constructive' ),
					'href' => SpecialPage::getTitleFor( 'Newsletter', $id . '/' . self::NEWSLETTER_SUBSCRIBE )->getFullURL()
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
			$txt = $this->msg( 'newsletter-unsubscribe-text', $this->newsletter->getName() )->parse();
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
			$txt = $this->msg( 'newsletter-subscribe-text', $this->newsletter->getName() )->parse();
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
		$this->getOutput()->addReturnTo( SpecialPage::getTitleFor( 'Newsletter', $this->newsletter->getId() ) );
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
		}
		if ( !isset( $status ) ) {
			throw new Exception( "POST data corrupted or required parameter missing from request" );
		}
		if ( $status->isGood() ) {
			// @todo We could probably do this in a better way
			// Add the success message if the action was successful
			// Messages used: 'newsletter-subscribe-success', 'newsletter-unsubscribe-success'
			$this->getOutput()->addWikiMsg( "newsletter-$action-success", $this->newsletter->getName() );
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

		if ( !$this->newsletter->isPublisher( $user ) ) {
			// only publishers can delete newsletter (for now)
			$out->showPermissionsErrorPage(
				array( array( 'newsletter-delete-nopermission' ) )
			);
			return;
		}

		$out->setPageTitle( $this->msg( 'newsletter-delete' ) );

		// @todo add reason field when logging is implemented
		$form = $this->getHTMLForm( array(), array( $this, 'submitDeleteForm' ) );
		$form->addHeaderText( $this->msg( 'newsletter-delete-text', $this->newsletter->getName() )->text() );
		$form->setSubmitTextMsg( 'newsletter-deletenewsletter-button' );
		$form->setSubmitDestructive();

		if ( !$form->show() ) {
			// After submission, no point in showing showing the return to link if the newsletter was just deleted
			$out->addReturnTo( SpecialPage::getTitleFor( 'Newsletter', $this->newsletter->getId() ) );
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
