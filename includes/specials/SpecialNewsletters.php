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
		$out = $this->getOutput();
		if ( $this->getUser()->isLoggedIn() ) {
			// IPs cannot subscribe
			$out->addModules( 'ext.newsletter' );
		}
		$out->setSubtitle( LinksGenerator::getSubtitleLinks() );
		$pager = new NewsletterTablePager();

		if ( $pager->getNumRows() > 0 ) {
			$out->addParserOutput( $pager->getFullOutput() );
		} else {
			$out->showErrorPage( 'newsletters', 'newsletter-none-found' );
		}
	}

}
