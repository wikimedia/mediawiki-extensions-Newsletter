<?php

/**
 * Special page for announcing issues and managing newsletters
 *
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
		$output->setSubtitle( LinksGenerator::getSubtitleLinks() );
		$this->requireLogin();

		# Create HTML forms
		$announceIssueForm = new HTMLForm(
			$this->getAnnounceFormFields(),
			$this->getContext(),
			'newsletter-announceissueform'
		);
		$announceIssueForm->setSubmitCallback( array( $this, 'onSubmitIssue' ) );
		$addPublisherForm = new HTMLForm(
			$this->getPublisherFormFields(),
			$this->getContext(),
			'newsletter-addpublisherform'
		);
		$addPublisherForm->setSubmitCallback( array( $this, 'onSubmitPublisher' ) );

		$pager = new NewsletterManageTablePager();
		if ( $pager->getNumRows() > 0 ) {
			$output->addParserOutput( $pager->getFullOutput() );
			// Show HTML forms
			$announceIssueForm->show();
			$addPublisherForm->show();
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
		$dbr = wfGetDB( DB_SLAVE );

		$db = NewsletterDb::newFromGlobalState();
		$userPublishedNewsletters = $db->getNewsletterIdsForPublisher(
			$this->getUser()->getId()
		);

		$newsletterNames = array();
		foreach ( $userPublishedNewsletters as $value ) {
			$resl = $dbr->select(
				'nl_newsletters',
				array( 'nl_name', 'nl_id' ),
				array( 'nl_id' => $value ),
				__METHOD__
			);

			foreach ( $resl as $row ) {
				$newsletterNames[$row->nl_name] = $row->nl_id;
			}
		}

		return array(
			'issue-newsletter' => array(
				'type' => 'select',
				'section' => 'announceissue-section',
				'label-message' => 'newsletter-name',
				'options' => array_merge( array( '' => null ), $newsletterNames ),
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
		// Get newsletters owned by the logged in user
		$dbr = wfGetDB( DB_SLAVE );
		$query = $dbr->select(
			'nl_newsletters',
			array( 'nl_name', 'nl_id' ),
			array( 'nl_owner_id' => $this->getUser()->getId() ),
			__METHOD__,
			array()
		);

		$ownedNewsletter = array();
		foreach ( $query as $row ) {
			$ownedNewsletter[$row->nl_name] = $row->nl_id;
		}

		return array(
			'newsletter-name' => array(
				'type' => 'select',
				'section' => 'addpublisher-section',
				'label-message' => 'newsletter-name',
				'options' => array_merge( array( '' => null ), $ownedNewsletter ),
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
	 * @return bool|array true on success, array on error
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
				// Find number of existing issues
				$dbr = wfGetDB( DB_SLAVE );
				$issueCount = $dbr->selectRowCount(
					'nl_issues',
					array( 'issue_id' ),
					array( 'issue_newsletter_id' => $newsletterId ),
					__METHOD__,
					array()
				);
				// inserting to database
				$dbw = wfGetDB( DB_MASTER );
				$rowData = array(
					'issue_id' => $issueCount + 1,
					'issue_page_id' => $pageId,
					'issue_newsletter_id' => $newsletterId,
					'issue_publisher_id' => $formData['publisher'],
				);
				$dbw->insert( 'nl_issues', $rowData, __METHOD__ );
				$this->getOutput()->addWikiMsg( 'newsletter-issue-announce-confirmation' );
				// trigger notifications
				$res = $dbr->select(
					'nl_newsletters',
					array( 'nl_name' ),
					array( 'nl_id' => $newsletterId ),
					__METHOD__,
					array()
				);

				$newsletterName = null;
				foreach ( $res as $row ) {
					$newsletterName = $row->nl_name;
				}
				if ( class_exists( 'EchoEvent' ) ) {
					EchoEvent::create(
						array(
							'type' => 'subscribe-newsletter',
							'extra' => array(
								'newsletter' => $newsletterName,
								'newsletterId' => $newsletterId,
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
