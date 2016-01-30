<?php

/**
 * Special page for subscribing/un-subscribing a newsletter
 *
 * @license GNU GPL v2+
 * @author Tina Johnson
 */
class SpecialNewsletters extends SpecialPage {

	/**
	 * @var string
	 */
	private $option;

	public function __construct() {
		parent::__construct( 'Newsletters' );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();
		if ( $this->getUser()->isLoggedIn() ) {
			// IPs cannot subscribe and these modules are only used for subscription functionality.
			$out->addModuleStyles( 'ext.newsletter.newsletters.styles' );
			$out->addModules( 'ext.newsletter.newsletters' );
		}

		$out->setSubtitle( NewsletterLinksGenerator::getSubtitleLinks( $this->getContext() ) );

		$this->option = $this->getRequest()->getVal( 'filter', 'all' );
		if ( !in_array( $this->option, array( 'all', 'subscribed', 'unsubscribed' ) ) ) {
			// Set a default if invalid input was received from the user
			$this->option = 'all';
		}

		$filterTableForm = HTMLForm::factory(
			'ooui',
			$this->getFormFields(),
			$this->getContext()
		);
		$filterTableForm->setMethod( 'get' );
		$filterTableForm->setSubmitProgressive();
		$filterTableForm->setSubmitTextMsg( 'newsletter-list-go-button' );
		$filterTableForm->setWrapperLegendMsg( 'newsletter-list-section' );

		$pager = new NewsletterTablePager();
		$pager->setUserOption( $this->option );

		if ( $pager->getNumRows() === 0 && $this->option === 'all' ) {
			// No newsletters exist on this wiki so just show an error page without the form
			$out->showErrorPage( 'newsletters', 'newsletter-none-found' );
			return;
		}

		if ( $this->getUser()->isLoggedIn() ) {
			$filterTableForm->prepareForm()->displayForm( false );
		}

		if ( $pager->getNumRows() > 0 ) {
			$out->addParserOutput( $pager->getFullOutput() );
		} else {
			$out->addWikiMsg( 'newsletter-list-search-none-found' );
		}
	}

	private function getFormFields() {
		return array(
			'filter' => array(
				'type' => 'select',
				'name' => 'filter',
				'label-message' => 'newsletter-list-table',
				'options' => array(
					$this->msg( 'newsletter-list-option-all' )->escaped() => 'all',
					$this->msg( 'newsletter-list-option-subscribed' )->escaped() => 'subscribed',
					$this->msg( 'newsletter-list-option-unsubscribed' )->escaped() => 'unsubscribed'
				),
				'default' => $this->option,
			),
		);
	}
}
