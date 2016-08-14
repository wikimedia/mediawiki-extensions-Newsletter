<?php
/**
 * @license GNU GPL v2+
 * @author tonythomas
 */

class NewsletterDeletionUpdate extends DataUpdate {
	/**
	 * Newsletter name
	 * @var string
	 */
	private $newsletter;

	public function __construct( $newsletterName ) {
		$this->newsletter = Newsletter::newFromName( $newsletterName );
	}
	public function doUpdate() {
		$store = NewsletterStore::getDefaultInstance();
		$store->deleteNewsletter( $this->newsletter );
	}
}