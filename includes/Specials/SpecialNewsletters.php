<?php

namespace MediaWiki\Extension\Newsletter\Specials;

use MediaWiki\Extension\Newsletter\Specials\Pagers\NewsletterTablePager;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\SpecialPage\SpecialPage;

/**
 * Implements Special:Newsletter which lists all the newsletters on the wiki.
 * Logged-in users can also filter by subscribed/unsubscribed newsletters and
 * also subscribe and unsubscribe from individual newsletters.
 *
 * @license GPL-2.0-or-later
 * @author Tina Johnson
 */
class SpecialNewsletters extends SpecialPage {

	/**
	 * @var string Filter option for the table - doesn't affect anons
	 */
	private $option;

	public function __construct() {
		parent::__construct( 'Newsletters' );
	}

	public function execute( $par ) {
		$this->setHeaders();

		$out = $this->getOutput();
		$out->addModuleStyles( 'ext.newsletter.newsletters.styles' );
		$user = $this->getUser();

		$this->addHelpLink( 'Help:Extension:Newsletter' );

		if ( $user->isAllowed( 'newsletter-create' ) ) {
			$createLink = $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'NewsletterCreate' ),
				$this->msg( 'newsletter-subtitlelinks-create' )->text()
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
		$introMessage = 'newsletter-list-intro-not-logged-in';
		if ( $user->isRegistered() ) {
			// Filter form and resource modules needed for logged-in users only
			$out->addModuleStyles( 'ext.newsletter.newsletters.styles' );
			$out->addModules( 'ext.newsletter.newsletters' );

			$filterForm = HTMLForm::factory(
				'ooui',
				$this->getFormFields(),
				$this->getContext()
			);
			$filterForm->setId( 'mw-newsletter-filter-form' );
			$filterForm->setSubmitID( 'mw-newsletter-filter-submit' );
			$filterForm->setMethod( 'get' );
			// Note that submit button is hidden for users with JS enabled in
			// as changing the dropdown menu's option updates the page for them
			$filterForm->setSubmitTextMsg( 'newsletter-list-go-button' );
			$filterForm->prepareForm();
			$formHtml = $filterForm->getHTML( false );

			$introMessage = 'newsletter-list-intro';
		}

		$pager = new NewsletterTablePager();
		$pager->setUserOption( $this->option );
		if ( $pager->getNumRows() ) {
			$out->addWikiMsg( $introMessage );
			$out->addHTML( $formHtml );
			$out->addParserOutput( $pager->getFullOutput() );
		} elseif ( $filtered ) {
			$out->addWikiMsg( $introMessage );
			$out->addHTML( $formHtml );
			$out->addWikiMsg( 'newsletter-list-search-none-found' );
		} else {
			// No newsletters exist on this wiki so just show an error page without the form
			$out->showErrorPage( 'newsletters', 'newsletter-none-found' );
		}
	}

	private function getFormFields(): array {
		return [
			'filter' => [
				'id' => 'mw-newsletter-filter-options',
				'type' => 'select',
				'name' => 'filter',
				'label-message' => 'newsletter-list-table',
				'options' => [
					$this->msg( 'newsletter-list-option-all' )->escaped() => 'all',
					$this->msg( 'newsletter-list-option-subscribed' )->escaped() => 'subscribed',
					$this->msg( 'newsletter-list-option-unsubscribed' )->escaped() => 'unsubscribed'
				],
				'default' => $this->option,
			],
		];
	}

}
