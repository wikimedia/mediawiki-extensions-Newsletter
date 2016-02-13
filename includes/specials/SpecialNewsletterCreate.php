<?php

/**
 * Special page for creating newsletters
 *
 * @license GNU GPL v2+
 * @author Tina Johnson
 */
class SpecialNewsletterCreate extends FormSpecialPage {

	public function __construct() {
		parent::__construct( 'NewsletterCreate', 'newsletter-create' );
	}

	public function execute( $par ) {
		$this->requireLogin();
		parent::execute( $par );
		$this->getOutput()->setSubtitle(
			NewsletterLinksGenerator::getSubtitleLinks( $this->getContext() )
		);
	}

	/**
	 * @param HTMLForm $form
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->setSubmitTextMsg( 'newsletter-create-submit' );
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
				'maxlength' => 120
			),
			'description' => array(
				'type' => 'textarea',
				'required' => true,
				'label-message' => 'newsletter-desc',
				'rows' => 15,
				'maxlength' => 600000,
			),
			'mainpage' => array(
				'type' => 'title',
				'required' => true,
				'label-message' => 'newsletter-title',
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
	public function onSubmit( array $input ) {
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
		foreach( $rows as $row ) {
			if ( $row->nl_name === $data['Name'] ) {
				return Status::newFatal( 'newsletter-exist-error', $data['Name'] );
			} elseif ( (int)$row->nl_main_page_id === $mainPageId ) {
				return Status::newFatal( 'newsletter-mainpage-in-use' );
			}
		}

		$user = $this->getUser();
		if ( $user->pingLimiter( 'newsletter' ) ) {
			// Default user access level for creating a newsletter is quite low
			// so add a throttle here to prevent abuse (eg. mass vandalism spree)
			throw new ThrottledError;
		}

		$ndb = NewsletterDb::newFromGlobalState();
		$newsletterCreated = $ndb->addNewsletter(
			$data['Name'],
			// nl_newsletters.nl_desc is a blob but put some limit
			// here which is less than the max size for blobs
			$wgContLang->truncate( $data['Description'], 600000 ),
			$mainPageId
		);

		if ( $newsletterCreated ) {
			$newsletter = $ndb->getNewsletterForPageId( $mainPageId );
			$this->onPostCreation( $newsletter->getId(), $user->getId() );

			return Status::newGood();
		}

		// Couldn't insert to the DB..
		return Status::newFatal( 'newsletter-create-error' );
	}

	/**
	 * Automatically subscribe and add creator as publisher of the newsletter
	 *
	 * @param int $newsletterId Id of the newsletter
	 * @param int $userID User Id of the publisher
	 */
	private function onPostCreation( $newsletterId, $userID ) {
		$db = NewsletterDb::newFromGlobalState();
		$db->addPublisher( $userID, $newsletterId );
		$db->addSubscription( $userID, $newsletterId );
	}

	public function onSuccess() {
		// @todo Link to corresponding Special:Newsletter page
		$this->getOutput()->addWikiMsg( 'newsletter-create-confirmation' );
	}

	protected function getDisplayFormat() {
		return 'ooui';
	}
}
