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
	 * Function to generate Create Newsletter Form
	 *
	 * @return array
	 */
	protected function getFormFields() {
		return array(
			'name' => array(
				'type' => 'text',
				'required' => true,
				'label-message' => 'newsletter-name',
			),
			'description' => array(
				'type' => 'textarea',
				'required' => true,
				'label-message' => 'newsletter-desc',
				'rows' => 15,
				'cols' => 50,
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
			),
			// @todo FIXME: this shouldn't be a form field
			'publisher' => array(
				'type' => 'hidden',
				'default' => $this->getUser()->getId(),
			),
		);
	}

	/**
	 * Perform insert query on newsletter table with data retrieved from HTML
	 * form for creating newsletters
	 *
	 * @param array $formData The data entered by user in the form
	 *
	 * @return bool|array true on success, array on error
	 */
	public function onSubmit( array $formData ) {
		if ( isset( $formData['mainpage'] ) ) {
			$page = Title::newFromText( $formData['mainpage'] );
			$pageId = $page->getArticleId();
		} else {
			return array( 'newsletter-create-mainpage-error' );
		}
		if ( isset( $formData['name'] ) &&
			isset( $formData['description'] ) &&
			( $pageId !== 0 ) &&
			isset( $formData['mainpage'] ) &&
			isset( $formData['frequency'] ) &&
			isset( $formData['publisher'] )
		) {
			$db = NewsletterDb::newFromGlobalState();
			$newsletterAdded = $db->addNewsletter(
				$formData['name'],
				$formData['description'],
				$pageId,
				$formData['frequency'],
				$formData['publisher']
			);

			if ( !$newsletterAdded ) {
				return array( 'newsletter-exist-error' );
			}

			$this->getOutput()->addWikiMsg( 'newsletter-create-confirmation' );

			$newsletter = $db->getNewsletterForPageId( $pageId );

			$this->autoSubscribe( $newsletter->getId(), $formData['publisher'] );

			return true;
		}

		return array( 'newsletter-mainpage-not-found-error' );
	}

	/**
	 * Automatically subscribe and add owner as publisher of the newsletter
	 *
	 * @param int $newsletterId Id of the newsletter
	 * @param int $ownerId User Id of the owner
	 */
	private function autoSubscribe( $newsletterId, $ownerId ) {
		$db = NewsletterDb::newFromGlobalState();
		$db->addPublisher( $ownerId, $newsletterId );
		$db->addSubscription( $ownerId, $newsletterId );
	}

}
