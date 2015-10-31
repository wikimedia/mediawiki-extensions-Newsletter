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

	public function execute( $par ) {
		$this->setHeaders();
		$output = $this->getOutput();
		$output->addModules( 'ext.newslettermanage' );
		$output->setSubtitle( NewsletterLinksGenerator::getSubtitleLinks() );
		$this->requireLogin();

		# Create HTML forms
		$announceIssueForm = new HTMLForm(
			$this->getAnnounceFormFields(),
			$this->getContext(),
			'newsletter-announceissueform'
		);
		$announceIssueForm->setSubmitCallback( array( $this, 'onSubmitIssue' ) );

		$pager = new NewsletterManageTablePager();
		if ( $pager->getNumRows() > 0 ) {
			$output->addParserOutput( $pager->getFullOutput() );
			// Show HTML forms
			$announceIssueForm->show();

			if ( $this->getUser()->isAllowed( 'newsletter-addpublisher' ) ) {
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
	 * Function to generate Announce Issue form
	 *
	 * @return array
	 */
	private function getAnnounceFormFields() {
		$db = NewsletterDb::newFromGlobalState();
		$userPublishedNewsletters = $db->getNewslettersUserIsPublisherOf( $this->getUser() );

		$newsletterNameMap = array();
		foreach ( $userPublishedNewsletters as $newsletter ) {
			$newsletterNameMap[$newsletter->getName()] = $newsletter->getId();
		}

		return array(
			'issue-newsletter' => array(
				'type' => 'select',
				'section' => 'announceissue-section',
				'label-message' => 'newsletter-name',
				'options' => array( $this->msg( 'newsletter-dropdown-default-message' )->text() => null ) + $newsletterNameMap,
			),
			'issue-page' => array(
				'type' => 'text',
				'section' => 'announceissue-section',
				'label-message' => 'newsletter-issue-title',
			),
			'publisher' => array(
				'type' => 'hidden',
				'default' => $this->getUser()->getId(),
			),
		);
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
				'type' => 'text',
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
	 * @return array|bool true on success, array on error
	 * @throws MWException
	 */
	public function onSubmitIssue( $formData ) {
		if ( !isset( $formData['issue-page'] ) || !isset( $formData['issue-newsletter'] ) ) {
			// This is not the form that was submitted
			return false;
		}

		if ( !empty( $formData['issue-page'] ) && !empty( $formData['issue-newsletter'] ) ) {
			$newsletterId = $formData['issue-newsletter'];
			$issuePage = Title::newFromText( $formData['issue-page'] );
			$pageId = $issuePage->getArticleId();
			$pageNamepace = $issuePage->getNamespace();

			// Array index is newsletter-id for selected newsletter in newsletterNames[] above
			if ( ( $pageId !== 0 ) && isset( $newsletterId ) && isset( $formData['publisher'] ) ) {
				$db = NewsletterDb::newFromGlobalState();
				$issueCreated = $db->addNewsletterIssue(
					$newsletterId,
					$pageId,
					$formData['publisher']
				);

				if ( !$issueCreated ) {
					// TODO better output message here with i18n....
					throw new MWException( 'Failed to create newsletter issue' );
				}
				$this->getOutput()->addWikiMsg( 'newsletter-issue-announce-confirmation' );

				$newsletter = $db->getNewsletter( $newsletterId );

				if ( class_exists( 'EchoEvent' ) ) {
					EchoEvent::create(
						array(
							'type' => 'newsletter-announce',
							'extra' => array(
								'newsletter' => $newsletter->getName(),
								'newsletterId' => $newsletter->getId(),
								'issuePageTitle' => $formData['issue-page'],
								'issuePageNamespace' => $pageNamepace,
							),
						)
					);
				}

				return true;
			} else {
				return array( 'newsletter-issuepage-not-found-error' );
			}
		}

		return array( 'newsletter-required-fields-error' );
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
