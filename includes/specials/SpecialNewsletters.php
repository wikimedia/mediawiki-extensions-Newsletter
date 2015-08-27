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
		$this->requireLogin();
		$out = $this->getOutput();
		$out->addModules( 'ext.newsletter' );
		$out->setSubtitle( LinksGenerator::getSubtitleLinks() );
		$pager = new NewsletterTablePager();

		if ( $pager->getNumRows() > 0 ) {
			$out->addHTML(
				$pager->getNavigationBar() .
				$pager->getBody() .
				$pager->getNavigationBar()
			);
		} else {
			$out->showErrorPage( 'newsletters', 'newsletter-none-found' );
		}
	}

}
