<?php

namespace MediaWiki\Extension\Newsletter;

use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\BadRequestError;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Exception\ReadOnlyError;
use MediaWiki\Exception\ThrottledError;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\Extension\Newsletter\Content\NewsletterContentHandler;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserArray;

/**
 * @license GPL-2.0-or-later
 * @author tonythomas
 */

class NewsletterEditPage {

	/** @var bool */
	protected $createNew;

	/** @var IContextSource */
	protected $context;

	/** @var bool */
	protected $readOnly = false;

	/** @var Newsletter|null */
	protected $newsletter;

	/** @var User */
	private $user;

	/** @var Title */
	private $title;

	/** @var OutputPage */
	private $out;

	public function __construct( IContextSource $context, ?Newsletter $newsletter = null ) {
		$this->context = $context;
		$this->user = $context->getUser();
		$this->title = $context->getTitle();
		$this->out = $context->getOutput();
		$this->newsletter = $newsletter;
	}

	public function edit() {
		$services = MediaWikiServices::getInstance();
		if ( $services->getReadOnlyMode()->isReadOnly() ) {
			throw new ReadOnlyError;
		}
		$this->createNew = !$this->title->exists();
		if ( !$this->createNew ) {
			// A newsletter exists, lets open the edit page
			$block = $this->user->getBlock();
			if ( $block ) {
				throw new UserBlockedError( $block );
			}

			if ( !$this->newsletter->canManage( $this->user ) ) {
				throw new PermissionsError( 'newsletter-manage' );
			}

			$this->out->setPageTitleMsg(
				$this->context->msg( 'newsletter-manage' )
					->params( $this->newsletter->getName() )
			);

			$revId = $this->context->getRequest()->getInt( 'undoafter' );
			$undoId = $this->context->getRequest()->getInt( 'undo' );
			$oldId = $this->context->getRequest()->getInt( 'oldid' );
			$this->getManageForm( $revId, $undoId, $oldId )->show();
		} else {
			$permManager = $services->getPermissionManager();
			$status = $permManager->getPermissionStatus( 'edit', $this->user, $this->title );
			if ( !$status->isGood() ) {
				$this->out->showPermissionStatus( $status );
				return;
			}

			$this->out->setPageTitle(
				$this->context->msg( 'newslettercreate', $this->title->getPrefixedText() )->text()
			);
			$this->getForm()->show();
		}
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
	 * Create the manage form. If this is on an undo revision action, $revId would be set, and we
	 * manually load in form data from the reverted revision.
	 *
	 * @param int $revId
	 * @param int $undoId
	 * @param int $oldId
	 * @return HTMLForm
	 * @throws BadRequestError Exception thrown on false revision id on revision undo, etc
	 */
	protected function getManageForm( $revId, $undoId, $oldId ) {
		$publishers = UserArray::newFromIDs( $this->newsletter->getPublishers() );
		$publishersNames = [];

		$mainTitle = Title::newFromID( $this->newsletter->getPageId() );
		foreach ( $publishers as $publisher ) {
			$publishersNames[] = $publisher->getName();
		}

		if ( $mainTitle === null ) {
			$mainText = null;
		} else {
			$mainText = $mainTitle->getPrefixedText();
		}

		$fields = [
			'MainPage' => [
				'type' => 'title',
				'label-message' => 'newsletter-manage-title',
				'default' => $mainText,
				'required' => true,
			],
			'Description' => [
				'type' => 'textarea',
				'label-message' => 'newsletter-manage-description',
				'rows' => 6,
				'default' => $this->newsletter->getDescription(),
				'required' => true,
			],
			'Publishers' => [
				'type' => 'usersmultiselect',
				'label-message' => 'newsletter-manage-publishers',
				'exists' => true,
				'default' => implode( "\n", $publishersNames ),
			],
			'Summary' => [
				'type' => 'text',
				'label-message' => 'newsletter-manage-summary',
				'required' => false,
			],
			'Confirm' => [
				'type' => 'hidden',
				'default' => false,
			],
		];

		// Ensure action is not editing the current revision
		if ( ( $revId && $undoId ) || $oldId ) {
			$oldRevRecord = null;
			$revLookup = MediaWikiServices::getInstance()->getRevisionLookup();
			// Editing a previous revision
			if ( $oldId ) {
				$oldRevRecord = $revLookup->getRevisionById( $oldId );
				$oldMainSlot = $oldRevRecord->getSlot(
					SlotRecord::MAIN,
					RevisionRecord::RAW
				);
				if ( $oldMainSlot->getModel() === 'NewsletterContent'
					&& !$oldRevRecord->isDeleted( RevisionRecord::DELETED_TEXT )
				) {
					$fields['Summary']['default'] = '';
				}
			} elseif ( $revId && $undoId ) {
				// Undoing the latest revision
				$oldRevRecord = $revLookup->getRevisionById( $revId );
				$undoRevRecord = $revLookup->getRevisionById( $undoId );
				$undoMainSlot = $undoRevRecord->getSlot(
					SlotRecord::MAIN,
					RevisionRecord::RAW
				);
				if ( $undoRevRecord->isCurrent()
					&& $undoMainSlot->getModel() === 'NewsletterContent'
					&& !$undoRevRecord->isDeleted( RevisionRecord::DELETED_TEXT )
				) {
					$userText = $undoRevRecord->getUser() ?
						$undoRevRecord->getUser()->getName() :
						'';
					$fields['Summary']['default'] =
						$this->context->msg( 'undo-summary' )
							->params( $undoRevRecord->getId(), $userText )
							->inContentLanguage()
							->text();
				} else {
					// User attempts to undo prior revision
					throw new BadRequestError(
						'newsletter-oldrev-undo-error-title',
						'newsletter-oldrev-undo-error-body'
					);
				}
			}

			if ( $oldRevRecord
				&& $oldRevRecord->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )
					->getModel() === 'NewsletterContent'
				&& !$oldRevRecord->isDeleted( RevisionRecord::DELETED_TEXT )
			) {
				$content = $oldRevRecord->getContent( SlotRecord::MAIN );
				'@phan-var \MediaWiki\Extension\Newsletter\Content\NewsletterContent $content';
				$fields['MainPage']['default'] = $content->getMainPage()->getPrefixedText();
				$fields['Description']['default'] = $content->getDescription();
				// HTMLUsersMultiselectField expects a string, so we implode here
				$publisherNames = $content->getPublishers();
				$fields['Publishers']['default'] = implode( "\n", $publishersNames );
			}
		}

		if ( $this->context->getRequest()->wasPosted() ) {
			// @todo Make this work properly for double submissions
			$fields['Confirm']['default'] = true;
		}

		$form = HTMLForm::factory(
			'ooui',
			$fields,
			$this->context
		);
		$form->setSubmitCallback( [ $this, 'submitManageForm' ] );
		$form->setAction( $this->title->getLocalURL( 'action=submit' ) );
		$form->addHeaderHtml(
			$this->context->msg( 'newsletter-manage-text' )
				->params( $this->newsletter->getName() )->parse()
		);
		$form->setId( 'newsletter-manage-form' );
		$form->setSubmitID( 'newsletter-manage-button' );
		$form->setSubmitTextMsg( 'newsletter-managenewsletter-button' );
		return $form;
	}

	/**
	 * @return HTMLForm
	 */
	protected function getForm() {
		$form = HTMLForm::factory(
			'ooui',
			$this->getFormFields(),
			$this->context
		);
		$form->setSubmitCallback( [ $this, 'attemptSave' ] );
		$form->setAction( $this->title->getLocalURL( 'action=edit' ) );
		$form->addHeaderHtml( $this->context->msg( 'newslettercreate-text' )->parseAsBlock() );
		// Retain query parameters (uselang etc)
		$params = array_diff_key(
			$this->context->getRequest()->getQueryValues(), [ 'title' => null ] );
		$form->addHiddenField( 'redirectparams', wfArrayToCgi( $params ) );
		$form->setSubmitTextMsg( 'newsletter-create-submit' );

		// @todo $form->addPostHtml( save/copyright warnings etc. );
		return $form;
	}

	/**
	 * @return array
	 */
	protected function getFormFields() {
		return [
			'name' => [
				'type' => 'text',
				'required' => true,
				'label-message' => 'newsletter-name',
				'maxlength' => 120,
				'default' => $this->title->getText(),
			],
			'mainpage' => [
				'type' => 'title',
				'required' => true,
				'label-message' => 'newsletter-title',
			],
			'description' => [
				'type' => 'textarea',
				'required' => true,
				'label-message' => 'newsletter-desc',
				'rows' => 15,
				'maxlength' => 600000,
			],
		];
	}

	/**
	 * Do input validation, error handling and create a new newletter.
	 *
	 * This is only for saving a new page. Modifying an existing page
	 * is submitManageForm()
	 *
	 * @param array $input The data entered by user in the form
	 * @throws ThrottledError
	 * @return Status
	 */
	public function attemptSave( array $input ) {
		$data = [
			'Name' => trim( $input['name'] ),
			'Description' => trim( $input['description'] ),
			'MainPage' => Title::newFromText( $input['mainpage'] ),
		];

		$validator = new NewsletterValidator( $data );
		$validation = $validator->validate( true );
		if ( !$validation->isGood() ) {
			// Invalid input was entered
			return $validation;
		}

		$mainPageId = $data['MainPage']->getArticleID();
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'nl_name', 'nl_main_page_id', 'nl_active' ] )
			->from( 'nl_newsletters' )
			->where(
				$dbr->expr( 'nl_name', '=', $data['Name'] )->orExpr(
					$dbr->expr( 'nl_main_page_id', '=', $mainPageId )->and( 'nl_active', '=', 1 )
				)
			)
			->caller( __METHOD__ )
			->fetchResultSet();
		// Check whether another existing newsletter has the same name or main page
		foreach ( $rows as $row ) {
			if ( $row->nl_name === $data['Name'] ) {
				return Status::newFatal( 'newsletter-exist-error', $data['Name'] );
			} elseif ( (int)$row->nl_main_page_id === $mainPageId && (int)$row->nl_active === 1 ) {
				return Status::newFatal( 'newsletter-mainpage-in-use' );
			}
		}

		if ( $this->user->pingLimiter( 'newsletter' ) ) {
			// Default user access level for creating a newsletter is quite low
			// so add a throttle here to prevent abuse (eg. mass vandalism spree)
			throw new ThrottledError;
		}
		$title = Title::makeTitleSafe( NS_NEWSLETTER, $data['Name'] );
		$editSummaryMsg = $this->context->msg( 'newsletter-create-editsummary' );
		$result = NewsletterContentHandler::edit(
			$title,
			$data['Description'],
			$input['mainpage'],
			[ $this->user->getName() ],
			$editSummaryMsg->inContentLanguage()->plain(),
			$this->context
		);
		if ( $result->isGood() ) {
			$this->out->addWikiMsg( 'newsletter-create-confirmation', $data['Name'] );
			return Status::newGood();
		} else {
			return $result;
		}
	}

	/**
	 * Submit callback for the manage form.
	 *
	 * This is only for editing an existing page. Making a new page
	 * is attemptSave()
	 *
	 * @todo Move most of this code out of SpecialNewsletter class
	 * @param array $data
	 *
	 * @return Status|bool true on success, Status fatal otherwise
	 */
	public function submitManageForm( array $data ) {
		$confirmed = (bool)$data['Confirm'];

		$description = trim( $data['Description'] );
		$mainPage = Title::newFromText( $data['MainPage'] );

		if ( !$mainPage ) {
			return Status::newFatal( 'newsletter-create-mainpage-error' );
		}

		$formData = [
			'Description' => $description,
			'MainPage' => $mainPage,
		];

		$validator = new NewsletterValidator( $formData );
		$validation = $validator->validate( false );
		if ( !$validation->isGood() ) {
			// Invalid input was entered
			return $validation;
		}

		$newsletterId = $this->newsletter->getId();

		$title = Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() );

		$publisherNames = $data['Publishers'] ? explode( "\n", $data['Publishers'] ) : [];

		// Ask for confirmation before removing all the publishers
		if ( !$confirmed && count( $publisherNames ) === 0 ) {
			return Status::newFatal( 'newsletter-manage-no-publishers' );
		}

		/** @var User[] $newPublishers */
		$newPublishers = array_map( [ User::class, 'newFromName' ], $publisherNames );
		$newPublishersIds = self::getIdsFromUsers( $newPublishers );

		// Confirm whether the current user (if already a publisher)
		// wants to be removed from the publishers group
		$user = $this->user;
		if ( !$confirmed && $this->newsletter->isPublisher( $user )
			&& !in_array( $user->getId(), $newPublishersIds )
		) {
			return Status::newFatal( 'newsletter-manage-remove-self-publisher' );
		}

		$editResult = NewsletterContentHandler::edit(
			$title,
			$description,
			$mainPage->getFullText(),
			$publisherNames,
			trim( $data['Summary'] ),
			$this->context
		);

		if ( $editResult->isGood() ) {
			$this->out->redirect( $title->getLocalURL() );
		} else {
			return $editResult;
		}

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
		$ids = [];
		foreach ( $users as $user ) {
			$ids[] = $user->getId();
		}
		return $ids;
	}

}
