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
		$this->getOutput()->setSubtitle( NewsletterLinksGenerator::getSubtitleLinks( $this->getContext() ) );
	}

	/**
	 * @param HTMLForm $form
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->setSubmitTextMsg( 'newsletter-create-submit' );
		$form->setWrapperLegendMsg( 'newsletter-create-section' );
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
				'maxlength' => 50
			),
			'description' => array(
				'type' => 'textarea',
				'required' => true,
				'label-message' => 'newsletter-desc',
				'rows' => 15,
				'maxlength' => 767
			),
			'mainpage' => array(
				'type' => 'title',
				'exists' => true,
				'required' => true,
				'label-message' => 'newsletter-title',
			),
		);
	}

	/**
	 * Do input validation, error handling and create a new newletter.
	 *
	 * @param array $data The data entered by user in the form
	 * @return bool|array true on success, array on error
	 */
	public function onSubmit( array $data ) {

		$mainTitle = Title::newFromText( $data['mainpage'] );
		if ( !$mainTitle ) {
			// HTMLTitleTextField should do validation but we can't be sure about
			// it so let's check again here - otherwise this may throw fatals below
			return array( 'newsletter-create-mainpage-error' );
		}

		$articleId = $mainTitle->getArticleId();

		if ( isset( $data['name'] ) &&
			isset( $data['description'] ) &&
			( $articleId !== 0 ) &&
			isset( $data['mainpage'] )
		) {
			$db = NewsletterDb::newFromGlobalState();
			$newsletterAdded = $db->addNewsletter(
				trim( $data['name'] ),
				$data['description'],
				$articleId
			);

			if ( !$newsletterAdded ) {
				return array( 'newsletter-exist-error' );
			}

			$newsletter = $db->getNewsletterForPageId( $articleId );

			$this->autoSubscribe( $newsletter->getId(), $this->getUser()->getId() );

			return true;
		}

		// Could not insert - newsletter by this name already exists
		return array( 'newsletter-exist-error' );

	}

	protected function getDisplayFormat() {
		return 'ooui';
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'newsletter-create-confirmation' );
	}


	/**
	 * Automatically subscribe and add creator as publisher of the newsletter
	 *
	 * @param int $newsletterId Id of the newsletter
	 * @param int $userID User Id of the publisher
	 */
	private function autoSubscribe( $newsletterId, $userID ) {
		$db = NewsletterDb::newFromGlobalState();
		$db->addPublisher( $userID, $newsletterId );
		$db->addSubscription( $userID, $newsletterId );
	}

}
