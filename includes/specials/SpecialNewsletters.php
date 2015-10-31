<?php

/**
 * Special page for subscribing/un-subscribing a newsletter
 *
 * @license GNU GPL v2+
 * @author Tina Johnson
 */
class SpecialNewsletters extends SpecialPage {

	public function __construct() {
		parent::__construct( 'Newsletters' );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();
		if ( $this->getUser()->isLoggedIn() ) {
			// IPs cannot subscribe and these modules are only used for subscription functionality.
			$out->addModuleStyles( 'ext.newsletter.styles' );
			$out->addModules( 'ext.newsletter' );
		}
		$out->setSubtitle( NewsletterLinksGenerator::getSubtitleLinks() );

		$pager = new NewsletterTablePager();
		if ( $pager->getNumRows() > 0 ) {
			$out->addParserOutput( $pager->getFullOutput() );
		} else {
			$out->showErrorPage( 'newsletters', 'newsletter-none-found' );
		}
	}

}
