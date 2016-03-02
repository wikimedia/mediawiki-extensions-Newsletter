<?php
/**
 * Implements Special:Newsletter which lists all the newsletters on the wiki.
 * Logged-in users can also filter by subscribed/unsubscribed newsletters and
 * also subscribe and unsubscribe from individual newsletters.
 *
 * @license GNU GPL v2+
 * @author Tina Johnson
 */
class SpecialNewsletters extends SpecialPage {
	/**
	 * @var string $option Filter option for the table - doesn't affect anons
	 */
	private $option;

	public function __construct() {
		parent::__construct( 'Newsletters' );
	}

	public function execute( $par ) {
		$this->setHeaders();

		$out = $this->getOutput();
		$user = $this->getUser();

		if ( $user->isAllowed( 'newsletter-create' ) ) {
			$createLink = Linker::linkKnown(
				SpecialPage::getTitleFor( 'NewsletterCreate' ),
				$this->msg( 'newsletter-subtitlelinks-create' )->escaped()
			);
			$out->setSubtitle( $createLink );
		}

		$this->option = $this->getRequest()->getVal( 'filter', 'all' );
		$filtered = $this->option === 'subscribed' || $this->option === 'unsubscribed';
		if ( !$filtered ) {
			// Defaults to 'all' if unexpected input was received
			$this->option = 'all';
		}

		$formHtml = '';
		if ( $user->isLoggedIn() ) {
			// Filter form and resource modules needed for logged-in users only
			$out->addModuleStyles( 'ext.newsletter.newsletters.styles' );
			$out->addModules( 'ext.newsletter.newsletters' );

			$filterForm = HTMLForm::factory(
				'ooui',
				$this->getFormFields(),
				$this->getContext()
			);
			$filterForm->setId( 'mw-newsletter-filter-form' );
			$filterForm->setSubmitId( 'mw-newsletter-filter-submit' );
			$filterForm->setMethod( 'get' );
			// Note that submit button is hidden for users with JS enabled in
			// as changing the dropdown menu's option updates the page for them
			$filterForm->setSubmitProgressive();
			$filterForm->setSubmitTextMsg( 'newsletter-list-go-button' );
			$filterForm->prepareForm();
			$formHtml = $filterForm->getHTML( false );
		}

		$pager = new NewsletterTablePager();
		$pager->setUserOption( $this->option );
		if ( $pager->getNumRows() ) {
			$out->addHTML( $formHtml );
			$out->addParserOutput( $pager->getFullOutput() );
		} elseif ( $filtered ) {
			$out->addHTML( $formHtml );
			$out->addWikiMsg( 'newsletter-list-search-none-found' );
		} else {
			// No newsletters exist on this wiki so just show an error page without the form
			$out->showErrorPage( 'newsletters', 'newsletter-none-found' );
		}
	}

	private function getFormFields() {
		return array(
			'filter' => array(
				'id' => 'mw-newsletter-filter-options',
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
