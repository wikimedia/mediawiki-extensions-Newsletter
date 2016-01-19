<?php

/**
 * Special page for announcing issues and managing newsletters
 *
 * @todo Merge functionality with SpecialNewsletter
 * @license GNU GPL v2+
 * @author Tina Johnson
 */
class SpecialNewsletterManage extends SpecialPage {

	public function __construct() {
		parent::__construct( 'NewsletterManage' );
	}

	public function doesWrites() {
		return true;
	}

	public function execute( $par ) {
		$this->setHeaders();
		$output = $this->getOutput();
		$output->addModules( 'ext.newslettermanage' );
		$output->setSubtitle( NewsletterLinksGenerator::getSubtitleLinks( $this->getContext() ) );
		$this->requireLogin();

		$pager = new NewsletterManageTablePager();
		if ( $pager->getNumRows() > 0 ) {
			$output->addParserOutput( $pager->getFullOutput() );
			// Show HTML forms

			if ( $this->getUser()->isAllowed( 'newsletter-manage' ) ) {
				// The user have required permissions
				$addPublisherForm = new HTMLForm(
					$this->getPublisherFormFields(),
					$this->getContext(),
					'newsletter-addpublisherform'
				);
				$addPublisherForm->setSubmitCallback( array( $this, 'onSubmitPublisher' ) );
				$addPublisherForm->show();
			}

		} else {
			$output->showErrorPage( 'newslettermanage', 'newsletter-none-found' );
		}

	}

	/**
	 * Function to generate Add Publisher form
	 *
	 * @return array
	 */
	private function getPublisherFormFields() {
		$db = NewsletterDb::newFromGlobalState();
		$newsletters = array();
		foreach ( $db->getAllNewsletters() as $newsletter ) {
			$newsletters[$newsletter->getName()] = $newsletter->getId();
		}

		return array(
			'newsletter-name' => array(
				'type' => 'select',
				'section' => 'addpublisher-section',
				'label-message' => 'newsletter-name',
				'options' => array( $this->msg( 'newsletter-dropdown-default-message' )->text() => null )
					+ $newsletters,
			),
			'publisher-name' => array(
				'section' => 'addpublisher-section',
				'type' => 'user',
				'label-message' => 'newsletter-publisher-username',
			),
		);
	}

	/**
	 * Perform insert query on issues table with data retrieved from HTML
	 * form for announcing issues
	 *
	 * @param array $formData The data entered by user in the form
	 *
	 * @return bool|array true on success, array on error
	 */
	public function onSubmitPublisher( $formData ) {
		if ( !isset( $formData['newsletter-name'] ) || !isset( $formData['publisher-name'] ) ) {
			// This is not the form that was submitted
			return false;
		}

		if ( !empty( $formData['newsletter-name'] ) && !empty( $formData['publisher-name'] ) ) {
			$pubNewsletterId = $formData['newsletter-name'];
			$user = User::newFromName( $formData['publisher-name'] );

			if ( !$user || $user->isAnon() ) {
				return array( 'newsletter-invalid-username-error' );
			}

			if ( !$user->isEmailConfirmed() ) {
				return array( 'newsletter-unconfirmed-email-error' );
			}

			$db = NewsletterDb::newFromGlobalState();
			$db->addPublisher( $user->getId(), $pubNewsletterId );
			$db->addSubscription( $user->getId(), $pubNewsletterId );

			$this->getOutput()->addWikiMsg( 'newsletter-new-publisher-confirmation' );

			return true;
		}

		return array( 'newsletter-required-fields-error' );
	}

}
