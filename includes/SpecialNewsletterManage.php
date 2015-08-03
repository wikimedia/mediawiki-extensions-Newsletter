<?php
/**
 * Special page for announcing issues and managing newsletters
 *
 */
class SpecialNewsletterManage extends SpecialPage {
	function __construct() {
		parent::__construct( 'NewsletterManage' );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$output = $this->getOutput();
		$this->requireLogin();
		$announceIssueArray = $this->getAnnounceFormFields();

		# Create HTML forms
		$announceIssueForm = new HTMLForm( $announceIssueArray, $this->getContext(), 'announceissueform' );
		$announceIssueForm->setSubmitCallback( array( 'SpecialNewsletterManage', 'onSubmitIssue' ) );

		# Show HTML forms
		$announceIssueForm->show();
		$output->returnToMain();
	}

	/**
	 * Function to generate Announce Issue form
	 *
	 * @return array
	 */
	protected function getAnnounceFormFields() {
		$newsletterNames = array();
		$newsletterIds = array();
		$defaultOption = array('' => null);
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'nl_publishers',
			array( 'newsletter_id' ),
			array( 'publisher_id' => $this->getUser()->getId() ),
			__METHOD__
		);

		foreach( $res as $row ) {
			$newsletterIds[$row->newsletter_id] = $row->newsletter_id;
		}

		foreach( $newsletterIds as $value ) {
			$resl = $dbr->select(
				'nl_newsletters',
				array( 'nl_name', 'nl_id' ),
				array( 'nl_id' => $value ),
				__METHOD__
			);

			foreach ($resl as $row) {
				$newsletterNames[$row->nl_name] = $row->nl_id;
			}
		}

		return array(
			'issue-newsletter' => array(
				'type' => 'select',
				'section' => 'announceissue-section',
				'label' => 'Name of newsletter',
				'options' => array_merge( $defaultOption, $newsletterNames ),
			),
			'issue-page' => array(
				'type' => 'text',
				'section' => 'announceissue-section',
				'label' => "Title of issue's main page",
			),
			'publisher' => array(
				'type' => 'hidden',
				'default' => $this->getUser()->getId()
			),
			'newsletter-name' => array(
				'type' => 'select',
				'section' => 'addpublisher-section',
				'label' => 'Name of newsletter',
				'options' => array_merge( $defaultOption, $newsletterNames )
			),
			'publisher-name' => array(
				'section' => 'addpublisher-section',
				'type' => 'text',
				'label' => "Username",
			)
		);
	}

	/**
	 * Perform insert query on issues table with data retrieved from HTML
	 * form for announcing issues
	 *
	 * @param array $formData The data entered by user in the form
	 * @return bool
	 */
	static function onSubmitIssue( $formData ) {
		$newsletterId = $formData['issue-newsletter'];
		if ( !empty( $formData['issue-page'] ) && !empty( $formData['issue-newsletter'] ) ) {
			$issuePage = Title::newFromText( $formData['issue-page'] );
			$pageId = $issuePage->getArticleId();
			$pageNamepace = $issuePage->getNamespace();
			//Array index is newsletter-id for selected newsletter in newsletterNames[] above
			if ( ( $pageId !== 0 ) && isset( $newsletterId ) && isset( $formData['publisher'] ) ) {
				//Find number of existing issues
				$dbr = wfGetDB( DB_SLAVE );
				$issueCount = $dbr->selectRowCount(
					'nl_issues',
					array( 'issue_id' ),
					array( 'issue_newsletter_id' => $newsletterId ),
					__METHOD__,
					array()
				);
				//inserting to database
				$dbw = wfGetDB( DB_MASTER );
				$rowData = array(
					'issue_id' => $issueCount + 1,
					'issue_page_id' => $pageId,
					'issue_newsletter_id' => $newsletterId,
					'issue_publisher_id' => $formData['publisher']
				);
				$dbw->insert( 'nl_issues', $rowData, __METHOD__ );
				RequestContext::getMain()->getOutput()->addWikiMsg( 'issue-announce-confirmation' );
				//trigger notifications
				$res = $dbr->select(
					'nl_newsletters',
					array( 'nl_name' ),
					array( 'nl_id' => $newsletterId ),
					__METHOD__,
					array()
				);

				$newsletterName = array();
				foreach( $res as $row ) {
					$newsletterName = $row->nl_name;
				}
				if ( class_exists( 'EchoEvent' ) ) {
					EchoEvent::create( array(
						'type' => 'subscribe-newsletter',
						'extra' => array(
							'newsletter' => $newsletterName,
							'newsletterId' => $newsletterId,
							'issuePageTitle' => $formData['issue-page'],
							'issuePageNamespace' => $pageNamepace
						),
					));
				}

				return true;
			} else {
				return 'The Newsletter issue page cannot be found. Please try again';
			}
		}

		if ( !empty( $formData['newsletter-name'] ) && !empty( $formData['publisher-name'] ) ) {
			$pubNewsletterId = $formData['newsletter-name'];
			$user = User::newFromName( $formData['publisher-name'] );
			if ( $user->isEmailConfirmed() ) {
				$dbw = wfGetDB(DB_MASTER);
				$rowData = array(
					'newsletter_id' => $pubNewsletterId,
					'publisher_id' => $user->getId()
				);
				try {
					$dbw->insert( 'nl_publishers', $rowData, __METHOD__ );
					RequestContext::getMain()->getOutput()->addWikiMsg( 'new-publisher-confirmation' );
					return true;
				} catch ( DBQueryError $e ) {
					return "Invalid username";
				}
			} else {
				return "The provided username does not have a confirmed email address!";
			}

		}

		return "Required fields are empty.";

	}
}