<?php

/**
 * Special page for creating newsletters
 *
 * @license GNU GPL v2+
 * @author Tina Johnson
 */
class SpecialNewsletterCreate extends FormSpecialPage {


	public function __construct() {
		parent::__construct( 'NewsletterCreate' );
	}

	public function execute( $par ) {
		$this->requireLogin();
		parent::execute( $par );
		$this->getOutput()->setSubtitle( LinksGenerator::getSubtitleLinks() );
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
				'cols' => 50,
				'maxlength' => 767
			),
			'mainpage' => array(
				'required' => true,
				'type' => 'text',
				'label-message' => 'newsletter-title',
			),
			'frequency' => array(
				'required' => true,
				'type' => 'selectorother',
				'label-message' => 'newsletter-frequency',
				'options' => array(
					'weekly' => $this->msg( 'newsletter-option-weekly' ),
					'monthly' => $this->msg( 'newsletter-option-monthly' ),
					'quarterly' => $this->msg( 'newsletter-option-quarterly' ),
				),
				'size' => 18, # size of 'other' field
				'maxlength' => 50,
			)
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
			return array( 'newsletter-create-mainpage-invalid' );
		}

		$articleId = $mainTitle->getArticleId();
		if ( !$articleId ) {
			return array( 'newsletter-mainpage-not-found-error' );
		}

		if ( isset( $data['name'] ) &&
			isset( $data['description'] ) &&
			( $articleId !== 0 ) &&
			isset( $data['mainpage'] ) &&
			isset( $data['frequency'] )
		) {
			$db = NewsletterDb::newFromGlobalState();
			$newsletterAdded = $db->addNewsletter(
				trim( $data['name'] ),
				$data['description'],
				$articleId,
				$data['frequency']
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
