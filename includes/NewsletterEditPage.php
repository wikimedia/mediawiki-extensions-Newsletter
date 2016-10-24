<?php

class NewsletterEditPage {

	protected $context;

	protected $readOnly = false;

	protected $newsletter;

	public function __construct( IContextSource $context ) {
		$this->context = $context;
		$this->user = $context->getUser();
		$this->title = $context->getTitle();
		$this->out = $context->getOutput();
	}

	public function edit() {
		if ( wfReadOnly() ) {
			throw new ReadOnlyError;
		}

		$this->createNew = !$this->title->exists();

		$permErrors = $this->getPermissionErrors();
		if ( count( $permErrors ) ) {
			$this->out->showPermissionsErrorPage( $permErrors );
			return;
		}

		$this->out->setPageTitle( $this->context->msg( 'newslettercreate', $this->title->getPrefixedText() )->text() );
		$this->getForm()->show();

		// TODO more things here
		// block
		// ratelimit
		// check existing
		// add subtitle link
		// intro
		// form
	}

	protected function getPermissionErrors() {
		$rigor = 'secure';
		$permErrors = $this->title->getUserPermissionsErrors( 'edit', $this->user, $rigor );

		if ( $this->createNew ) {
			$permErrors = array_merge(
				$permErrors,
				wfArrayDiff2(
					$this->title->getUserPermissionsErrors( 'create', $this->user, $rigor ),
					$permErrors
				)
			);
		}

		return $permErrors;
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
		return array(
			'name' => array(
				'type' => 'text',
				'required' => true,
				'label-message' => 'newsletter-name',
				'maxlength' => 120,
				'default' => $this->title->getText(),
			),
			'mainpage' => array(
				'type' => 'title',
				'required' => true,
				'label-message' => 'newsletter-title',
			),
			'description' => array(
				'type' => 'textarea',
				'required' => true,
				'label-message' => 'newsletter-desc',
				'rows' => 15,
				'maxlength' => 600000,
			),
		);
	}

	/**
	 * Do input validation, error handling and create a new newletter.
	 *
	 * @param array $input The data entered by user in the form
	 * @throws ThrottledError
	 * @return Status
	 */
	public function attemptSave( array $input ) {
		global $wgContLang;

		$data = array(
			'Name' => trim( $input['name'] ),
			'Description' => trim( $input['description'] ),
			'MainPage' => Title::newFromText( $input['mainpage'] ),
		);

		$validator = new NewsletterValidator( $data );
		$validation = $validator->validate();
		if ( !$validation->isGood() ) {
			// Invalid input was entered
			return $validation;
		}

		$mainPageId = $data['MainPage']->getArticleID();

		$dbr = wfGetDB( DB_SLAVE );
		$rows = $dbr->select(
			'nl_newsletters',
			array( 'nl_name', 'nl_main_page_id' ),
			$dbr->makeList(
				array(
					'nl_name' => $data['Name'],
					'nl_main_page_id' => $mainPageId,
				),
				LIST_OR
			)
		);
		// Check whether another existing newsletter has the same name or main page
		foreach ( $rows as $row ) {
			if ( $row->nl_name === $data['Name'] ) {
				return Status::newFatal( 'newsletter-exist-error', $data['Name'] );
			} elseif ( (int)$row->nl_main_page_id === $mainPageId ) {
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
			$wgContLang->truncate( $data['Description'], 600000 ),
			$mainPageId
		);
		$newsletterCreated = $store->addNewsletter( $this->newsletter );
		if ( $newsletterCreated ) {
			$title = Title::makeTitleSafe( NS_NEWSLETTER, trim( $data['Name'] ) );
			$editSummaryMsg = $this->context->msg( 'newsletter-create-editsummary' );
			$result = NewsletterContentHandler::edit(
				$title,
				$data['Description'],
				$input['mainpage'],
				array( $this->user->getName() ),
				$editSummaryMsg->inContentLanguage()->plain(),
				$this->context
			);
			if ( $result->isGood() ) {
				$this->newsletter->subscribe( $this->user );
				NewsletterStore::getDefaultInstance()->addPublisher( $this->newsletter, $this->user );
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
}