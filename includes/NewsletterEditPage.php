<?php

class NewsletterEditPage {

	/** @var IContextSource */
	protected $context;

	/** @var User */
	protected $user;

	/** @var Title */
	protected $title;

	/** @var OutputPage */
	protected $out;

	/** @var bool */
	protected $isNew;

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

		$this->isNew = !$this->title->exists();

		$permErrors = $this->getPermissionErrors();
		if ( count( $permErrors ) ) {
			$this->out->showPermissionsErrorPage( $permErrors );
			return;
		}

		$this->out->setPageTitle( $this->getHeaderMsg() );
		$this->out->setRobotPolicy( 'noindex,nofollow' );
		if ( !$this->isNew ) {
			$this->out->addBacklinkSubtitle( $this->title );
		}
		$this->getForm()->show();

		// TODO This implementation requires the following features added
		// readonly
		// block
		// ratelimit
		// check existing
		// permissions
		// intro
		// form
	}

	protected function getHeaderMsg() {
		$key = $this->isNew ? 'newslettercreate-header' : 'newsletteredit-header';
		return $this->context->msg( $key, $this->title->getPrefixedText() )->text();
	}

	protected function getPermissionErrors() {
		$rigor = 'secure';
		$permErrors = $this->title->getUserPermissionsErrors( 'edit', $this->user, $rigor );

		if ( $this->isNew ) {
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
		$form->setAction( $this->title->getLocalURL( 'action=edit' ) );
		$form->showCancel();
		$form->setCancelTarget( $this->title );

		// Retain query parameters (uselang etc)
		$params = array_diff_key(
			$this->context->getRequest()->getQueryValues(),
			array( 'title' => null )
		);
		$form->addHiddenField( 'redirectparams', wfArrayToCgi( $params ) );

		if ( $this->isNew ) {
			$this->setupCreateForm( $form );
		} else {
			$this->setupEditForm( $form );
		}
		// @todo $form->addPostText( save/copyright warnings etc. );

		return $form;
	}

	protected function setupCreateForm( HTMLForm $form ) {
		$form->addHeaderText( $this->context->msg( 'newslettercreate-text' )->parseAsBlock() );
		$form->setSubmitCallback( array( $this, 'attemptCreate' ) );
		$form->setSubmitTextMsg( 'newsletter-create-submit' );
	}

	protected function setupEditForm( HTMLForm $form ) {
		$form->addHeaderText( $this->context->msg( 'newslettercreate-text' )->parseAsBlock() );
		$form->setSubmitCallback( array( $this, 'attemptEdit' ) );
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
			// @todo Add summary field
		);
	}

	/**
	 * Do input validation, error handling and create a new newletter.
	 *
	 * @param array $input The data entered by user in the form
	 * @throws ThrottledError
	 * @return Status
	 */
	public function attemptCreate( array $input ) {
		global $wgContLang;

		// @todo Implement edit conflict check
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
			$this->newsletter->subscribe( $this->user );
			NewsletterStore::getDefaultInstance()->addPublisher( $this->newsletter, $this->user );

			$this->out->addWikiMsg( 'newsletter-create-confirmation', $this->newsletter->getId() );

			return Status::newGood();
		}

		// Couldn't insert to the DB..
		return Status::newFatal( 'newsletter-create-error' );
	}
}
