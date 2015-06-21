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
		$this->requireLogin( 'requiredlogintext' );
		$announceIssueArray = $this->getAnnounceFormFields();

		# Create HTML forms
		$announceIssueForm = new HTMLForm( $announceIssueArray, $this->getContext(), 'announceissueform' );
		$announceIssueForm->setSubmitText( 'Announce issue' );
		$announceIssueForm->setSubmitCallback( array( 'SpecialNewsletterManage', 'onSubmitIssue' ) );
		$announceIssueForm->setWrapperLegendMsg( 'announceissue-section' );

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
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'nl_newsletters',
			array( 'nl_name', 'nl_id' ),
			'',
			__METHOD__
		);
		$newsletterNames = array();
		foreach( $res as $row ) {
			$newsletterNames[$row->nl_name] = $row->nl_id;
		}

		return array(
			'issue-newsletter' => array(
				'type' => 'select',
				'label' => 'Name of newsletter',
				'options' => $newsletterNames,
				'required' => true
			),
			'issue-page' => array(
				'type' => 'text',
				'label' => "Title of issue's main page",
				'required' => true
			),
			'publisher' => array(
				'type' => 'hidden',
				'default' => $this->getUser()->getId()
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
		global $wgOut;

		if ( isset( $formData['issue-page'] ) && isset( $formData['issue-newsletter'] ) ) {
			$issuePage = Title::newFromText( $formData['issue-page'] );
			$pageId = $issuePage->getArticleId();
			//Array index is newsletter-id for selected newsletter in newsletterNames[] above
			$newsletterId = $formData['issue-newsletter'];
		} else {
			return 'Unknown Newsletter selected. Please try again';
		}
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
			$wgOut->addWikiMsg( 'issue-announce-confirmation' );

			return true;
		}

		return 'The Newsletter issue page cannot be found. Please try again';
	}
}