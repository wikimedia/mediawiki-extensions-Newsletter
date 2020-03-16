<?php

use MediaWiki\MediaWikiServices;

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

	public function __construct( IContextSource $context, Newsletter $newsletter = null ) {
		$this->context = $context;
		$this->user = $context->getUser();
		$this->title = $context->getTitle();
		$this->out = $context->getOutput();
		$this->newsletter = $newsletter;
	}

	public function edit() {
		if ( wfReadOnly() ) {
			throw new ReadOnlyError;
		}
		$this->createNew = !$this->title->exists();
		if ( !$this->createNew ) {
			// A newsletter exists, lets open the edit page
			if ( $this->user->isBlocked() ) {
				throw new UserBlockedError( $this->user->getBlock() );
			}

			if ( !$this->newsletter->canManage( $this->user ) ) {
				throw new PermissionsError( 'newsletter-manage' );
			}

			$this->out->setPageTitle(
				$this->context->msg( 'newsletter-manage' )
					->params( $this->newsletter->getName() )
			);

			$revId = $this->context->getRequest()->getInt( 'undoafter' );
			$undoId = $this->context->getRequest()->getInt( 'undo' );
			$oldId = $this->context->getRequest()->getInt( 'oldid' );
			$this->getManageForm( $revId, $undoId, $oldId )->show();
		} else {
			$permErrors = $this->getPermissionErrors();
			if ( count( $permErrors ) ) {
				$this->out->showPermissionsErrorPage( $permErrors );
				return;
			}

			$this->out->setPageTitle(
				$this->context->msg( 'newslettercreate', $this->title->getPrefixedText() )->text()
			);
			$this->getForm()->show();
		}
	}

	protected function getPermissionErrors() {
		$permManager = MediaWikiServices::getInstance()->getPermissionManager();
		$permErrors = $permManager->getPermissionErrors( 'edit', $this->user, $this->title );

		if ( $this->createNew ) {
			$permErrors = array_merge(
				$permErrors,
				wfArrayDiff2(
					$permManager->getPermissionErrors( 'create', $this->user, $this->title ),
					$permErrors
				)
			);
		}

		return $permErrors;
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

		$fields = [
			'MainPage' => [
				'type' => 'title',
				'label-message' => 'newsletter-manage-title',
				'default' => $mainTitle->getPrefixedText(),
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
			$oldRevision = null;
			// Editing a previous revision
			if ( $oldId ) {
				$oldRevision = Revision::newFromId( $oldId );
				if ( $oldRevision->getContentModel() === 'NewsletterContent'
					&& $oldRevision->getContent() !== null ) {
					$fields['Summary']['default'] = '';
				}
			} elseif /* Undoing the latest revision */ ( $revId && $undoId ) {
				$oldRevision = Revision::newFromId( $revId );
				$undoRevision = Revision::newFromId( $undoId );
				if ( $undoRevision->isCurrent()
					&& $undoRevision->getContentModel() === 'NewsletterContent'
					&& $undoRevision->getContent() !== null
				) {
					$fields['Summary']['default'] =
						$this->context->msg( 'undo-summary' )
							->params( $undoRevision->getId(), $undoRevision->getUserText() )
							->inContentLanguage()
							->text();
				} else /* User attempts to undo prior revision */ {
					throw new BadRequestError(
						'newsletter-oldrev-undo-error-title',
						'newsletter-oldrev-undo-error-body'
					);
				}
			}

			// Default fields are the same, regardless of action
			if ( $oldRevision && $oldRevision->getContentModel() === 'NewsletterContent'
				&& $oldRevision->getContent() !== null
			) {
				$content = $oldRevision->getContent();
				'@phan-var NewsletterContent $content';
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
		$form->addHeaderText(
			$this->context->msg( 'newsletter-manage-text' )
				->params( $this->newsletter->getName() )->parse()
		);
		$form->setId( 'newsletter-manage-form' );
		$form->setSubmitID( 'newsletter-manage-button' );
		$form->setSubmitTextMsg( 'newsletter-managenewsletter-button' );
		return $form;
	}

	protected function getForm() {
		$form = HTMLForm::factory(
			'ooui',
			$this->getFormFields(),
			$this->context
		);
		$form->setSubmitCallback( [ $this, 'attemptSave' ] );
		$form->setAction( $this->title->getLocalURL( 'action=edit' ) );
		$form->addHeaderText( $this->context->msg( 'newslettercreate-text' )->parseAsBlock() );
		// Retain query parameters (uselang etc)
		$params = array_diff_key(
			$this->context->getRequest()->getQueryValues(), [ 'title' => null ] );
		$form->addHiddenField( 'redirectparams', wfArrayToCgi( $params ) );
		$form->setSubmitTextMsg( 'newsletter-create-submit' );

		// @todo $form->addPostText( save/copyright warnings etc. );
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
		$dbr = wfGetDB( DB_REPLICA );
		$rows = $dbr->select(
			'nl_newsletters',
			[ 'nl_name', 'nl_main_page_id', 'nl_active' ],
			$dbr->makeList( [
				'nl_name' => $data['Name'],
				$dbr->makeList(
					[
						'nl_main_page_id' => $mainPageId,
						'nl_active' => 1
					], LIST_AND )
			], LIST_OR )
		);
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

		$store = NewsletterStore::getDefaultInstance();
		$this->newsletter = new Newsletter( 0,
			$data['Name'],
			// nl_newsletters.nl_desc is a blob but put some limit
			// here which is less than the max size for blobs
			$data['Description'],
			$mainPageId
		);
		$newsletterCreated = $store->addNewsletter( $this->newsletter );
		if ( $newsletterCreated ) {
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
				$this->newsletter->subscribe( $this->user );
				$store = NewsletterStore::getDefaultInstance();
				$store->addPublisher( $this->newsletter, [ $this->user->getId() ] );
				$this->out->addWikiMsg( 'newsletter-create-confirmation', $this->newsletter->getName() );
				return Status::newGood();
			} else {
				// The content creation was unsuccessful, lets rollback the newsletter from db
				$store->rollBackNewsletterAddition( $this->newsletter );
			}
		}

		// Couldn't insert to the DB..
		return Status::newFatal( 'newsletter-create-error' );
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

		$oldDescription = $this->newsletter->getDescription();
		$oldMainPage = $this->newsletter->getPageId();

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

		$mainPageId = $mainPage->getArticleID();

		$store = NewsletterStore::getDefaultInstance();
		$newsletterId = $this->newsletter->getId();

		$title = Title::makeTitleSafe( NS_NEWSLETTER, $this->newsletter->getName() );

		if ( $description != $oldDescription ) {
			$store->updateDescription( $newsletterId, $description );
			$modified = true;
		}
		if ( $oldMainPage != $mainPageId ) {
			$rows = $store->newsletterExistsForMainPage( $mainPageId );
			foreach ( $rows as $row ) {
				if ( (int)$row->nl_main_page_id === $mainPageId && (int)$row->nl_active === 1 ) {
					return Status::newFatal( 'newsletter-mainpage-in-use' );
				}
			}
			$store->updateMainPage( $newsletterId, $mainPageId );
			$modified = true;
		}

		$publisherNames = $data['Publishers'] ? explode( "\n", $data['Publishers'] ) : [];
		// Ask for confirmation before removing all the publishers
		if ( !$confirmed && count( $publisherNames ) === 0 ) {
			return Status::newFatal( 'newsletter-manage-no-publishers' );
		}

		/** @var User[] $newPublishers */
		$newPublishers = array_map( 'User::newFromName', $publisherNames );

		$oldPublishersIds = $this->newsletter->getPublishers();
		$newPublishersIds = self::getIdsFromUsers( $newPublishers );

		// Confirm whether the current user (if already a publisher)
		// wants to be removed from the publishers group
		$user = $this->user;
		if ( !$confirmed && $this->newsletter->isPublisher( $user )
			&& !in_array( $user->getId(), $newPublishersIds )
		) {
			return Status::newFatal( 'newsletter-manage-remove-self-publisher' );
		}

		// Do the actual modifications now
		$added = array_diff( $newPublishersIds, $oldPublishersIds );
		$removed = array_diff( $oldPublishersIds, $newPublishersIds );

		// Check if people has been added
		if ( $added ) {
			$store->addPublisher( $this->newsletter, $added );
			// Adds the new publishers to subscription list
			$store->addSubscription( $this->newsletter, $added );
			$this->newsletter->notifyPublishers(
				$added, $user, Newsletter::NEWSLETTER_PUBLISHERS_ADDED
			);
		}

		// Check if people have been removed
		if ( $removed ) {
			$store->removePublisher( $this->newsletter, $removed );
			$this->newsletter->notifyPublishers(
				$removed, $user, Newsletter::NEWSLETTER_PUBLISHERS_REMOVED
			);
		}

		// Now report to the user
		if ( $added || $removed || $modified ) {
			$this->out->addWikiMsg( 'newsletter-manage-newsletter-success' );
		} else {
			// Submitted without any changes to the existing publishers
			$this->out->addWikiMsg( 'newsletter-manage-newsletter-nochanges' );
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
