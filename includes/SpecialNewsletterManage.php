<?php
/**
 * Special page for announcing issues and managing newsletters
 *
 */
class SpecialNewsletterManage extends SpecialPage {
	static $fields = array(
		'newsletter_id' => 'name',
		'publisher_id' => 'publisher',
		'permissions' => 'permissions',
		'action' => 'action'
	);

	function __construct() {
		parent::__construct( 'NewsletterManage' );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$output = $this->getOutput();
		$this->getOutput()->addModules( 'ext.newsletter' );
		$this->getOutput()->addModules( 'ext.newslettermanage' );
		$this->requireLogin();
		$announceIssueArray = $this->getAnnounceFormFields();

		# Create HTML forms
		$announceIssueForm = new HTMLForm( $announceIssueArray, $this->getContext(), 'newsletter-announceissueform' );
		$announceIssueForm->setSubmitCallback( array( 'SpecialNewsletterManage', 'onSubmitIssue' ) );

		$table = new NewsletterManageTable();
		if ( $table->getNumRows() > 0 ) {
			$output->addHTML(
				$table->getNavigationBar() .
				$table->getBody() .
				$table->getNavigationBar()
			);
		}
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
		$ownedNewsletter = array();
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

			foreach ( $resl as $row ) {
				$newsletterNames[$row->nl_name] = $row->nl_id;
			}
		}

		$newsletters = array();
		$result = $dbr->select(
			'nl_newsletters',
			array( 'nl_name','nl_id' ),
			array( 'nl_owner_id' => $this->getUser()->getId() ),
			__METHOD__
		);
		foreach ( $result as $row ) {
			$newsletters[$row->nl_name] = $row->nl_id;
		}
		//Get newsletters owned by the logged in user
		$dbr = wfGetDB( DB_SLAVE );
		$query = $dbr->select(
			'nl_newsletters',
			array( 'nl_name', 'nl_id'),
			array( 'nl_owner_id' => $this->getUser()->getId() ),
			__METHOD__,
			array()
		);

		foreach ($query as $row) {
			$ownedNewsletter[$row->nl_name] = $row->nl_id;
		}

		return array(
			'issue-newsletter' => array(
				'type' => 'select',
				'section' => 'announceissue-section',
				'label' => $this->msg( 'newsletter-name' ),
				'options' => array_merge( $defaultOption, $newsletterNames ),
			),
			'issue-page' => array(
				'type' => 'text',
				'section' => 'announceissue-section',
				'label' => $this->msg( 'newsletter-issue-title' ),
			),
			'publisher' => array(
				'type' => 'hidden',
				'default' => $this->getUser()->getId()
			),
			'newsletter-name' => array(
				'type' => 'select',
				'section' => 'addpublisher-section',
				'label' => $this->msg( 'newsletter-name' ),
				'options' => array_merge( $defaultOption, $ownedNewsletter ),
			),
			'publisher-name' => array(
				'section' => 'addpublisher-section',
				'type' => 'text',
				'label' => $this->msg( 'newsletter-publisher-username' ),
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
				RequestContext::getMain()->getOutput()->addWikiMsg( 'newsletter-issue-announce-confirmation' );
				//trigger notifications
				$res = $dbr->select(
					'nl_newsletters',
					array( 'nl_name' ),
					array( 'nl_id' => $newsletterId ),
					__METHOD__,
					array()
				);

				$newsletterName = null;
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
				return RequestContext::getMain()->msg( 'newsletter-issuepage-not-found-error' );
			}
		}

		if ( !empty( $formData['newsletter-name'] ) && !empty( $formData['publisher-name'] ) ) {
			$pubNewsletterId = $formData['newsletter-name'];
			$user = User::newFromName( $formData['publisher-name'] );
			if ( $user->isEmailConfirmed() ) {
				$dbww = wfGetDB( DB_MASTER );
				$rowData = array(
					'newsletter_id' => $pubNewsletterId,
					'publisher_id' => $user->getId()
				);
				try {
					$dbww->insert('nl_publishers', $rowData, __METHOD__);
					RequestContext::getMain()->getOutput()->addWikiMsg( 'newsletter-new-publisher-confirmation' );

					return true;
				} catch ( DBQueryError $e ) {
					return RequestContext::getMain()->msg( 'newsletter-invalid-username-error' );
				}
			} else {
				return 	RequestContext::getMain()->msg( 'newsletter-unconfirmed-email-error' );
			}

		}

		return RequestContext::getMain()->msg( 'newsletter-required-fields-error' );

	}
}

class NewsletterManageTable extends TablePager {
	static $newsletterOwners = array();

	function getFieldNames() {
		$header = null;
		if ( is_null( $header ) ) {
			$header = array();
			foreach ( SpecialNewsletterManage::$fields as $key => $value ) {
				$header[$key]= $this->msg( "newsletter-manage-header-$value" )->text();
			}
		}

		return $header;

	}

	function getQueryInfo() {
		$info = array(
			'tables' => array( 'nl_publishers' ),
			'fields' => array(
				'newsletter_id',
				'publisher_id'
			)
		);

		//get user ids of all newsletter owners
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'nl_newsletters',
			array( 'nl_owner_id', 'nl_id' ),
			array(),
			__METHOD__,
			array( 'DISTINCT' )
		);
		foreach( $res as $row ) {
			self::$newsletterOwners[$row->nl_id] = $row->nl_owner_id;
		}

		return $info;
	}

	function formatValue( $field, $value ) {
		static $previous;

		switch( $field ) {
			case 'newsletter_id':
					if( $previous === $value ){

						return null;
					} else {
						$dbr = wfGetDB( DB_SLAVE );
						$res = $dbr->select(
							'nl_newsletters',
							array( 'nl_name' ),
							array( 'nl_id' => $value ),
							__METHOD__,
							array()
						);
						$newsletterName = null;
						foreach( $res as $row ) {
							$newsletterName = $row->nl_name;
						}
						$previous = $value;

						return $newsletterName;
					}
			case 'publisher_id' :
					$user = User::newFromId( $value );

					return $user->getName();
			case 'permissions' :
					$radioOwner = HTML::element(
						'input',
						array(
							'type' => 'checkbox',
							'disabled' => 'true',
							'id' => 'newslettermanage',
							'checked' => self::$newsletterOwners[$this->mCurrentRow->newsletter_id]
								=== $this->mCurrentRow->publisher_id ? true : false,						)
					) . $this->msg( 'newsletter-owner-radiobutton-label' );

					$radioPublisher = HTML::element(
						'input',
						array(
							'type' => 'checkbox',
							'disabled' => 'true',
							'id' => 'newslettermanage',
							'checked' => self::$newsletterOwners[$this->mCurrentRow->newsletter_id]
								=== $this->mCurrentRow->publisher_id ? false : true,						)
					) . $this->msg( 'newsletter-publisher-radiobutton-label' );

					return $radioOwner . $radioPublisher;
			case 'action' :
					$remButton = HTML::element(
						'input',
						array(
							'type' => 'button',
							'value' => 'Remove',
							'name' => $previous,
							'id' => $this->mCurrentRow->publisher_id
						)
					);

					return ( self::$newsletterOwners[$this->mCurrentRow->newsletter_id] !== $this->mCurrentRow->publisher_id &&
						self::$newsletterOwners[$this->mCurrentRow->newsletter_id] == $this->getUser()->getId() ) ? $remButton : '';

		}
	}

	function getCellAttrs( $field, $value ) {
		return array(
			'align' => 'center'
		);
	}

	function getDefaultSort() {
		return 'newsletter_id';
	}

	function isFieldSortable( $field ) {
		return false;
	}

}